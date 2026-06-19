<!-- meta tags and other links -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ gs()->siteName($pageTitle ?? '') }}</title>

    <link rel="shortcut icon" type="image/png" href="{{ siteFavicon() }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/global/css/bootstrap.min.css') }}">

    <link rel="stylesheet" href="{{ asset('assets/admin/css/vendor/bootstrap-toggle.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/global/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/global/css/line-awesome.min.css') }}">

    @stack('style-lib')

    <link rel="stylesheet" href="{{ asset('assets/global/css/magnific-popup.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/global/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/custom.css') }}">

    @php
        $baseColor = gs('base_color') ?: '4634ff';
        $red = hexdec(substr($baseColor, 0, 2));
        $green = hexdec(substr($baseColor, 2, 2));
        $blue = hexdec(substr($baseColor, 4, 2));
    @endphp
    <style>
        :root {
            --primary-color: #{{ $baseColor }};
        }

        .text--primary, 
        .sidebar__menu .sidebar-menu-item>a:hover .menu-icon,
        .sidebar__menu .sidebar-menu-item>a:hover .menu-title,
        .sidebar__menu .sidebar-submenu .sidebar-menu-item.active a .menu-icon,
        .sidebar__menu .sidebar-submenu .sidebar-menu-item.active a .menu-title,
        .sidebar__menu .sidebar-submenu .sidebar-menu-item a:hover .menu-title,
        .sidebar__menu .sidebar-submenu .sidebar-menu-item a:hover .menu-icon,
        a,
        .color--blue {
            color: var(--primary-color) !important;
        }

        .bg--primary,
        .sidebar__menu .sidebar-menu-item .side-menu--open,
        .sidebar__menu .sidebar-menu-item.active>a,
        .dataTables_paginate .pagination .page-item.active .page-link,
        table.table--light thead th,
        table.dataTable thead tr,
        .nav-pills .nav-link.active,
        .nav-pills .show>.nav-link,
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable,
        .dropdown-item.active,
        .dropdown-item:active,
        .dropdown-item:hover,
        .link_badge:hover,
        .slider:before,
        .form-check-input:checked {
            background-color: var(--primary-color) !important;
        }

        .bg--primary {
            --color: var(--primary-color) !important;
        }

        .sidebar[class*='bg--'] .sidebar__menu .sidebar-menu-item>a:hover,
        .sidebar__menu .sidebar-submenu .sidebar-menu-item.active>a {
            background-color: rgba({{ $red }}, {{ $green }}, {{ $blue }}, 0.35) !important;
        }

        .border--primary,
        .bl--5-primary,
        .nav-tabs-primary .nav-item a.active,
        .form-check-input:checked,
        .form-check-input:focus,
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color) !important;
        }

        .sidebar__menu .sidebar-menu-item>a:hover .menu-icon {
            text-shadow: 1px 2px 5px var(--primary-color) !important;
        }

        .btn--primary,
        .btn-outline--primary:hover {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
            color: #fff !important;
        }

        .btn-outline--primary {
            border-color: var(--primary-color) !important;
            color: var(--primary-color) !important;
        }

        .btn-outline--primary:focus,
        .btn-outline--primary.focus {
            box-shadow: 0 0 0 0.2rem rgba({{ $red }}, {{ $green }}, {{ $blue }}, 0.5) !important;
        }
    </style>

    @stack('style')
</head>

<body>
    @yield('content')

    <script src="{{ asset('assets/global/js/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('assets/global/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/vendor/bootstrap-toggle.min.js') }}"></script>

    @include('partials.notify')
    @stack('script-lib')

    <script src="{{ asset('assets/global/js/magnific-popup.min.js') }}"></script>
    <script src="{{ asset('assets/global/js/nicEdit.js') }}"></script>

    <script src="{{ asset('assets/global/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/app.js') }}"></script>
    <script src="{{ asset('assets/admin/js/custom.js') }}"></script>
    <script src="{{ asset('assets/admin/js/cu-modal.js') }}"></script>

    {{-- LOAD NIC EDIT --}}
    <script>
        "use strict";
        bkLib.onDomLoaded(function() {
            $(".nicEdit").each(function(index) {
                $(this).attr("id", "nicEditor" + index);
                new nicEditor({
                    fullPanel: true
                }).panelInstance('nicEditor' + index, {
                    hasPanel: true
                });
            });
        });

        (function($) {
            $(document).on('mouseover ', '.nicEdit-main,.nicEdit-panelContain', function() {
                if ($(this).hasClass('nicEdit-main')) {
                    $(this).focus();
                } else {
                    $(this).parent().next('div').find('.nicEdit-main').focus();
                }
            });

            $('.breadcrumb-nav-open').on('click', function() {
                $(this).toggleClass('active');
                $('.breadcrumb-nav').toggleClass('active');
            });

            $('.breadcrumb-nav-close').on('click', function() {
                $('.breadcrumb-nav').removeClass('active');
            });

            if ($('.topTap').length) {
                $('.breadcrumb-nav-open').removeClass('d-none');
            }

            $('.image-popup').magnificPopup({
                type: 'image'
            });
        })(jQuery);
    </script>

    @stack('script')

</body>

</html>
