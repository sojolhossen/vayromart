@php
    $content = getContent('recent_viewed.content', true);
@endphp

<div class="recently-viewed-section mt-60 mb-60 d-none">
    <div class="container">
        <div class="section-header left-style">
            <h5 class="title">{{ @$content->data_values?->title }}</h5>
        </div>
        <div class="offer-wrapper">
            <div class="recent-view owl-carousel owl-theme"></div>
        </div>
    </div>
</div>

@push('script')
    <script>
        (function($) {
            "use strict";


            function showRecentlyViewed() {
                const config = {
                    days: {{ gs('recently_viewed_days') }},
                    limit: {{ gs('recently_viewed_items') }}
                };

                let viewedProducts = JSON.parse(localStorage.getItem("recentlyViewed")) || [];
                let recentViewSection = $(".recently-viewed-section");
                let recentViewContainer = $(".recent-view");
                let now = new Date().getTime();
                let maxAge = config.days * 24 * 60 * 60 * 1000;

                recentViewContainer.empty();

                // Filter by age
                viewedProducts = viewedProducts.filter(product => now - product.date < maxAge);

                // Limit number of items
                viewedProducts = viewedProducts.slice(0, config.limit);

                if (viewedProducts.length > 0) {
                    recentViewSection.removeClass('d-none');
                    viewedProducts.forEach(function(product) {
                        let productHtml = `
                        <div class="product-card">
                            <div class="product-thumb">
                                <a href="${product.plink}">
                                    <img src="${product.pima}" alt="${product.pna}" width="200" height="200">
                                </a>
                            </div>
                            <div class="product-content">
                                <h6 class="title">
                                    <a href="${product.plink}">${product.pna}</a>
                                </h6>
                            </div>
                        </div>
                    `;
                        recentViewContainer.append(productHtml);
                    });

                    recentViewContainer.owlCarousel({
                        margin: 0,
                        responsiveClass: true,
                        nav: false,
                        dots: false,
                        autoplay: true,
                        autoplayTimeout: 3000,
                        autoplayHoverPause: true,
                        loop: viewedProducts.length > 5,
                        responsive: {
                            0: {
                                items: 2,
                            },
                            576: {
                                items: 3,
                            },
                            768: {
                                items: 4,
                            },
                            992: {
                                items: 5,
                            }
                        }
                    });

                    localStorage.setItem("recentlyViewed", JSON.stringify(viewedProducts));
                }
            }

            showRecentlyViewed();
        })(jQuery);
    </script>
@endpush
