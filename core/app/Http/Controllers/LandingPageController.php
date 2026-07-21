<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ChatbotLandingPage;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Guest;
use App\Models\ProductVariant;
use App\Models\AdminNotification;
use App\Lib\ProductManager;
use Illuminate\Http\Request;

class LandingPageController extends Controller
{
    /**
     * View standalone landing page by slug
     */
    public function viewPage($slug)
    {
        $landingPage = ChatbotLandingPage::where('slug', $slug)->firstOrFail();
        
        return response($landingPage->content)
            ->header('Content-Type', 'text/html');
    }

    /**
     * Place order from landing page COD form
     */
    public function placeOrder(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'name' => 'required|string|max:255',
            'mobile' => 'required|string|regex:/^(?:\+?88)?01[3-9]\d{8}$/',
            'address' => 'required|string|max:500',
            'shipping_location' => 'nullable|string|in:inside,outside',
            'landing_page_id' => 'nullable|integer|exists:chatbot_landing_pages,id',
        ], [
            'name.required' => 'অনুগ্রহ করে আপনার নাম লিখুন।',
            'mobile.required' => 'অনুগ্রহ করে আপনার মোবাইল নম্বর লিখুন।',
            'mobile.regex' => 'অনুগ্রহ করে একটি সঠিক ১১ ডিজিটের মোবাইল নম্বর লিখুন।',
            'address.required' => 'অনুগ্রহ করে আপনার ডেলিভারি ঠিকানা লিখুন।',
        ]);

        $product = Product::published()->findOrFail($request->product_id);
        $quantity = 1; // Default quantity is 1 for direct landing page purchase
        $variantId = 0; // Default variant is 0

        // Check stock
        if ($product->track_inventory) {
            $stockQuantity = $product->inStock(null);
            if ($quantity > $stockQuantity) {
                $notify[] = ['error', 'দুঃখিত, এই পণ্যটি এই মুহূর্তে স্টকে নেই।'];
                return back()->withNotify($notify)->withInput();
            }
        }

        // Get/Create Guest user or authenticate
        $userId = auth()->id() ?? 0;
        $guestId = null;

        if ($userId === 0) {
            $cleanMobile = preg_replace('/[^0-9]/', '', $request->mobile);
            // Bangladesh mobile is 11 digits, extract last 11 digits
            if (strlen($cleanMobile) > 11) {
                $cleanMobile = substr($cleanMobile, -11);
            }

            $guestEmail = 'guest_landing_' . session()->getId() . '@vayromart.local';
            $guest = Guest::where('mobile', $cleanMobile)->first();
            if (!$guest) {
                $guest = new Guest();
                $guest->email = $guestEmail;
                $guest->mobile = $cleanMobile;
                $guest->session_id = session()->getId();
                $guest->dial_code = '880';
                $guest->country_code = 'BD';
                $guest->country_name = 'Bangladesh';
                $guest->save();
            }
            $guestId = $guest->id;
            session()->put('guest_user_data', $guest);
        }

        // Price calculation
        $price = null;
        $discount = 0;
        if ($request->landing_page_id) {
            $landingPage = ChatbotLandingPage::find($request->landing_page_id);
            if ($landingPage && isset($landingPage->design_settings['custom_price']) && !empty($landingPage->design_settings['custom_price'])) {
                $price = floatval($landingPage->design_settings['custom_price']);
                $regPrice = !empty($landingPage->design_settings['custom_regular_price']) ? floatval($landingPage->design_settings['custom_regular_price']) : ($product->regular_price > $price ? $product->regular_price : round($price * 1.35));
                $discount = $regPrice - $price;
            }
        }

        if ($price === null) {
            $prices = $product->prices(null);
            $price = $prices->sale_price;
            $discount = $prices->regular_price - $prices->sale_price;
        }

        $subtotal = $price * $quantity;
        $shippingCharge = ($request->shipping_location === 'outside') ? 130.00 : 80.00;
        $totalAmount = $subtotal + $shippingCharge;

        // Generate unique Order Number
        $prefix = 'OID-';
        $last = Order::max('id') + 1;
        $formattedLast = str_pad($last, 5, '0', STR_PAD_LEFT);
        $orderNumber = $prefix . $formattedLast;

        // Create Order
        $order = new Order();
        $order->order_number = $orderNumber;
        $order->user_id = $userId;
        $order->guest_id = $guestId;

        $names = explode(' ', trim($request->name), 2);
        $firstName = $names[0] ?? '';
        $lastName = $names[1] ?? '';

        $shippingAddressObj = [
            'firstname' => $firstName,
            'lastname' => $lastName,
            'mobile' => $request->mobile,
            'email' => $userId ? auth()->user()->email : $guest->email,
            'city' => 'Dhaka',
            'state' => 'Dhaka',
            'zip' => '1000',
            'country_code' => 'BD',
            'dial_code' => '880',
            'country' => 'Bangladesh',
            'address' => $request->address,
        ];
        $order->shipping_address = (object)$shippingAddressObj;
        $order->shipping_method_id = 1; // Standard Delivery
        $order->shipping_charge = $shippingCharge;
        $order->is_cod = 1; // Cash on delivery
        $order->payment_status = 0; // Not Paid
        $order->status = 0; // Pending
        $order->subtotal = $subtotal;
        $order->total_amount = $totalAmount;
        $order->save();

        // Create Order Details
        $orderDetail = new OrderDetail();
        $orderDetail->order_id = $order->id;
        $orderDetail->product_id = $product->id;
        $orderDetail->product_variant_id = $variantId;
        $orderDetail->quantity = $quantity;
        $orderDetail->price = $price;
        $orderDetail->discount = $discount;
        $orderDetail->save();

        // Deduct stock and update log
        if ($product->track_inventory) {
            $product->in_stock -= $quantity;
            $product->save();

            $desc = "Sold {$quantity} product(s) via AI Landing Page";
            $productManager = new ProductManager();
            $productManager->createStockLog($product, $quantity, $desc, null, '-', $order->id);
        }

        // Send Facebook Conversions API (CAPI) Purchase Event
        sendFbCapiEvent('Purchase', [
            'value' => $totalAmount,
            'content_ids' => [(string)$product->id],
            'content_type' => 'product',
            'num_items' => $quantity
        ], [
            'name' => $request->name,
            'phone' => $request->mobile,
            'email' => $shippingAddressObj['email'] ?? null
        ]);

        // Send Admin notification
        try {
            $adminNotification = new AdminNotification();
            $adminNotification->title = 'New order #' . $order->order_number . ' has been created via AI Landing Page';
            $adminNotification->click_url = urlPath('admin.order.index') . '?search=' . $order->order_number;
            $adminNotification->save();
        } catch (\Exception $e) {}

        // Show a premium order success page
        return view('templates.basic.landing_success', compact('order', 'product', 'totalAmount'));
    }
}
