<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Lib\CartManager;
use App\Models\Guest;
use App\Models\Order;
use App\Models\ShippingAddress;
use App\Models\ShippingMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CheckoutController extends Controller {
    private $cartManager;

    public function __construct(CartManager $cartManager) {
        parent::__construct();
        $this->cartManager = $cartManager;
    }

    public function storeGuestUser(Request $request) {
        $countryData  = (array)json_decode(file_get_contents(resource_path('views/partials/country.json')));
        if (isset($countryData['BD'])) {
            $countryData = ['BD' => $countryData['BD']];
        }
        $countryCodes = implode(',', array_keys($countryData));
        $mobileCodes  = implode(',', array_column($countryData, 'dial_code'));
        $countries    = implode(',', array_column($countryData, 'country'));

        $request->validate([
            'email'        => 'required|email',
            'mobile'       => 'required|regex:/^([0-9]*)$/',
            'country_code' => 'required|in:' . $countryCodes,
            'country'      => 'required|in:' . $countries,
            'mobile_code'  => 'required|in:' . $mobileCodes,
        ]);

        $guest = Guest::where('email', $request->email)->where('mobile', $request->mobile)->where('dial_code', $request->mobile_code)->firstOrNew();
        $guest->email        = $request->email;
        $guest->dial_code    = $request->mobile_code;
        $guest->country_name = $request->country;
        $guest->country_code = $request->country_code;
        $guest->mobile       = $request->mobile;
        $guest->session_id   = getSessionId();
        $guest->save();

        session()->put('guest_user_data', $guest);
        return redirect()->route('checkout.shipping.info');
    }

    public function storeGuestShippingInfo(Request $request) {

        $request->validate([
            'firstname' => 'nullable|string',
            'lastname'  => 'nullable|string',
            'mobile'    => 'nullable|string',
            'email'     => 'nullable|email',
            'city'      => 'nullable|string',
            'state'     => 'nullable|string',
            'zip'       => 'nullable|string',
            'country'   => 'nullable|string',
            'address'   => 'nullable|string',
        ]);

        $shippingData = [
            'firstname'    => $request->firstname,
            'lastname'     => $request->lastname,
            'mobile'       => $request->mobile,
            'email'        => $request->email,
            'city'         => $request->city,
            'state'        => $request->state,
            'zip'          => $request->zip,
            'country_code' => $request->country_code,
            'dial_code'    => $request->mobile_code,
            'country'      => $request->country,
            'address'      => $request->address,
        ];

        Session::put('shipping_info', $shippingData);

        // Find or create the Guest model for the current guest session
        $guest = Guest::where('session_id', getSessionId())->first();
        if (!$guest) {
            $guest = new Guest();
            $guest->session_id = getSessionId();
        }

        // Email cannot be null in guests table, so generate a fallback if not provided
        $guestEmail = $request->email ?: 'guest_' . getSessionId() . '@vayromart.local';

        $guest->email        = $guestEmail;
        $guest->dial_code    = $request->mobile_code;
        $guest->country_name = $request->country;
        $guest->country_code = $request->country_code;
        $guest->mobile       = $request->mobile;
        $guest->save();

        session()->put('guest_user_data', $guest);

        return redirect()->route('checkout.delivery.methods');
    }

    //============= checkout step start here ===================//
    public function shippingInfo() {
        $pageTitle = 'Shipping Information';

        $shippingAddresses = ShippingAddress::where('user_id', auth()->id())->get();
        $countries         = getCountries();


        if (auth()->user()) {
            $view = 'Template::checkout_steps.shipping_info';
        } else {
            if (!gs('guest_checkout')) {
                abort(404);
            }

            $view = 'Template::checkout_steps.shipping_info_guest';
        }

        return view($view, compact('pageTitle', 'shippingAddresses', 'countries'));
    }

    public function addShippingInfo(Request $request) {
        $ids = ShippingAddress::where('user_id', auth()->id())->pluck('id')->toArray();

        $request->validate([
            'shipping_address_id' => 'required|in:' . implode(',', $ids)
        ], [
            'shipping_address_id.required' => 'Shipping address is required',
            'shipping_address_id.in' => 'Invalid address selected'
        ]);

        $checkoutData = session('shipping_info');
        $checkoutData['shipping_address_id'] = $request->shipping_address_id;

        session()->put('shipping_info', $checkoutData);
        return to_route('checkout.delivery.methods');
    }

    public function deliveryMethods() {
        $pageTitle = 'Delivery Methods';
        $shippingMethods = ShippingMethod::active()->get();
        return view('Template::checkout_steps.shipping_methods', compact('pageTitle', 'shippingMethods'));
    }

    public function addDeliveryMethod(Request $request) {
        $ids = ShippingMethod::active()->pluck('id')->toArray();

        $request->validate([
            'shipping_method_id' => 'required|in:' . implode(',', $ids)
        ], [
            'shipping_method_id.required' => 'Delivery type field is required',
            'shipping_method_id.in'       => 'Invalid delivery type selected'
        ]);

        $checkoutData = session('shipping_info');
        $checkoutData['shipping_method_id'] = $request->shipping_method_id;

        session()->put('shipping_info', $checkoutData);
        return to_route('checkout.payment.methods');
    }

    public function confirmation($orderNumber) {
        $order  = Order::where('order_number', $orderNumber)->with('deposit', 'orderDetail.product',  'orderDetail.productVariant', 'appliedCoupon')->first();

        $pageTitle = 'Order Number -' . $order->order_number;

        return view('Template::checkout_steps.confirmation', compact('pageTitle', 'order'));
    }

    private function appliedCoupon($cartData, $subtotal) {
        $coupon = session('coupon');

        if (!$coupon) {
            return null;
        }

        // Match the coupon code with database and check is exists
        $coupon  = $this->cartManager->getCouponByCode($coupon['code']);

        if (!$coupon) {
            return ['error' => "Applied coupon is invalid or expired"];
        }

        $checkCoupon = $this->cartManager->isValidCoupon($coupon, $subtotal, $cartData);

        if (isset($checkCoupon['error'])) {
            return $checkCoupon;
        }

        $coupon->discount_amount = $coupon->discountAmount($subtotal);
        return $coupon;
    }
}
