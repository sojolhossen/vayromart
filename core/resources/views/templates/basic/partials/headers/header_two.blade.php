@php
    $headerTwo = App\Models\Frontend::where('data_keys', 'header_two.content')->first()?->data_values;
    $headerTwoKeys = array_keys((array) $headerTwo->group);
    $firstLetters = array_map(function ($key) {
        return $key[0];
    }, $headerTwoKeys);

    $headerTwoLayoutClass = 'middle-menu-' . implode('', $firstLetters);
@endphp

@if (@$headerTwo->status == 'on')
    <div class="header-middle">
        <div class="container">
            <div class="d-flex justify-content-between header-wrapper {{ $headerTwoLayoutClass }} align-items-center">
                <button class="primary-menu-button d-lg-none text-dark border-0 bg-transparent flex-shrink-0" type="button" style="margin-right: 15px;">
                    <span style="background-color: #333 !important;"></span>
                    <span style="background-color: #333 !important;"></span>
                    <span style="background-color: #333 !important;"></span>
                </button>
                @foreach ($headerTwo->group as $key => $group)
                    @if ($key == 'logo_widget' && isset($group->status) && $group->status == 'on')
                        <x-site-logo type="dark" />
                    @endif
                    @if ($key == 'search_widget' && isset($group->status) && $group->status == 'on')
                        <div class="header-search-wrapper">
                            <form action="{{ route('product.all') }}" method="GET" class="header-search-form me-auto @if (!request()->routeIs('home')) w-100 @endif">
                                <div class="header-form-group">
                                    <button type="button" class="search-close-btn"><i class="las la-arrow-up"></i></button>
                                    <input type="text" class="form--control" name="search" value="{{ request()->search }}" placeholder="@lang('I am shopping for')...">
                                </div>
                                <button class="icon" type="submit"><i class="las la-search"></i></button>
                            </form>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="javascript:void(0)" class="ecommerce cart-button mobile-cart-btn d-lg-none" style="position: relative; display: flex; align-items: center; justify-content: center; width: 35px; height: 35px; border-radius: 5px; border: 1px solid hsl(var(--border)); background: #fff; color: #333; font-size: 20px; text-decoration: none;">
                                <i class="las la-shopping-bag"></i>
                                <span class="cartItemCount ecommerce__is d-none" style="position: absolute; top: -5px; right: -5px; background: #ff3a3a !important; color: #fff !important; font-size: 10px !important; min-width: 16px !important; height: 16px !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; font-weight: 700 !important; border: 2px solid #fff !important; line-height: 1;">0</span>
                            </a>
                            <button type="button" class="header-search-btn"><i class="las la-search"></i></button>
                        </div>
                    @endif
                    @if ($key == 'widgets')
                        @php
                            $widgets = collect($group);
                        @endphp

                        @if ($widgets->count())
                            <ul class="list list--row option-list-wrapper justify-content-center justify-content-md-end option-list d-flex align-items-center">
                                @foreach ($widgets as $widget)
                                    @if (gs('product_compare') && $widget->key == 'compare' && @$widget->status == 'on')
                                        <li class="d-none d-lg-block">
                                            <a href="{{ route('compare.all') }}" class="ecommerce">
                                                <span class="ecommerce__icon">
                                                    <i class="las la-exchange-alt"></i>
                                                    <span class="ecommerce__is compare-count d-none"></span>
                                                </span>
                                                <span class="ecommerce__text">@lang('Compare')</span>
                                            </a>
                                        </li>
                                    @endif

                                    @if (gs('product_wishlist') && $widget->key == 'wishlist')
                                        <li class="d-none d-lg-block">
                                            <a href="javascript:void(0)" class="ecommerce wish-button">
                                                <span class="ecommerce__icon">
                                                    <i class="las la-heart"></i>
                                                    <span class="ecommerce__is wishlist-count d-none"></span>
                                                </span>
                                                <span class="ecommerce__text">@lang('Wishlist')</span>
                                            </a>
                                        </li>
                                    @endif
                                    @if ($widget->key == 'cart' && @$widget->status == 'on')
                                        <li>
                                            <a href="javascript:void(0)" class="ecommerce cart-button">
                                                <span class="ecommerce__icon">
                                                    <i class="las la-shopping-bag"></i>
                                                    <span class="ecommerce__is cartItemCount d-none"></span>
                                                </span>
                                                <span class="ecommerce__text">@lang('Cart')</span>
                                            </a>
                                        </li>
                                    @endif

                                    @if ($widget->key == 'notifications' && @$widget->status == 'on')
                                        @auth
                                            <li>
                                                <x-user-notification-component />
                                            </li>
                                        @endauth
                                    @endif

                                    @if ($widget->key == 'user_auth' && @$widget->status == 'on')
                                        <li class="d-none d-lg-block">
                                            @include('Template::partials.user_auth_options')
                                        </li>
                                    @endif

                                    @if ($widget->key == 'language' && @$widget->status == 'on')
                                        <li class="d-none d-lg-block">
                                            @include($activeTemplate . 'partials.menu.language_menu')
                                        </li>
                                    @endif
                                @endforeach

                            </ul>
                        @endif
                    @endif
                @endforeach
            </div>
        </div>
    </div>
@endif


