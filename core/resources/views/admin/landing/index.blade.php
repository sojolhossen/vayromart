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
                        <div class="row gy-3">
                            <!-- Basic Product Details Section -->
                            <div class="col-12">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-header bg--dark text-white py-2">
                                        <h6 class="mb-0 text-white"><i class="las la-cog"></i> @lang('Basic Product & Pricing Details')</h6>
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="row">
                                            <div class="col-md-4 form-group mb-2">
                                                <label class="fw-bold">@lang('Select Product') <span class="text-danger">*</span></label>
                                                <select name="product_id" id="productId" class="form-control form-control-sm" required>
                                                    <option value="">-- @lang('Choose a product') --</option>
                                                    @foreach($products as $product)
                                                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-2 form-group mb-2">
                                                <label class="fw-bold">@lang('Sale Price (BDT)')</label>
                                                <input type="number" step="any" name="custom_price" id="customPrice" class="form-control form-control-sm" placeholder="e.g. 1200">
                                            </div>
                                            <div class="col-md-2 form-group mb-2">
                                                <label class="fw-bold">@lang('Regular Price')</label>
                                                <input type="number" step="any" name="custom_regular_price" id="customRegularPrice" class="form-control form-control-sm" placeholder="e.g. 1800">
                                            </div>
                                            <div class="col-md-4 form-group mb-2">
                                                <label class="fw-bold">@lang('Delivery Offer')</label>
                                                <select name="free_delivery" id="freeDelivery" class="form-control form-control-sm">
                                                    <option value="paid">Standard / Paid Delivery</option>
                                                    <option value="free">Free Delivery (ফ্রি ডেলিভারি)</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Main Banner Section -->
                            <div class="col-12">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-header bg--dark text-white py-2">
                                        <h6 class="mb-0 text-white"><i class="las la-image"></i> @lang('Main Banner & Product Images')</h6>
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">@lang('Title') <span class="text-danger">*</span></label>
                                            <input type="text" name="title" id="pageTitle" class="form-control form-control-sm" placeholder="e.g. Premium quality bluetooth airbuds" required>
                                        </div>

                                        <!-- Multi Manual Upload -->
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-12 form-group mb-2">
                                                <label class="fw-bold text-dark"><i class="las la-upload"></i> @lang('Upload Manually Product Images (Multiple)')</label>
                                                <input type="file" name="manual_product_images[]" id="manualProductImages" class="form-control form-control-sm" accept="image/*" multiple>
                                                <small class="text-muted">@lang('You can select multiple local images to upload as slider photos.')</small>
                                            </div>
                                        </div>

                                        <!-- Or Add Custom URL -->
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-12 form-group mb-2">
                                                <label class="fw-bold text-dark"><i class="las la-link"></i> @lang('Or Add Single Custom Image URL')</label>
                                                <div class="input-group">
                                                    <input type="url" id="customImageUrl" class="form-control form-control-sm" placeholder="https://example.com/image.jpg">
                                                    <button type="button" class="btn btn-sm btn-dark" id="addCustomUrlBtn"><i class="las la-plus"></i> @lang('Add')</button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Active Images Container -->
                                        <div class="form-group mb-3">
                                            <label class="fw-bold text-success"><i class="las la-images"></i> @lang('Active Slider Images')</label>
                                            <small class="text-muted d-block mb-2">@lang('These images will be displayed as an auto-sliding slideshow on the page. Click the trash icon to remove any.')</small>
                                            <div class="row g-2 row-cols-3 row-cols-sm-6" id="activeProductImagesList" style="min-height: 90px; padding: 10px; border: 2px dashed #ddd; border-radius: 8px; background-color: #fafafa;">
                                                <!-- Thumbnails will dynamically append here -->
                                            </div>
                                            <!-- Hidden input to hold a single fallback image if needed -->
                                            <input type="hidden" name="image_url" id="imageUrl">
                                        </div>

                                        <!-- Product Gallery Images Wrapper -->
                                        <div class="form-group mb-3 d-none animate__animated animate__fadeIn" id="productImagesGalleryWrapper">
                                            <label class="fw-bold text-primary"><i class="las la-images"></i> @lang('Select from Product Gallery Images')</label>
                                            <small class="text-muted d-block mb-2">@lang('Click on any thumbnail below to add it to the active slider images.')</small>
                                            <div class="row g-2 row-cols-3 row-cols-sm-6" id="productImagesGallery" style="max-height: 220px; overflow-y: auto; padding: 5px; border: 1px solid #eee; border-radius: 8px; background-color: #fafafa;">
                                                <!-- Dynamic product thumbnails will load here -->
                                            </div>
                                        </div>

                                        <div class="form-group mb-2">
                                            <label class="fw-bold">@lang('Template')</label>
                                            <select name="template" id="pageTemplate" class="form-control form-control-sm">
                                                <option value="template_1">Template 1 (Original Style)</option>
                                                <option value="template_2" selected>Template 2 (Mohasagor Style)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Hero Content (Headline, Subtitle, Bullets) -->
                            <div class="col-12">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-header bg--dark text-white py-2">
                                        <h6 class="mb-0 text-white"><i class="las la-bullhorn"></i> @lang('Hero Headline & Key Features')</h6>
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="form-group mb-2">
                                            <label class="fw-bold">@lang('Main Catchy Headline')</label>
                                            <input type="text" name="headline" id="pageHeadline" class="form-control form-control-sm" placeholder="e.g. অসাধারণ সাউন্ডের প্রিমিয়াম ইয়ারবাডস!">
                                        </div>
                                        <div class="form-group mb-2">
                                            <label class="fw-bold">@lang('Sub-headline Text')</label>
                                            <input type="text" name="subtitle" id="pageSubtitle" class="form-control form-control-sm" placeholder="e.g. অফুরন্ত চার্জ ব্যাকআপ ও নিখুঁত কলিং ফিচার সহ।">
                                        </div>
                                        <div class="form-group mb-2">
                                            <label class="fw-bold">@lang('Bullet Points / Key Features')</label>
                                            <textarea name="bullets" id="pageBullets" class="form-control form-control-sm" rows="3" placeholder="একটি প্রতি লাইনে লিখুন:&#10;ফাস্ট চার্জিং টেকনোলজি&#10;৭ দিনের রিপ্লেসমেন্ট গ্যারান্টি"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Hotline Section -->
                            <div class="col-12">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-header bg--dark text-white py-2">
                                        <h6 class="mb-0 text-white"><i class="las la-phone-volume"></i> @lang('Hotline')</h6>
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="row">
                                            <div class="col-md-6 form-group mb-2">
                                                <label class="fw-bold">@lang('Title')</label>
                                                <input type="text" name="hotline_title" id="hotlineTitle" class="form-control form-control-sm" value="প্রয়োজনে কল করুন">
                                            </div>
                                            <div class="col-md-6 form-group mb-2">
                                                <label class="fw-bold">@lang('Phone')</label>
                                                <input type="text" name="hotline_phone" id="hotlinePhone" class="form-control form-control-sm" placeholder="01XXXXXXXXX">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Video Section -->
                            <div class="col-12">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-header bg--dark text-white py-2">
                                        <h6 class="mb-0 text-white"><i class="las la-video"></i> @lang('Video')</h6>
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="row">
                                            <div class="col-md-6 form-group mb-2">
                                                <label class="fw-bold">@lang('Video Title')</label>
                                                <input type="text" name="video_title" id="videoTitle" class="form-control form-control-sm" placeholder="Video Title">
                                            </div>
                                            <div class="col-md-6 form-group mb-2">
                                                <label class="fw-bold">@lang('Video URL')</label>
                                                <input type="url" name="video_url" id="videoUrl" class="form-control form-control-sm" placeholder="https://www.youtube.com/watch?v=xxxx">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Why Us Section -->
                            <div class="col-12">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-header bg--dark text-white py-2">
                                        <h6 class="mb-0 text-white"><i class="las la-question-circle"></i> @lang('Why Us')</h6>
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="form-group mb-2">
                                            <label class="fw-bold">@lang('Title')</label>
                                            <input type="text" name="why_us_title" id="whyUsTitle" class="form-control form-control-sm" placeholder="e.g. পণ্যটির বিস্তারিত বিবরণ">
                                        </div>
                                        <div class="form-group mb-2">
                                            <label class="fw-bold">@lang('Description')</label>
                                            <textarea name="why_us_description" id="whyUsDescription" class="form-control nicEdit" rows="5" placeholder="পণ্যটি সম্পর্কে বিস্তারিত বিবরণ লিখুন..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Product Description Dynamic List -->
                            <div class="col-12">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-header bg--dark text-white py-2">
                                        <h6 class="mb-0 text-white"><i class="las la-list-ol"></i> @lang('Product Description (Key Bullet Blocks)')</h6>
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="form-group mb-2">
                                            <label class="fw-bold">@lang('Add Title')</label>
                                            <input type="text" name="product_description_title" id="productDescriptionTitle" class="form-control form-control-sm" value="পণ্যটি কেন আপনার জন্য প্রয়োজনীয়?">
                                        </div>
                                        <div id="dynamicDescriptionsContainer">
                                            <!-- Dynamic rows will be inserted here -->
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline--success addDescRowBtn mt-2">
                                            <i class="las la-plus"></i> @lang('Add Description')
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Customer Review Section -->
                            <div class="col-12">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-header bg--dark text-white py-2">
                                        <h6 class="mb-0 text-white"><i class="las la-star"></i> @lang('Customer Review (Images Upload)')</h6>
                                    </div>
                                    <div class="card-body p-3">
                                        <small class="text-muted d-block mb-3"><i>@lang('(Every image max size 2400 x 2400 px)')</i></small>
                                        <div class="row row-cols-2 row-cols-sm-3 row-cols-md-6 g-3">
                                            @for($i = 1; $i <= 6; $i++)
                                                <div class="col text-center">
                                                    <div class="border rounded p-2 bg-light">
                                                        <label class="fw-bold mb-1 small d-block">@lang('Image') {{ $i }}</label>
                                                        <img id="reviewImgPreview_{{ $i }}" src="https://placehold.co/100x100?text=Review+{{ $i }}" class="img-thumbnail d-block mx-auto mb-2" style="height: 80px; width: 80px; object-fit: cover;">
                                                        <input type="file" name="review_image_{{ $i }}" id="reviewImgFile_{{ $i }}" class="form-control form-control-sm review-img-input" data-index="{{ $i }}" accept="image/*">
                                                    </div>
                                                </div>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Original Template 1 Fields (Hidden / Textarea Description & Original Text Reviews - Collapsible for Backward Compatibility) -->
                            <div class="col-12">
                                <div class="accordion" id="originalFieldsAccordion">
                                    <div class="accordion-item border-0 shadow-sm mb-3">
                                        <h2 class="accordion-header" id="originalFieldsHeading">
                                            <button class="accordion-button collapsed bg-light py-2 text-dark font-weight-bold" type="button" data-bs-toggle="collapse" data-bs-target="#originalFieldsCollapse" aria-expanded="false" aria-controls="originalFieldsCollapse">
                                                <i class="las la-history mr-2"></i> @lang('Original Template 1 Reviews & Description (Optional)')
                                            </button>
                                        </h2>
                                        <div id="originalFieldsCollapse" class="accordion-collapse collapse" aria-labelledby="originalFieldsHeading" data-bs-parent="#originalFieldsAccordion">
                                            <div class="accordion-body bg-white p-3 border-top">
                                                <div class="form-group mb-3">
                                                    <label class="fw-bold">@lang('Original Detailed Description (Template 1)')</label>
                                                    <textarea name="description" id="pageDescription" class="form-control form-control-sm" rows="3" placeholder="পণ্যটির বিস্তারিত বিবরণ এখানে লিখুন (Template 1 এর জন্য)..."></textarea>
                                                </div>
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
                                        </div>
                                    </div>
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

        // Dynamic descriptions logic
        function clearDescriptions() {
            $('#dynamicDescriptionsContainer').empty();
        }

        function addDescriptionRow(val = '') {
            var row = `
            <div class="input-group mb-2 desc-row">
                <input type="text" name="descriptions[]" class="form-control form-control-sm" value="${val}" placeholder="Enter product feature/description line...">
                <button type="button" class="btn btn-sm btn-outline-danger removeDescRowBtn"><i class="las la-trash"></i></button>
            </div>`;
            $('#dynamicDescriptionsContainer').append(row);
        }

        // Live banner image preview
        $('#imageFile').on('change', function() {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    $('#bannerImagePreview').attr('src', e.target.result);
                }
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Live review images previews
        $(document).on('change', '.review-img-input', function() {
            var idx = $(this).data('index');
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    $('#reviewImgPreview_' + idx).attr('src', e.target.result);
                }
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Helper to add active slideshow image
        function addActiveProductImage(imgUrl) {
            if (!imgUrl) return;
            var exists = false;
            $('#activeProductImagesList input[name="existing_product_images[]"]').each(function() {
                if ($(this).val() === imgUrl) {
                    exists = true;
                }
            });
            if (exists) return;
            
            var card = `
                <div class="col active-img-card position-relative" style="max-width: 90px; margin-bottom: 10px;">
                    <img src="${imgUrl}" class="img-thumbnail" style="width: 70px; height: 70px; object-fit: cover;">
                    <input type="hidden" name="existing_product_images[]" value="${imgUrl}">
                    <button type="button" class="btn btn-sm btn-danger remove-active-image-btn" style="position: absolute; top: -5px; right: -5px; padding: 2px 6px; border-radius: 50%; font-size: 10px; line-height: 1;"><i class="las la-times"></i></button>
                </div>
            `;
            $('#activeProductImagesList').append(card);
            
            // Set single fallback URL
            if (!$('#imageUrl').val()) {
                $('#imageUrl').val(imgUrl);
            }
        }

        // Live manual upload files previews
        $('#manualProductImages').on('change', function() {
            if (this.files) {
                for (var i = 0; i < this.files.length; i++) {
                    var file = this.files[i];
                    var localUrl = URL.createObjectURL(file);
                    var card = `
                        <div class="col active-img-card position-relative" style="max-width: 90px; margin-bottom: 10px;">
                            <img src="${localUrl}" class="img-thumbnail" style="width: 70px; height: 70px; object-fit: cover; border: 1px dashed #28a745;">
                            <span class="badge bg-success" style="position: absolute; bottom: 0; left: 0; font-size: 8px; width: 100%; text-align: center; border-radius: 0 0 4px 4px;">Local</span>
                        </div>
                    `;
                    $('#activeProductImagesList').append(card);
                }
            }
        });

        // Add custom URL button click
        $('#addCustomUrlBtn').on('click', function(e) {
            e.preventDefault();
            var customUrl = $('#customImageUrl').val().trim();
            if (customUrl) {
                addActiveProductImage(customUrl);
                $('#customImageUrl').val('');
            }
        });

        // Remove active image click handler
        $(document).on('click', '.remove-active-image-btn', function(e) {
            e.preventDefault();
            $(this).closest('.active-img-card').remove();
            // Sync fallback image input
            var firstImg = $('#activeProductImagesList input[name="existing_product_images[]"]').first().val();
            $('#imageUrl').val(firstImg || '');
        });

        // Fetch product images when a product is selected
        $('#productId').on('change', function() {
            var productId = $(this).val();
            if (!productId) {
                $('#productImagesGalleryWrapper').addClass('d-none');
                $('#productImagesGallery').empty();
                return;
            }
            
            // Fetch images via AJAX
            var url = "{{ route('admin.landing.product.images', '') }}/" + productId;
            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.images && response.images.length > 0) {
                        $('#productImagesGallery').empty();
                        response.images.forEach(function(imgUrl, idx) {
                            var imgCol = `
                                <div class="col text-center">
                                    <img src="${imgUrl}" class="img-thumbnail select-product-image-btn" style="width: 70px; height: 70px; object-fit: cover; cursor: pointer;" data-imgurl="${imgUrl}" title="Click to add to slideshow">
                                </div>
                            `;
                            $('#productImagesGallery').append(imgCol);
                            
                            // Auto add first image (main image) as fallback if list is empty
                            if (idx === 0 && $('#activeProductImagesList').children().length === 0) {
                                addActiveProductImage(imgUrl);
                            }
                        });
                        $('#productImagesGalleryWrapper').removeClass('d-none');
                    } else {
                        $('#productImagesGalleryWrapper').addClass('d-none');
                        $('#productImagesGallery').empty();
                    }
                },
                error: function() {
                    $('#productImagesGalleryWrapper').addClass('d-none');
                    $('#productImagesGallery').empty();
                }
            });
        });

        // Click handler for product image thumbnail selection (add to active slider)
        $(document).on('click', '.select-product-image-btn', function() {
            var imgUrl = $(this).data('imgurl');
            addActiveProductImage(imgUrl);
        });

        // Add/Remove dynamic description row buttons
        $('.addDescRowBtn').on('click', function() {
            addDescriptionRow();
        });
        
        $(document).on('click', '.removeDescRowBtn', function() {
            $(this).closest('.desc-row').remove();
            if ($('#dynamicDescriptionsContainer').children().length === 0) {
                addDescriptionRow();
            }
        });

        // Open Create Modal
        $('.addBtn').on('click', function () {
            $('#modalTitle').text("@lang('Create Landing Page')");
            $('#builderForm').trigger('reset');
            $('#pageId').val('');
            $('#productId').prop('disabled', false);

            clearDescriptions();
            addDescriptionRow(); // Start with one empty row

            // Reset image gallery wrapper
            $('#productImagesGalleryWrapper').addClass('d-none');
            $('#productImagesGallery').empty();
            $('#freeDelivery').val('paid');
            $('#activeProductImagesList').empty();
            $('#manualProductImages').val('');
            $('#customImageUrl').val('');
            for(var i=1; i<=6; i++) {
                $('#reviewImgPreview_' + i).attr('src', 'https://placehold.co/100x100?text=Review+'+i);
            }

            // Reset WYSIWYG editor
            if (typeof nicEditors !== 'undefined') {
                var whyUsEditor = nicEditors.findEditor('whyUsDescription');
                if (whyUsEditor) {
                    whyUsEditor.setContent('');
                }
            }

            $('#builderModal').modal('show');
        });

        // Open Edit Modal & Populate Settings
        $('.editBtn').on('click', function () {
            $('#modalTitle').text("@lang('Edit Landing Page')");
            $('#builderForm').trigger('reset');
            clearDescriptions();

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

                // Populate new fields
                $('#pageTemplate').val(settings.template || 'template_1');
                $('#freeDelivery').val(settings.free_delivery || 'paid');
                $('#hotlineTitle').val(settings.hotline_title || 'প্রয়োজনে কল করুন');
                $('#hotlinePhone').val(settings.hotline_phone || '');
                $('#videoTitle').val(settings.video_title || '');
                $('#whyUsTitle').val(settings.why_us_title || 'পণ্যটির বিস্তারিত বিবরণ');
                $('#productDescriptionTitle').val(settings.product_description_title || 'পণ্যটি কেন আপনার জন্য প্রয়োজনীয়?');

                // Trigger product image gallery loading
                $('#productId').trigger('change');

                // Populate dynamic descriptions
                if (settings.descriptions && settings.descriptions.length > 0) {
                    settings.descriptions.forEach(function(val) {
                        addDescriptionRow(val);
                    });
                } else {
                    addDescriptionRow();
                }

                // Populate review images
                for(var i=1; i<=6; i++) {
                    var previewSrc = 'https://placehold.co/100x100?text=Review+'+i;
                    if (settings.review_images && settings.review_images[i-1]) {
                        previewSrc = settings.review_images[i-1];
                    }
                    $('#reviewImgPreview_' + i).attr('src', previewSrc);
                }

                // Image inputs already loaded inside activeProductImagesList
                $('#imageUrl').val(settings.image_url || '');

                // Set WYSIWYG editor content after a tiny delay so nicEditor has time to initialize
                setTimeout(function() {
                    if (typeof nicEditors !== 'undefined') {
                        var whyUsEditor = nicEditors.findEditor('whyUsDescription');
                        if (whyUsEditor) {
                            whyUsEditor.setContent(settings.why_us_description || '');
                        } else {
                            $('#whyUsDescription').val(settings.why_us_description || '');
                        }
                    } else {
                        $('#whyUsDescription').val(settings.why_us_description || '');
                    }
                }, 100);
            }

            $('#builderModal').modal('show');
        });

        // Save nicEditor contents before submitting
        $('#builderForm').on('submit', function() {
            if (typeof nicEditors !== 'undefined') {
                $(".nicEdit").each(function(index, element) {
                    var editor = nicEditors.findEditor(element);
                    if (editor) {
                        editor.saveContent();
                    }
                });
            }
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
