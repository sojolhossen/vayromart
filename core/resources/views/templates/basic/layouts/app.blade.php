<!doctype html>
<html lang="{{ config('app.locale') }}" itemscope itemtype="http://schema.org/WebPage">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title> {{ gs()->siteName(__($pageTitle)) }}</title>
    @include('partials.seo')
    <link type="image/x-icon" href="{{ siteFavicon() }}" rel="shortcut icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/global/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/global/css/all.min.css') }}" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="{{ asset('assets/global/css/all.min.css') }}"></noscript>
    <link rel="stylesheet" href="{{ asset('assets/global/css/line-awesome.min.css') }}" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="{{ asset('assets/global/css/line-awesome.min.css') }}"></noscript>
    @stack('style-lib')
    <link rel="stylesheet" href="{{ asset($activeTemplateTrue . 'css/main.css') }}">
    <style>
        :root {
            --base-h: {{ hexToHsl(gs('base_color'))['h'] }};
            --base-s: {{ hexToHsl(gs('base_color'))['s'] }}%;
            --base-l: {{ hexToHsl(gs('base_color'))['l'] }}%;
        }
    </style>
    @stack('style')
    <link href="{{ asset($activeTemplateTrue . 'css/custom.css') }}" rel="stylesheet">
</head>

@php echo loadExtension('google-analytics') @endphp
@php echo loadExtension('facebook-pixel') @endphp

<body>
    <div class="body-overlay" id="body-overlay"></div>
    @include('Template::partials.preloader')

    @yield('app')

    @if(gs('chatbot_enabled'))
        @include('Template::partials.chatbot')
    @endif

    @stack('modal')

    <script src="{{ asset('assets/global/js/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('assets/global/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset($activeTemplateTrue . 'js/jquery.validate.js') }}"></script>
    @stack('script-lib')
    <script src="{{ asset($activeTemplateTrue . 'js/main.js') }}"></script>
    @php echo loadExtension('tawk-chat') @endphp
    @include('partials.notify')
    @if (gs('pn'))
        @include('partials.push_script')
    @endif
    @stack('script')
</body>

</html>
