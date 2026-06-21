@php
    $sliders = getContent('banner.element');
    $firstSlider = $sliders->first();
    $firstSliderUrl = $firstSlider ? frontendImage('banner', $firstSlider->data_values->slider, '990x480') : null;
@endphp

@if (!blank($sliders))
    @push('style-lib')
        @if($firstSliderUrl)
            <link rel="preload" as="image" href="{{ $firstSliderUrl }}">
        @endif
    @endpush

    <div class="slider-wrapper overflow-hidden rounded--5">
        <div class="banner-slider owl-theme owl-carousel">
            @foreach ($sliders as $index => $slider)
                <div class="slide-item">
                    <a href="{{ @$slider->data_values->link }}" class="d-block">
                        @if($index == 0)
                            <img src="{{  frontendImage('banner', @$slider->data_values->slider, '990x480') }}" alt="slider-image" width="990" height="480">
                        @else
                            <img class="owl-lazy" data-src="{{  frontendImage('banner', @$slider->data_values->slider, '990x480') }}" alt="slider-image" width="990" height="480">
                        @endif
                    </a>
                </div>
            @endforeach
        </div>
    </div>
@endif


@push('script')
    <script>
        (function($) {
            "use strict";
            $(".banner-slider").owlCarousel({
                items: 1,
                loop: true,
                autoplay: 1,
                nav: false,
                dots: false,
                lazyLoad: true,
                animateOut: 'fadeOut'
            });
        })(jQuery);
    </script>
@endpush
