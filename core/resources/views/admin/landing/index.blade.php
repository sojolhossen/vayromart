@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <!-- History / Generated List Card -->
        <div class="col-lg-12 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg--dark d-flex align-items-center justify-content-between">
                    <h5 class="text-white card-title mb-0"><i class="las la-history"></i> @lang('Landing Pages History')</h5>
                    <button type="button" class="btn btn-sm btn--primary addBtn">
                        <i class="las la-plus"></i> @lang('Add Landing Page')
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive--md table-responsive">
                        <table class="table table--light style--two">
                            <thead>
                                <tr>
                                    <th>@lang('Product Name')</th>
                                    <th>@lang('Landing Page Title')</th>
                                    <th>@lang('URL / Live Link')</th>
                                    <th>@lang('Created At')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($landingPages as $page)
                                    <tr>
                                        <td>
                                            <span class="fw-bold">{{ $page->product->name ?? 'N/A' }}</span>
                                        </td>
                                        <td>
                                            <span>{{ $page->title }}</span>
                                        </td>
                                        <td>
                                            <a href="{{ route('landing.view', $page->slug) }}" target="_blank" class="text--primary fw-bold">
                                                /landing/{{ $page->slug }} <i class="las la-external-link-alt"></i>
                                            </a>
                                        </td>
                                        <td>
                                            {{ showDateTime($page->created_at) }}<br>
                                            <small class="text-muted">{{ diffForHumans($page->created_at) }}</small>
                                        </td>
                                        <td>
                                            <div class="button--group">
                                                <button type="button" class="btn btn-sm btn-outline--primary editBtn" 
                                                        data-id="{{ $page->id }}" 
                                                        data-settings="{{ json_encode($page->design_settings) }}">
                                                    <i class="las la-pen"></i> @lang('Edit')
                                                </button>
                                                <a href="{{ route('landing.view', $page->slug) }}" target="_blank" class="btn btn-sm btn-outline--info">
                                                    <i class="las la-eye"></i> @lang('View')
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline--danger deleteBtn" data-id="{{ $page->id }}" data-product="{{ $page->product->name ?? 'N/A' }}">
                                                    <i class="las la-trash"></i> @lang('Delete')
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage ?? 'No landing pages created yet') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($landingPages->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($landingPages) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Manual Builder Form Modal -->
    <div id="builderModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg--dark">
                    <h5 class="modal-title text-white" id="modalTitle">@lang('Create Landing Page')</h5>
                    <button type="button" class="close text-white border-0 bg-transparent" data-bs-dismiss="modal" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form action="{{ route('admin.landing.generate') }}" method="POST" id="builderForm" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="id" id="pageId">
                    <div class="modal-body">
                        <div class="row">
                            <!-- Left column -->
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="fw-bold">@lang('Select Product') <span class="text-danger">*</span></label>
                                    <select name="product_id" id="productId" class="form-control" required>
                                        <option value="">-- @lang('Choose a product') --</option>
                                        @foreach($products as $product)
                                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">@lang('Select the product this landing page is for.')</small>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="fw-bold">@lang('Page Tab Title / Meta Title') <span class="text-danger">*</span></label>
                                    <input type="text" name="title" id="pageTitle" class="form-control" placeholder="e.g. Premium quality bluetooth airbuds - Vayromart" required>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="fw-bold">@lang('Custom Sale Price (BDT)')</label>
                                    <input type="number" step="any" name="custom_price" id="customPrice" class="form-control" placeholder="e.g. 1200">
                                    <small class="text-muted">@lang('Leave blank to use default product sale price.')</small>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="fw-bold">@lang('Custom Regular Price (BDT)')</label>
                                    <input type="number" step="any" name="custom_regular_price" id="customRegularPrice" class="form-control" placeholder="e.g. 1800">
                                    <small class="text-muted">@lang('Leave blank to use default regular price.')</small>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="fw-bold">@lang('Main Catchy Headline') <span class="text-danger">*</span></label>
                                    <input type="text" name="headline" id="pageHeadline" class="form-control" placeholder="e.g. অসাধারণ সাউন্ডের প্রিমিয়াম ইয়ারবাডস!" required>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="fw-bold">@lang('Sub-headline Text') <span class="text-danger">*</span></label>
                                    <input type="text" name="subtitle" id="pageSubtitle" class="form-control" placeholder="e.g. অফুরন্ত চার্জ ব্যাকআপ ও নিখুঁত কলিং ফিচার সহ।" required>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="fw-bold">@lang('Upload Product Banner Image')</label>
                                    <input type="file" name="image_file" id="imageFile" class="form-control" accept="image/*">
                                    <small class="text-muted">@lang('Optional. Upload custom banner image file.')</small>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="fw-bold">@lang('Or Custom Banner Image URL')</label>
                                    <input type="url" name="image_url" id="imageUrl" class="form-control" placeholder="https://example.com/image.jpg">
                                    <small class="text-muted">@lang('Alternatively paste image link.')</small>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="fw-bold">@lang('YouTube Video URL')</label>
                                    <input type="url" name="video_url" id="videoUrl" class="form-control" placeholder="https://www.youtube.com/watch?v=xxxx">
                                    <small class="text-muted">@lang('Optional. Paste a YouTube link to show a product video.')</small>
                                </div>
                            </div>

                            <!-- Right column -->
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="fw-bold">@lang('Bullet Points / Key Features') <span class="text-danger">*</span></label>
                                    <textarea name="bullets" id="pageBullets" class="form-control" rows="4" placeholder="একটি প্রতি লাইনে লিখুন:&#10;ফাস্ট চার্জিং টেকনোলজি&#10;৭ দিনের রিপ্লেসমেন্ট গ্যারান্টি&#10;ক্রিস্টাল ক্লিয়ার সাউন্ড কোয়ালিটি" required></textarea>
                                    <small class="text-muted">@lang('Enter each selling point on a new line.')</small>
                                </div>

                                <div class="form-group mb-3">
                                    <label class="fw-bold">@lang('Detailed Description / Review') <span class="text-danger">*</span></label>
                                    <textarea name="description" id="pageDescription" class="form-control" rows="8" placeholder="পণ্যটির বিস্তারিত বিবরণ এখানে লিখুন..." required></textarea>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">
                        <h6 class="mb-3 text--primary"><i class="las la-star"></i> @lang('Customer Reviews (Optional Customization)')</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group mb-2">
                                    <label class="small fw-bold">@lang('Reviewer 1 Name')</label>
                                    <input type="text" name="reviewer_name_1" id="revName1" class="form-control form-control-sm" placeholder="Sojol Hossen">
                                </div>
                                <div class="form-group mb-2">
                                    <label class="small fw-bold">@lang('Reviewer 1 Comment')</label>
                                    <textarea name="reviewer_comment_1" id="revComment1" class="form-control form-control-sm" rows="2" placeholder="অসাধারণ প্রোডাক্ট!"></textarea>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-2">
                                    <label class="small fw-bold">@lang('Reviewer 2 Name')</label>
                                    <input type="text" name="reviewer_name_2" id="revName2" class="form-control form-control-sm" placeholder="Farhana Yasmin">
                                </div>
                                <div class="form-group mb-2">
                                    <label class="small fw-bold">@lang('Reviewer 2 Comment')</label>
                                    <textarea name="reviewer_comment_2" id="revComment2" class="form-control form-control-sm" rows="2" placeholder="পণ্যটির কোয়ালিটি খুবই ভালো।"></textarea>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-2">
                                    <label class="small fw-bold">@lang('Reviewer 3 Name')</label>
                                    <input type="text" name="reviewer_name_3" id="revName3" class="form-control form-control-sm" placeholder="Md. Arif">
                                </div>
                                <div class="form-group mb-2">
                                    <label class="small fw-bold">@lang('Reviewer 3 Comment')</label>
                                    <textarea name="reviewer_comment_3" id="revComment3" class="form-control form-control-sm" rows="2" placeholder="ক্যাশ অন ডেলিভারি পেয়ে ভালো লেগেছে।"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Close')</button>
                        <button type="submit" class="btn btn--primary" id="saveBtn">@lang('Save Landing Page')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Delete Landing Page')</h5>
                    <button type="button" class="close border-0 bg-transparent" data-bs-dismiss="modal" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form action="" method="POST" id="deleteForm">
                    @csrf
                    <div class="modal-body">
                        <p>@lang('Are you sure you want to delete the landing page for') <strong id="deleteProductName"></strong>?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Cancel')</button>
                        <button type="submit" class="btn btn--danger">@lang('Delete')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script')
<script>
    (function ($) {
        "use strict";

        // Open Create Modal
        $('.addBtn').on('click', function () {
            $('#modalTitle').text("@lang('Create Landing Page')");
            $('#builderForm').trigger('reset');
            $('#pageId').val('');
            $('#productId').prop('disabled', false);
            $('#builderModal').modal('show');
        });

        // Open Edit Modal & Populate Settings
        $('.editBtn').on('click', function () {
            $('#modalTitle').text("@lang('Edit Landing Page')");
            $('#builderForm').trigger('reset');

            var id = $(this).data('id');
            var settings = $(this).data('settings');

            $('#pageId').val(id);
            if (settings) {
                $('#productId').val(settings.product_id);
                $('#pageTitle').val(settings.title);
                $('#pageHeadline').val(settings.headline);
                $('#pageSubtitle').val(settings.subtitle);
                $('#customPrice').val(settings.custom_price);
                $('#customRegularPrice').val(settings.custom_regular_price);
                $('#imageUrl').val(settings.image_url);
                $('#videoUrl').val(settings.video_url);
                $('#pageBullets').val(settings.bullets);
                $('#pageDescription').val(settings.description);
                $('#revName1').val(settings.reviewer_name_1);
                $('#revComment1').val(settings.reviewer_comment_1);
                $('#revName2').val(settings.reviewer_name_2);
                $('#revComment2').val(settings.reviewer_comment_2);
                $('#revName3').val(settings.reviewer_name_3);
                $('#revComment3').val(settings.reviewer_comment_3);
            }

            $('#builderModal').modal('show');
        });

        // Delete Modal
        $('.deleteBtn').on('click', function () {
            var id = $(this).data('id');
            var productName = $(this).data('product');
            var actionUrl = "{{ route('admin.landing.delete', '') }}/" + id;
            
            $('#deleteProductName').text(productName);
            $('#deleteForm').attr('action', actionUrl);
            $('#deleteModal').modal('show');
        });

    })(jQuery);
</script>
@endpush
