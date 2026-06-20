@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card b-radius--10">
                <div class="card-body p-0">
                    <div class="table-responsive--md table-responsive">
                        <table class="table--light style--two table">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAllProducts" class="form-check-input">
                                    </th>
                                    <th>@lang('Product')</th>
                                    <th>@lang('Price')</th>
                                    <th>@lang('Downloadable')</th>
                                    <th>@lang('Is Published')</th>
                                    <th>@lang('In Stock')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($products as $product)
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="product-checkbox form-check-input" value="{{ $product->id }}">
                                        </td>
                                        <td>
                                            <!-- Product Card -->
                                            <div class="d-flex flex-wrap flex-md-nowrap gap-2 justify-content-lg-start justify-content-end">
                                                <!-- Product Image -->
                                                <div class="table-thumb">
                                                    <a href="{{ $product->mainImage() }}" class="image-popup">
                                                        <img src="{{ $product->mainImage() }}" alt="@lang('image')">
                                                    </a>
                                                </div>

                                                <!-- Product Info -->
                                                <div>
                                                    <!-- Product Name -->
                                                    <span class="d-block"> {{ strLimit($product->name, 40) }}</span>

                                                    <!-- Product Brand -->
                                                    <small class="text-muted d-block">
                                                        @lang('Brand'): @if ($product->brand)
                                                            {{ __($product->brand->name) }}
                                                        @else
                                                            @lang('Non Brand')
                                                        @endif
                                                    </small>

                                                    <!-- Product Categories -->
                                                    <small class="text-muted d-block category-wrapper">
                                                        @lang('Categories'): {{ $product->showCategories(3) }}
                                                    </small>

                                                    @if ($product->deleted_at == null)
                                                        <a href="{{ $product->link() }}" target="_blank" class="text-muted small color--small text-decoration-underline">@lang('View in shop')</a>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>

                                        <td>@php echo $product->formattedPrice() @endphp</td>

                                        <td>{{ yn($product->is_downloadable) }}</td>

                                        <td>
                                            <x-toggle-switch class="change_publish_status" :checked="$product->is_published" data-id="{{ $product->id }}" />
                                        </td>

                                        <td>
                                            <div class="fixed-height-cell">
                                                @php echo $product->detailedStock(); @endphp
                                            </div>
                                        </td>

                                        <td>
                                            <div class="d-flex flex-wrap flex-md-nowrap justify-content-end gap-1">
                                                @if ($trashed)
                                                    <button type="button" class="btn btn-sm btn-outline--success confirmationBtn flex-shrink-0" data-action="{{ route('admin.products.delete', $product->id) }}" data-question="@lang('Are you sure to restore this product?')">
                                                        <i class="las la-trash-restore"></i>@lang('Restore')
                                                    </button>
                                                @else
                                                    <a href="{{ $product->editUrl() }}" class="btn btn-outline--primary flex-shrink-0">
                                                        <i class="la la-pencil"></i>@lang('Edit')
                                                    </a>

                                                    <form method="post" action="{{ route('admin.products.duplicate', $product->id) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline--dark"><i class="la la-copy"></i>@lang('Duplicate')</button>
                                                    </form>


                                                    <button type="button" class="btn btn-outline--danger confirmationBtn flex-shrink-0" data-question="@lang('Are you sure to delete this product?')" data-action="{{ route('admin.products.delete', $product->id) }}">
                                                        <i class="las la-trash"></i>@lang('Delete')
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($products->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($products) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <x-confirmation-modal />

    @if (!$trashed)
        <!-- Bulk Category Modal -->
        <div class="modal fade" id="bulkCategoryModal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">@lang('Bulk Update Category')</h5>
                        <button type="button" class="close btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="{{ route('admin.products.bulk.category.update') }}" method="POST" id="bulkCategoryForm">
                        @csrf
                        <div id="selectedProductsContainer"></div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label class="required font-weight-bold">@lang('Select Categories')</label>
                                <div class="border p-3 rounded" style="max-height: 250px; overflow-y: auto; background: #fdfdfd;">
                                    @php
                                        $categories = App\Models\Category::with('allSubcategories')->isParent()->get();
                                    @endphp
                                    <x-category-checkbox :categories="$categories" />
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Close')</button>
                            <button type="submit" class="btn btn--primary">@lang('Update')</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if (Route::is('admin.products.all'))
        <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasRight" aria-labelledby="offcanvasRightLabel">
            <div class="offcanvas-header">
                <h5 id="offcanvasRightLabel">@lang('Filter Products')</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <form action="" method="GET" id="filterForm">
                    <div class="form-group">
                        <label>@lang('Product Specification')</label>
                        <select name="product_type_id" class="form-control select2">
                            <option value="">@lang('Select One')</option>
                            @foreach (App\Models\ProductType::get() as $productType)
                                <option value="{{ $productType->id }}" @selected(request()->product_type_id == $productType->id)>
                                    {{ __($productType->name) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>@lang('Brand')</label>
                        <select name="brand_id" class="form-control select2">
                            <option value="">@lang('Select One')</option>
                            @foreach (App\Models\Brand::get() as $brand)
                                <option value="1" @selected(request()->brand_id == $brand->id)>{{ __($brand->name) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>@lang('Category')</label>
                        <select class="form-control" name="category" id="categoryDropdown">
                            <option value="">@lang('Select One')</option>
                            <x-category-options :isAdmin="true" />
                        </select>
                    </div>

                    <div class="form-group">
                        <label>@lang('Is Published')</label>
                        <select name="is_published" class="form-control select2" data-minimum-results-for-search="-1">
                            <option value="" selected>@lang('Select One')</option>
                            <option value="1" @selected(request()->is_published === '1')>@lang('Yes')</option>
                            <option value="0" @selected(request()->is_published === '0')>@lang('No')</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>@lang('Product Type')</label>
                        <select name="product_type" class="form-control select2" data-minimum-results-for-search="-1">
                            <option value="" selected>@lang('Select One')</option>
                            <option value="{{ Status::PRODUCT_TYPE_SIMPLE }}" @selected(request()->product_type == Status::PRODUCT_TYPE_SIMPLE)>
                                @lang('Simple Product')</option>
                            <option value="{{ Status::PRODUCT_TYPE_VARIABLE }}" @selected(request()->product_type == Status::PRODUCT_TYPE_VARIABLE)>
                                @lang('Variable Product')</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>@lang('Shown In Product Page')</label>
                        <select name="show_in_products_page" class="form-control select2" data-minimum-results-for-search="-1">
                            <option value="" selected>@lang('Select One')</option>
                            <option value="1" @selected(request()->show_in_products_page === '1')>@lang('Yes')</option>
                            <option value="0" @selected(request()->show_in_products_page === '0')>@lang('No')</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>@lang('Sort By')</label>
                        <select name="sort_by"class="form-control select2" data-minimum-results-for-search="-1">
                            <option value="" selected>@lang('Default') </option>
                            <option value="price_htl" @selected(request()->sort_by == 'price_htl')>@lang('Price Hight to Low') </option>
                            <option value="price_lth" @selected(request()->sort_by == 'price_lth')>@lang('Price Low to High') </option>
                            <option value="latest" @selected(request()->sort_by == 'latest')>@lang('Latest') </option>
                            <option value="oldest" @selected(request()->sort_by == 'oldest')>@lang('Oldest') </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>@lang('Is Downloadable')</label>
                        <select name="is_downloadable" class="form-control select2" data-minimum-results-for-search="-1">
                            <option value="" selected>@lang('Select One')</option>
                            <option value="1" @selected(request()->is_downloadable === '1')>@lang('Yes')</option>
                            <option value="0" @selected(request()->is_downloadable === '0')>@lang('No')</option>
                        </select>
                    </div>

                </form>
            </div>

            <div class="position-sticky p-3">
                <button type="submit" class="btn btn--primary w-100 h-45" form="filterForm">@lang('Apply Filter')</button>
            </div>
        </div>
    @endif
@endsection

@push('breadcrumb-plugins')
    <x-search-form></x-search-form>
    @if (!$trashed)
        <button class="btn btn--warning d-none" id="bulkCategoryBtn" type="button"><i class="las la-tags"></i> @lang('Bulk Category')</button>
    @endif
    @if (Route::is('admin.products.all'))
        <button class="btn btn--primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight" aria-controls="offcanvasRight"><i class="las la-sliders-h"></i>@lang('Filter')</button>
    @endif
@endpush

@push('style')
    <style>
        .fixed-height-cell {
            height: 70px;
            overflow: auto;
            display: inline-flex;
            align-items: center;
            flex-direction: column;
        }

        .category-wrapper {
            max-width: 500px;
            width: 100%;
            white-space: normal;
        }
    </style>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";


            $('.change_publish_status').on('change', function() {
                var id = $(this).data('id');

                var data = {
                    _token: `{{ csrf_token() }}`,
                };

                $.ajax({
                    url: `{{ route('admin.products.publish.status', '') }}/${$(this).data('id')}`,
                    method: 'POST',
                    data: data,
                    success: function(response) {
                        notify(response.status, response.message);
                    }
                });
            });

            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                let url = new URL(window.location.href);
                let searchParams = url.searchParams;
                let fields = document.querySelector('#filterForm').elements;

                Array.from(fields).forEach(field => {
                    if (field.name) {
                        if (field.value) {
                            searchParams.set(field.name, field.value);
                        } else {
                            searchParams.delete(field.name);
                        }
                    }
                });

                window.location.href = cleanUpAndDecodeURL(url.toString());
            });

            function cleanUpAndDecodeURL(url) {
                let parsedURL = new URL(url);
                let params = parsedURL.searchParams;
                let cleanedParams = new URLSearchParams();

                params.forEach((value, key) => {
                    if (value) {
                        cleanedParams.append(key, value);
                    }
                });

                let cleanedURL = `${parsedURL.origin}${parsedURL.pathname}`;
                if (cleanedParams.toString()) {
                    cleanedURL +=
                        `?${decodeURIComponent(cleanedParams.toString()).replace(/%5B/g, '[').replace(/%5D/g, ']')}`;
                }

                return cleanedURL;
            }


            $(".exportBtn").on('click', function(e) {
                let modal = $("#exportModal");
                modal.modal('show')
            });

            // Handle select all checkbox in table header
            $('#selectAllProducts').on('click', function() {
                $('.product-checkbox').prop('checked', $(this).prop('checked'));
                toggleBulkBtn();
            });

            // Handle individual checkboxes
            $(document).on('change', '.product-checkbox', function() {
                var allChecked = $('.product-checkbox:checked').length === $('.product-checkbox').length;
                $('#selectAllProducts').prop('checked', allChecked);
                toggleBulkBtn();
            });

            // Toggle bulk action button visibility
            function toggleBulkBtn() {
                var checkedCount = $('.product-checkbox:checked').length;
                if (checkedCount > 0) {
                    $('#bulkCategoryBtn').removeClass('d-none');
                } else {
                    $('#bulkCategoryBtn').addClass('d-none');
                }
            }

            // Click bulk category update button
            $('#bulkCategoryBtn').on('click', function() {
                var modal = $('#bulkCategoryModal');
                var container = $('#selectedProductsContainer');
                container.empty();
                
                // Add hidden inputs for each selected product
                $('.product-checkbox:checked').each(function() {
                    container.append(`<input type="hidden" name="product_ids[]" value="${$(this).val()}">`);
                });

                modal.modal('show');
            });

        })(jQuery);
    </script>
@endpush
