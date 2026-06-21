<a href="{{ $category->shopLink() }}" class="d-block text-center">
    <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="{{ $category->categoryImage() }}" class="w-100 lazyload owl-lazy" alt="category" width="120" height="120">
    <span class="title line-limitation-1">{{ __($category->name) }}</span>
</a>
