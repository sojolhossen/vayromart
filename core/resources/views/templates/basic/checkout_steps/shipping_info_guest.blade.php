@extends($activeTemplate . 'layouts.checkout')

@section('blade')
    <form action="{{ route('checkout.guest.shipping.info.store') }}" method="POST" id="shipping-form">
        @csrf
        <div>
            @php
                $shippingInformation = (object) Session::get('shipping_info');
                $checkoutContent = getContent('guest_checkout.content', true)?->data_values;
            @endphp

            @if ($checkoutContent->shipping_info_recipient_info_title)
                <h5 class="mb-1 ">{{ __($checkoutContent->shipping_info_recipient_info_title) }}</h5>
            @endif

            @if ($checkoutContent->shipping_info_recipient_info_description)
                <p class="text-muted fst-italic">
                    {{ __($checkoutContent->shipping_info_recipient_info_description) }}
                </p>
            @endif

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>@lang('Name')</label>
                        <input type="text" value="{{ @$shippingInformation->firstname ?: (auth()->user()?->fullname ?? '') }}" class="form-control form--control" name="firstname" placeholder="@lang('Your Full Name')" required>
                        <input type="hidden" name="lastname" value="{{ @$shippingInformation->lastname }}">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label>@lang('Mobile')</label>
                        <div class="input-group">
                            <span class="input-group-text mobile-code">+880</span>
                            <input type="hidden" name="mobile_code" value="880">
                            <input type="hidden" name="country_code" value="BD">
                            <input type="hidden" name="country" value="Bangladesh">
                            <input type="number" name="mobile" value="{{ @$shippingInformation->mobile ?: (auth()->user()?->mobile ?? '') }}" class="form-control form--control ps-0" placeholder="@lang('Mobile Number')" required>
                        </div>
                        <small class="text-muted"><i class="la la-info-circle"></i> @lang('Enter the mobile number without the country code.')</small>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label>@lang('Email (Optional)')</label>
                        <input type="email" value="{{ @$shippingInformation->email ?: (auth()->user()?->email ?? '') }}" class="form-control form--control" name="email" placeholder="@lang('Email Address (Optional)')">
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                @if ($checkoutContent->description_in_shipping_info_title)
                    <h5 class="mb-1 ">{{ __($checkoutContent->description_in_shipping_info_title) }}</h5>
                @endif

                @if ($checkoutContent->description_in_shipping_info_description)
                    <p class="text-muted fst-italic">
                        {{ __($checkoutContent->description_in_shipping_info_description) }}
                    </p>
                @endif

                <input type="hidden" name="state" value="{{ @$shippingInformation->state }}">
                <input type="hidden" name="city" value="{{ @$shippingInformation->city }}">
                <input type="hidden" name="zip" value="{{ @$shippingInformation->zip }}">

                <div class="col-md-12">
                    <div class="form-group">
                        <label>@lang('Address')</label>
                        <input type="text" value="{{ @$shippingInformation->address }}" class="form-control form--control" name="address" placeholder="@lang('Full Shipping Address')" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between flex-wrap mt-4">
            <a href="{{ route('cart.page') }}" class="text--base">
                <i class="las la-angle-left"></i> @lang('Back to Cart')
            </a>

            <button type="submit" class="btn btn--base h-45">@lang('Continue to Next') <i class="las la-angle-right"></i></button>
        </div>
    </form>
@endsection

@push('script')
    <script>
        "use strict";
        (function($) {
            $('select[name=country]').on('change', function() {
                $('input[name=mobile_code]').val($('select[name=country] :selected').data('mobile_code'));
                $('input[name=country_code]').val($('select[name=country] :selected').data('code'));
                $('.mobile-code').text('+' + $('select[name=country] :selected').data('mobile_code'));
            });

            $('select[name=country]').trigger('change');
        })(jQuery);
    </script>

    @php
        $fbPixel = \App\Models\Extension::where('act', 'facebook-pixel')->where('status', \App\Constants\Status::ENABLE)->first();
    @endphp
    @if ($fbPixel)
        <script>
            (function($) {
                "use strict";
                @php
                    $cartItems = cartManager()->getCart();
                    $subtotal = cartManager()->subtotal($cartItems);
                    $itemIds = $cartItems->pluck('product_id')->toArray();
                @endphp
                if (typeof fbq !== 'undefined') {
                    fbq('track', 'InitiateCheckout', {
                        content_ids: {!! json_encode($itemIds) !!},
                        content_type: 'product',
                        value: {{ $subtotal }},
                        currency: '{{ gs("cur_text") }}',
                        num_items: {{ $cartItems->sum('quantity') }}
                    });
                }
            })(jQuery);
        </script>
    @endif
@endpush
