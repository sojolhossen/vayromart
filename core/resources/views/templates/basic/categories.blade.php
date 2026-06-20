@extends('Template::layouts.master')

@section('content')
    @php
        $allCats = collect();
        $flatten = function($category) use (&$flatten, &$allCats) {
            $allCats->push($category);
            foreach ($category->allSubcategories as $sub) {
                $flatten($sub);
            }
        };
        foreach ($categories as $cat) {
            $flatten($cat);
        }
    @endphp

    <div class="pt-60 pb-60">
        <div class="container">
            <div class="categories-grid-wrapper">
                @forelse ($allCats as $category)
                    <a href="{{ $category->shopLink() }}" class="cat-grid-card">
                        <div class="cat-grid-card__img-wrap">
                            <img src="{{ getImage(null) }}"
                                 data-src="{{ $category->categoryImage() }}"
                                 class="lazyload cat-grid-card__img"
                                 alt="{{ __($category->name) }}">
                        </div>
                        <div class="cat-grid-card__body">
                            <h6 class="cat-grid-card__name">{{ __($category->name) }}</h6>
                        </div>
                    </a>
                @empty
                    <div class="col-12">
                        <x-dynamic-component :component="frontendComponent('empty-message')" message="No category found" />
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection

@push('style-lib')
    <link rel="stylesheet" href="{{ asset($activeTemplateTrue . 'css/categories-page.css') }}">
@endpush
