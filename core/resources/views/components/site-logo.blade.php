@props([
    'type' => '',
])
<div class="logo">
    <a href="{{ route('home') }}">
        <img src="{{ siteLogo($type) }}" alt="@lang('logo')" width="298" height="69">
    </a>
</div>
