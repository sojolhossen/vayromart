@php
    $limit = gs('homepage_products_limit') ?? 20;
    $products = \App\Models\Product::published()->with(['brand:id,name', 'productVariants', 'displayImage', 'activeOffer'])->orderByRaw('SHA1(id) DESC')->paginate($limit);
@endphp

<section class="my-60">
    <div class="container">
        @if ($products->count())
            <div class="section-header left-style">
                <h5 class="title">{{ __('Our Products') }}</h5>
            </div>

            <div class="product-wrapper" id="homepage-products-wrapper">
                @foreach ($products as $product)
                    <x-dynamic-component :component="frontendComponent('product-card')" :product="$product" :showCartButton="false" />
                @endforeach
            </div>

            <div class="text-center mt-4 d-none" id="infinite-scroll-loader">
                <div class="spinner-border text--base" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        @endif
    </div>
</section>

@push('script')
<script>
    (function ($) {
        "use strict";

        let page = 2;
        let loading = false;
        let hasMore = {{ $products->hasMorePages() ? 'true' : 'false' }};
        const wrapper = $('#homepage-products-wrapper');
        const loader = $('#infinite-scroll-loader');

        if (hasMore) {
            $(window).on('scroll', function () {
                if (loading || !hasMore) return;

                if ($(window).scrollTop() + $(window).height() >= $(document).height() - 800) {
                    loadMoreProducts();
                }
            });
        }

        function loadMoreProducts() {
            loading = true;
            loader.removeClass('d-none');

            $.ajax({
                url: "{{ route('home') }}",
                type: 'GET',
                data: { page: page, scroll_home: 1 },
                success: function (response) {
                    if (response.html) {
                        wrapper.append(response.html);
                        page++;
                        hasMore = response.hasMore;
                        if (typeof lazyload === 'function') {
                            lazyload();
                        }
                    } else {
                        hasMore = false;
                    }
                },
                error: function () {
                    hasMore = false;
                },
                complete: function () {
                    loading = false;
                    loader.addClass('d-none');
                }
            });
        }
    })(jQuery);
</script>
@endpush
