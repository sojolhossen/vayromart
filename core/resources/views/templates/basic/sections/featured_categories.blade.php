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

            <div class="text-center mt-4 d-none" id="load-more-btn-wrapper">
                <button type="button" class="btn btn--base" id="load-more-btn">@lang('Load More')</button>
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
        const loadMoreBtn = $('#load-more-btn');
        const loadMoreBtnWrapper = $('#load-more-btn-wrapper');

        if (hasMore) {
            loadMoreBtnWrapper.removeClass('d-none');
            loadMoreBtn.on('click', function () {
                if (loading || !hasMore) return;
                loadMoreProducts();
            });
        }

        function loadMoreProducts() {
            loading = true;
            loadMoreBtn.prop('disabled', true).text('Loading...');

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
                        if (!hasMore) {
                            loadMoreBtnWrapper.addClass('d-none');
                        }
                    } else {
                        hasMore = false;
                        loadMoreBtnWrapper.addClass('d-none');
                    }
                },
                error: function () {
                    hasMore = false;
                    loadMoreBtnWrapper.addClass('d-none');
                },
                complete: function () {
                    loading = false;
                    loadMoreBtn.prop('disabled', false).text('Load More');
                }
            });
        }
    })(jQuery);
</script>
@endpush
