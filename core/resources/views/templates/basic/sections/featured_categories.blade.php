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

            <div class="product-wrapper">
                @foreach ($products as $product)
                    <x-dynamic-component :component="frontendComponent('product-card')" :product="$product" :showCartButton="false" />
                @endforeach
            </div>

            @if ($products->hasPages())
                <div class="mt-4 d-sm-block d-flex justify-content-end pagination-wrapper">
                    {{ paginateLinks($products) }}
                </div>
            @endif
        @endif
    </div>
</section>
