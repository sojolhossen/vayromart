<ul class="list list--row mobile-menu-icons justify-content-center justify-content-md-end option-list d-lg-none d-flex">
    <li>
        <a href="{{ route('home') }}" class="ecommerce">
            <span class="ecommerce__icon">
                <i class="las la-home" style="font-size: 22px;"></i>
            </span>
            <span class="ecommerce__text">@lang('Home')</span>
        </a>
    </li>

    <li>
        <a href="{{ route('categories') }}" class="ecommerce" id="cate-button">
            <span class="ecommerce__icon">
                <img src="{{ svg('category') }}" alt="category" width="22" height="22">
            </span>
            <span class="ecommerce__text">@lang('Category')</span>
        </a>
    </li>

    <li>
        <a href="javascript:void(0)" class="ecommerce @auth user-account-btn @endauth" id="account-button" @guest data-bs-toggle="modal" data-bs-target="#loginModal" @endguest>
            <span class="ecommerce__icon">
                <img src="{{ svg('my_account') }}" alt="account" width="22" height="22">
            </span>
            <span class="ecommerce__text">@lang('Account')</span>
        </a>
    </li>
</ul>


<div class="site-sidebar mobile-menu sidebar-nav d-lg-none">
    <button type="button" class="sidebar-close-btn">
        <i class="las la-times"></i>
    </button>

    <div class="mobile-menu-header">
        <div class="d-block d-lg-none">
            @include('Template::partials.menu.language_menu')
        </div>
    </div>
    <div class="mobile-menu-body">
        @include('Template::partials.menu.site_menu')
    </div>
</div>
