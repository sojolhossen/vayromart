<?php

use Illuminate\Support\Facades\Route;

Route::get('/clear', function () {
    \Illuminate\Support\Facades\Artisan::call('optimize:clear');
});

Route::get('/test-report', function () {
    $botToken = env('TELEGRAM_BOT_TOKEN');
    $chatId = env('TELEGRAM_CHAT_ID');
    
    if (!$botToken || !$chatId) {
        return "Please set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID in .env first!";
    }
    
    $date = date('Y-m-d');
    
    $totalVisitors = \DB::table('visitor_logs')->where('visit_date', $date)->count();
    $deviceStats = \DB::table('visitor_logs')
        ->where('visit_date', $date)
        ->select('device', \DB::raw('count(*) as total'))
        ->groupBy('device')
        ->get()
        ->pluck('total', 'device')
        ->toArray();
    
    $pcCount = $deviceStats['PC'] ?? 0;
    $mobileCount = $deviceStats['Mobile'] ?? 0;
    $tabletCount = $deviceStats['Tablet'] ?? 0;
    
    $totalOrders = \App\Models\Order::isValidOrder()->whereDate('created_at', $date)->count();
    $totalSales = \App\Models\Order::isValidOrder()->whereDate('created_at', $date)->sum('total_amount');
    $newUsers = \App\Models\User::whereDate('created_at', $date)->count();
    
    $message = "📊 <b>TEST Daily Website Report (" . date('d M Y', strtotime($date)) . ")</b>\n";
    $message .= "━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "👥 <b>Traffic Overview (Today so far):</b>\n";
    $message .= "• Unique Visitors: <b>" . $totalVisitors . "</b>\n";
    $message .= "• PC Visitors: <b>" . $pcCount . "</b>\n";
    $message .= "• Mobile Visitors: <b>" . $mobileCount . "</b>\n";
    if ($tabletCount > 0) {
        $message .= "• Tablet Visitors: <b>" . $tabletCount . "</b>\n";
    }
    $message .= "\n🛒 <b>Sales & Orders:</b>\n";
    $message .= "• Total Orders: <b>" . $totalOrders . "</b>\n";
    $message .= "• Total Sales: <b>" . gs('cur_sym') . showAmount($totalSales, currencyFormat: false) . " " . gs('cur_text') . "</b>\n";
    $message .= "\n👤 <b>User Registrations:</b>\n";
    $message .= "• New Signups: <b>" . $newUsers . "</b>\n";
    $message .= "\n━━━━━━━━━━━━━━━━━━━";
    
    $url = "https://api.telegram.org/bot" . $botToken . "/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]
    ];

    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result !== false) {
        return "Test report sent successfully to Telegram!";
    }
    
    return "Failed to send report. Please check Telegram bot permissions.";
});

// User Support Ticket
Route::controller('TicketController')->prefix('ticket')->name('ticket.')->group(function () {
    Route::get('/', 'supportTicket')->name('index');
    Route::get('new', 'openSupportTicket')->name('open');
    Route::post('create', 'storeSupportTicket')->name('store');
    Route::get('view/{ticket}', 'viewTicket')->name('view');
    Route::post('reply/{id}', 'replyTicket')->name('reply');
    Route::post('close/{id}', 'closeTicket')->name('close');
    Route::get('download/{attachment_id}', 'ticketDownload')->name('download');
});

Route::get('app/deposit/confirm/{hash}', 'Gateway\PaymentController@appDepositConfirm')->name('deposit.app.confirm');

Route::controller('CartController')->name('cart.')->group(function () {
    Route::get('cart', 'cart')->name('page');
    Route::post('add-to-cart/{productId}', 'addToCart')->name('add');
    Route::post('cart-update/{id}', 'updateCartItem')->name('update');
    Route::get('cart-shortlist', 'partialCart')->name('shortlist');
    Route::get('cart-items-count', 'cartItemsCount')->name('items.count');
    Route::get('cart-subtotal', 'cartSubtotal')->name('items.subtotal');
    Route::post('remove-from-cart/{id}', 'removeCartItem')->name('remove');
    Route::post('apply-coupon', 'applyCoupon')->name('coupon.apply');
    Route::post('remove-coupon', 'removeCoupon')->name('coupon.remove');
});

Route::controller('WishlistController')->name('wishlist.')->group(function () {
    Route::get('wishlist', 'wishList')->name('page');
    Route::post('add-to-wishlist/{productId}', 'addToWishList')->name('add');
    Route::get('wishlist-short', 'partialWishlist')->name('shortlist');
    Route::get('wishlist-count', 'wishlistItemsCount')->name('items.count');
    Route::post('remove-from-wishlist/{id}', 'remove')->name('remove');
});

Route::controller('ProductController')->name('product.')->group(function () {
    Route::get('products', 'products')->name('all');
    Route::get('products/{category}', 'productByCategory')->name('by.category');
    Route::get('{slug}/products', 'productsByBrand')->name('by.brand');
    Route::get('product/{slug}', 'productDetails')->name('detail');
    Route::get('products/{id}/reviews', 'reviews')->name('reviews');
    Route::get('product/{slug}/stock-by-variant', 'geStockByVariant')->name('variant.stock');
    Route::get('images-by-variant/{productId}', 'getImagesByVariant')->name('variant.image');
    Route::get('compare-wishlist-cart-date', 'compareWishlistAndCartData')->name('compare.wishlist.cart.data');
});

Route::redirect('product', 'products');

Route::controller('CompareController')->name('compare.')->group(function () {
    Route::get('compare-products', 'compare')->name('all');
    Route::post('add-to-compare', 'addToCompare')->name('add');
    Route::get('compare-products-count', 'compareProductsCount')->name('count');
    Route::post('remove-from-compare/{id?}', 'removeFromCompare')->name('remove');
});

Route::name('checkout.')->group(function () {
    Route::controller('CheckoutController')->group(function () {
        Route::post('store-guest-info', 'storeGuestUser')->name('guest.info.store')->middleware('checkModule:guest_checkout');
        Route::post('store-guest-shipping-info', 'storeGuestShippingInfo')->name('guest.shipping.info.store')->middleware('checkModule:guest_checkout');
        Route::get('checkout/shipping-info', 'shippingInfo')->name('shipping.info')->middleware('checkout.step:shipping_info');
        Route::post('add-shipping-address', 'addShippingInfo')->name('shipping.info.add')->middleware('checkout.step:shipping_info');
        Route::get('checkout/delivery-methods', 'deliveryMethods')->name('delivery.methods')->middleware('checkout.step:delivery_method');
        Route::post('add-delivery-method', 'addDeliveryMethod')->name('delivery.method.add')->middleware('checkout.step:delivery_method');
        Route::get('order-confirmation/{order}', 'confirmation')->name('confirmation');
    });

    Route::controller('PaymentController')->group(function () {
        Route::get('checkout/payment-methods', 'paymentMethods')->name('payment.methods')->middleware('checkout.step:payment');
        Route::post('complete-checkout', 'completeCheckout')->name('complete')->middleware('checkout.step:payment');
    });
});

// Payment
Route::prefix('deposit')->name('deposit.')->controller('Gateway\PaymentController')->group(function () {
    Route::get('confirm', 'depositConfirm')->name('confirm');
    Route::get('manual', 'manualDepositConfirm')->name('manual.confirm');
    Route::post('manual', 'manualDepositUpdate')->name('manual.update');
});

Route::controller('User\OrderController')->group(function () {
    Route::get('orders/{order_number}', 'orderDetails')->name('orders.details');
    Route::get('digital-item/download/{id}', 'download')->name('order.item.download');
});

Route::controller('SiteController')->group(function () {
    Route::get('categories', 'categories')->name('categories');
    Route::get('brands', 'brands')->name('brands');
    Route::get('track-order', 'trackOrder')->name('order.track');
    Route::get('order-data/{order_number}', 'getOrderTrackData')->name('track.order');
    Route::post('subscribe', 'addSubscriber')->name('subscribe');
    Route::get('faq', 'faq')->name('faq');
    Route::get('about-us', 'about')->name('about');

    Route::get('offers', 'offers')->name('offers');
    Route::get('offer-products/{id}', 'offerProducts')->name('offer.products');

    Route::get('/contact', 'contact')->name('contact');
    Route::post('contact-submit', 'contactSubmit')->name('contact.submit');
    Route::get('/change/{lang?}', 'changeLanguage')->name('lang');
    Route::get('cookie-policy', 'cookiePolicy')->name('cookie.policy');
    Route::get('/cookie/accept', 'cookieAccept')->name('cookie.accept');
    Route::get('policy/{slug}', 'policyPages')->name('policy.pages');

    Route::get('placeholder-image/{size}', 'placeholderImage')->withoutMiddleware('maintenance')->name('placeholder.image');
    Route::get('maintenance-mode', 'maintenance')->withoutMiddleware('maintenance')->name('maintenance');

    Route::get('/', 'index')->name('home');
});

Route::post('/ai-chatbot/message', 'ChatbotController@sendMessage')->name('chatbot.message');

Route::post('/telegram/webhook', [App\Http\Controllers\TelegramController::class, 'webhook'])->name('telegram.webhook');
Route::get('/telegram/set-webhook', [App\Http\Controllers\TelegramController::class, 'setWebhook'])->name('telegram.set_webhook');
Route::match(['get', 'post'], '/facebook/webhook', [App\Http\Controllers\FacebookWebhookController::class, 'handle'])->name('facebook.webhook');

// Standalone Privacy Policy page (required for Meta/Facebook Developer Portal)
Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy.policy');

// Data Deletion endpoint / callback URL (required for Meta Platform compliance)
Route::get('/data-deletion', function () {
    return redirect('/privacy-policy#data-deletion');
})->name('data.deletion');

// Chatbot Landing Pages
Route::controller('LandingPageController')->prefix('landing')->name('landing.')->group(function () {
    Route::get('/{slug}', 'viewPage')->name('view');
    Route::post('/checkout', 'placeOrder')->name('checkout');
});
