<ul class="menu {{ @$classes }}">
    <li>
        <a href="{{ route('home') }}" @class(['active' => request()->routeIs('home')])>@lang('Home')</a>
    </li>
    <li>
        <a href="{{ route('categories') }}" @class(['active' => request()->routeIs('categories')])>@lang('Category')</a>
    </li>
    <li>
        <a href="{{ route('product.all') }}" @class(['active' => request()->routeIs('product.*')])>@lang('Product')</a>
    </li>
    <li>
        <a href="{{ auth()->check() ? route('user.home') : route('user.login') }}" @class(['active' => request()->routeIs('user.*')])>@lang('Account')</a>
    </li>
</ul>
