@extends('admin.layouts.app')
@section('panel')
    <form method="post" action="{{ route('admin.media.upload') }}" enctype="multipart/form-data" id="mediaUploadForm" class="mb-3">
        @csrf
        <div class="mb-3 text-end">
            <button type="button" class="btn btn--dark uploaderCancelBtn">@lang('Delete All')</button>
            <button type="submit" class="btn btn--primary uploaderUploadButton">@lang('Upload')</button>
        </div>
        <div class="input-images"></div>
    </form>

    <div class="card b-radius--10 mb-4 box--shadow1">
        <div class="card-body">
            <h5 class="card-title border-bottom pb-2">@lang('WebP Image Optimization Settings')</h5>
            <div class="row align-items-center justify-content-between">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="d-flex align-items-center">
                        <div class="form-check form-switch me-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="webpAutoConvertToggle" style="width: 2.5em; height: 1.25em;" {{ gs('webp_auto_convert') ? 'checked' : '' }}>
                        </div>
                        <div>
                            <label class="form-check-label fw-bold mb-0" for="webpAutoConvertToggle" style="cursor: pointer;">@lang('Auto-Convert New Uploads to WebP')</label>
                            <p class="text-muted small mb-0">@lang('When enabled, all newly uploaded PNG, JPG, and JPEG files will be automatically optimized and converted to WebP format.')</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 text-md-end">
                    <button type="button" class="btn btn--success" id="bulkConvertWebpBtn">
                        <i class="las la-compress-arrows-alt"></i> @lang('Convert All Existing Images to WebP')
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <a href="{{ request()->fullUrlWithQuery(['status' => null]) }}" class="btn btn-sm btn-outline--primary @if(!request()->status) active @endif">@lang('All')</a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'used']) }}" class="btn btn-sm btn-outline--success @if(request()->status == 'used') active @endif">@lang('Used')</a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'unused']) }}" class="btn btn-sm btn-outline--danger @if(request()->status == 'unused') active @endif">@lang('Unused')</a>
            
            <button class="btn btn-sm btn--danger confirmationBtn" type="button" data-question="@lang('Are you sure to delete all unused files permanently?')" data-action="{{ route('admin.media.delete.unused') }}"><i class="las la-trash-alt"></i> @lang('Delete All Unused')</button>
        </div>

        <div class="d-flex gap-3 align-items-center">
            <select name="order_by" class="form-select w-auto">
                <option value="id::desc" @selected(request()->order_by == 'id::desc')>@lang('Newest')</option>
                <option value="id::asc" @selected(request()->order_by == 'id::asc')>@lang('Oldest')</option>
                <option value="file_name::asc" @selected(request()->order_by == 'name::asc')>@lang('Name A-Z')</option>
                <option value="file_name::desc" @selected(request()->order_by == 'name::desc')>@lang('Name Z-A')</option>
            </select>
            <x-search-form></x-search-form>
        </div>
    </div>

    <div class="media-content">
        @foreach ($mediaFiles as $file)
            @php
                $totalUses = $file->products_count + $file->product_images_count + $file->product_variants_count + $file->product_variant_images_count;
                $isUsed = $totalUses > 0;
            @endphp
            <div class="media-item">
                <span class="badge {{ $isUsed ? 'badge--success' : 'badge--danger' }} position-absolute top-0 start-0 m-2" style="z-index: 2;">
                    {{ $isUsed ? trans('Used') : trans('Unused') }}
                </span>

                <div class="dropdown ">
                    <button class="media-button" type="button" id="dropdown-id-{{ $file->id }}" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="la la-ellipsis-v"></i>
                    </button>

                    @php
                        $fileInfo = getFileInfoViaFullPath($file->full_url);
                        $fileInfo->id = $file->id;
                        $fileInfo->created_at = showDateTime($file->created_at);
                        $fileInfo->total_uses = $totalUses;
                    @endphp

                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdown-id-{{ $file->id }}" data-bs-popper="static">
                        <button type="button" class="dropdown-item showFileDetails" data-bs-toggle="offcanvas" data-bs-target="#fileDetailsCanvas" data-info='@json($fileInfo)'>
                            <i class="la la-info-circle me-1"></i> @lang('Details')
                        </button>

                        <a href="{{ $file->full_url }}" class="dropdown-item" download><i class="la la-download me-1"></i> @lang('Download')</a>

                        <button class="dropdown-item confirmationBtn" data-question="@lang('Are you sure to delete this file permanently?')" data-action="{{ route('admin.media.delete', $file->id) }}"><i class="la la-trash me-1"></i> @lang('Delete')</button>
                    </div>
                </div>
                <div class="media">
                    <img src="{{ $file->thumb_url }}" class="card-img-top" alt="image">
                </div>

                <div class="content">
                    @if (request()->has('show_id'))
                        <small class="fw-semibold">@lang('ID'): {{ $file->id }}</small>
                    @endif
                    <span class="file-name" title="{{ $file->file_name }}">{{ shortenFileName($file->full_url, 20) }}</span>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <span class="file-size">{{ $fileInfo->formatted_size }}</span>
                        <span class="badge badge--dark text-uppercase" style="font-size: 0.7rem; padding: 0.15rem 0.35rem; line-height: 1;">
                            {{ $fileInfo->extension ?? 'Unknown' }}
                        </span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($mediaFiles->hasPages())
        <div class="py-4">
            {{ paginateLinks($mediaFiles) }}
        </div>
    @endif

    <x-confirmation-modal />

    <div id="webpConvertModal" class="modal fade" tabindex="-1" role="dialog" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('WebP Image Conversion Progress')</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="webpModalCloseBtn" style="border: none; background: transparent; font-size: 1.5rem;"><i class="las la-times"></i></button>
                </div>
                <div class="modal-body">
                    <!-- Confirmation Section -->
                    <div id="webpConfirmSection">
                        <p class="mb-4">@lang('Are you sure you want to convert all existing JPG, JPEG, and PNG images to WebP? This will optimize all images, delete the original files, and update database entries.')</p>
                        <div class="text-end">
                            <button type="button" class="btn btn--dark me-2" data-bs-dismiss="modal">@lang('No')</button>
                            <button type="button" class="btn btn--primary" id="confirmWebpConvertBtn">@lang('Yes, Convert All')</button>
                        </div>
                    </div>

                    <!-- Progress Section -->
                    <div id="webpProgressSection" class="d-none">
                        <div class="mb-3 d-flex justify-content-between">
                            <span class="fw-bold">@lang('Status'): <span id="webpConvertStatus" class="fw-normal">@lang('Initializing...')</span></span>
                        </div>
                        <div class="progress mb-3 shadow-sm" style="height: 22px; border-radius: 6px; overflow: hidden;">
                            <div id="webpProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg--success fw-bold" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                        </div>
                        <div class="small border p-2 bg-light rounded" id="webpConvertFilename" style="word-break: break-all; max-height: 120px; overflow-y: auto; font-family: monospace;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="fileDetailsCanvas" aria-labelledby="fileDetailsLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="fileDetailsLabel">@lang('File Details')</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="list-group list-group-flush">
                <li class="list-group-item px-0 d-flex flex-column"><strong>@lang('ID')</strong> <span id="file-id"></span></li>
                <li class="list-group-item px-0 d-flex flex-column"><strong>@lang('Name')</strong> <span id="file-name"></span></li>
                <li class="list-group-item px-0 d-flex flex-column"><strong>@lang('Size')</strong> <span id="file-size"></span></li>
                <li class="list-group-item px-0 d-flex flex-column"><strong>@lang('Type')</strong> <span id="file-type"></span></li>
                <li class="list-group-item px-0 d-flex flex-column"><strong>@lang('Format')</strong> <span id="file-format" class="text-uppercase text-primary font-weight-bold"></span></li>
                <li class="list-group-item px-0 d-flex flex-column"><strong>@lang('URL')</strong> <a id="file-url" href="#" target="_blank"></a></li>
                <li class="list-group-item px-0 d-flex flex-column"><strong>@lang('Uploaded At')</strong> <span id="file-created-at"></span></li>
                <li class="list-group-item px-0 d-flex flex-column"><strong>@lang('Total Uses Count') </strong> <span id="file-total-uses"></span></li>
            </ul>
        </div>
    </div>
@endsection

@pushOnce('style-lib')
    <link rel="stylesheet" href="{{ asset('assets/global/css/image-uploader.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/media-uploader.css') }}">
@endPushOnce

@pushOnce('script-lib')
    <script src="{{ asset('assets/global/js/image-uploader.min.js') }}"></script>
@endPushOnce

@push('script')
    <script>
        (function($) {
            'use strict';

            let isSubmitting = false;

            const toggleFormButtons = () => {
                const fileInput = $('.input-images input[type="file"]')[0];
                const hasFiles = fileInput && fileInput.files.length > 0;
                // Toggle the buttons based on whether files are selected
                if (hasFiles) {
                    $('.uploaderCancelBtn, .uploaderUploadButton').parent().show();
                } else {
                    $('.uploaderCancelBtn, .uploaderUploadButton').parent().hide();
                }
            }

            const uploader = $('.input-images').fileUploader({
                filesName: 'photos',
                preloadedInputName: 'old',
                maxFiles: 20,
                onSelect: toggleFormButtons,
                onRemove: toggleFormButtons,
                label: 'Drag & drop files here to upload new images',
            });

            const clearUploader = () => {
                $('.input-images').find(".delete-file-button").each((i, e) => e.click())
            }

            const handleFormSubmission = (response) => {
                notify(response.status, response.message);
                if (response.status == 'success') {
                    window.location.reload();
                }
            }

            toggleFormButtons();

            const handleMediaFormSubmit = (e) => {
                e.preventDefault();

                if (isSubmitting) {
                    return;
                }

                isSubmitting = true;

                const form = $('#mediaUploadForm');

                let btn = form.find('button[type=submit]');
                btn.prop('disabled', true);
                btn.html('<i class="fa fa-circle-notch fa-spin" aria-hidden="true"></i>');
                let formData = new FormData(form[0]);
                formData.append('files_for', 'product');

                $.ajax({
                    url: form.prop('action'),
                    type: 'POST',
                    cache: false,
                    contentType: false,
                    processData: false,
                    data: formData,
                    success: handleFormSubmission
                }).always((response) => {
                    isSubmitting = false;
                    btn.prop('disabled', false);
                    btn.text(`@lang('Submit')`);
                });
            }

            $(document).on('click', '.showFileDetails', function() {
                const info = $(this).data('info');

                $('#file-id').text(info.id);
                $('#file-name').text(info.basename);
                $('#file-size').text(info.formatted_size);
                $('#file-type').text(info.mime_type);
                $('#file-format').text(info.extension);
                $('#file-url').attr('href', info.full_path).text(info.full_path);
                $('#file-total-uses').text(info.total_uses);
                $('#file-created-at').text(info.created_at);
            });

            $('#webpAutoConvertToggle').on('change', function() {
                const value = this.checked ? 1 : 0;
                $.ajax({
                    url: "{{ route('admin.media.webp.toggle') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        value: value
                    },
                    success: function(response) {
                        if (response.success) {
                            notify('success', response.message);
                        } else {
                            notify('error', 'Something went wrong.');
                        }
                    },
                    error: function() {
                        notify('error', 'Request failed.');
                    }
                });
            });

            // WebP stepped bulk conversion
            let webpConvertIds = [];
            let totalWebpFiles = 0;
            let currentWebpIndex = 0;
            let isConvertingWebp = false;
            let webpSuccessCount = 0;
            let webpErrorCount = 0;

            const webpModal = $('#webpConvertModal');

            $('#bulkConvertWebpBtn').on('click', function() {
                webpConvertIds = [];
                totalWebpFiles = 0;
                currentWebpIndex = 0;
                isConvertingWebp = false;
                webpSuccessCount = 0;
                webpErrorCount = 0;

                $('#webpConfirmSection').removeClass('d-none');
                $('#webpProgressSection').addClass('d-none');
                $('#webpModalCloseBtn').show();
                $('#webpProgressBar').css('width', '0%').text('0%').attr('aria-valuenow', 0).addClass('progress-bar-animated progress-bar-striped');
                $('#webpConvertStatus').text("@lang('Initializing...')");
                $('#webpConvertFilename').html('');

                webpModal.modal('show');
            });

            $('#confirmWebpConvertBtn').on('click', function() {
                $('#webpConfirmSection').addClass('d-none');
                $('#webpProgressSection').removeClass('d-none');
                $('#webpModalCloseBtn').hide();
                
                isConvertingWebp = true;
                initiateWebpConversion();
            });

            function initiateWebpConversion() {
                $.ajax({
                    url: "{{ route('admin.media.webp.convert.init') }}",
                    type: "GET",
                    success: function(response) {
                        if (response.success && response.ids.length > 0) {
                            webpConvertIds = response.ids;
                            totalWebpFiles = response.total;
                            $('#webpConvertStatus').text(`Processing 0 / ${totalWebpFiles} files...`);
                            processNextWebpFile();
                        } else {
                            $('#webpConvertStatus').text("No files found that need WebP conversion.");
                            $('#webpModalCloseBtn').show();
                            isConvertingWebp = false;
                            setTimeout(() => {
                                webpModal.modal('hide');
                                notify('info', 'All images are already WebP.');
                            }, 1500);
                        }
                    },
                    error: function() {
                        $('#webpConvertStatus').text("Failed to initialize conversion.");
                        $('#webpModalCloseBtn').show();
                        isConvertingWebp = false;
                    }
                });
            }

            function processNextWebpFile() {
                if (currentWebpIndex >= webpConvertIds.length) {
                    $('#webpConvertStatus').text(`Completed! Success: ${webpSuccessCount}, Failed: ${webpErrorCount}`);
                    $('#webpProgressBar').removeClass('progress-bar-animated progress-bar-striped');
                    $('#webpModalCloseBtn').show();
                    isConvertingWebp = false;
                    
                    notify('success', `WebP Conversion Completed! Converted ${webpSuccessCount} images.`);
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                    return;
                }

                const currentId = webpConvertIds[currentWebpIndex];
                
                $.ajax({
                    url: "{{ route('admin.media.webp.convert.step') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        id: currentId
                    },
                    success: function(response) {
                        currentWebpIndex++;
                        if (response.success) {
                            webpSuccessCount++;
                            $('#webpConvertFilename').prepend(`<div class="text-success"><i class="la la-check-circle"></i> [${currentWebpIndex}/${totalWebpFiles}] ${response.filename} -> WebP</div>`);
                        } else {
                            webpErrorCount++;
                            $('#webpConvertFilename').prepend(`<div class="text-danger"><i class="la la-exclamation-circle"></i> [${currentWebpIndex}/${totalWebpFiles}] ${response.filename}: ${response.message}</div>`);
                        }

                        const percent = Math.round((currentWebpIndex / totalWebpFiles) * 100);
                        $('#webpProgressBar').css('width', `${percent}%`).text(`${percent}%`).attr('aria-valuenow', percent);
                        $('#webpConvertStatus').text(`Processing ${currentWebpIndex} / ${totalWebpFiles} files...`);

                        processNextWebpFile();
                    },
                    error: function() {
                        currentWebpIndex++;
                        webpErrorCount++;
                        $('#webpConvertFilename').prepend(`<div class="text-danger"><i class="la la-times-circle"></i> [${currentWebpIndex}/${totalWebpFiles}] Connection error on file ID ${currentId}</div>`);
                        
                        const percent = Math.round((currentWebpIndex / totalWebpFiles) * 100);
                        $('#webpProgressBar').css('width', `${percent}%`).text(`${percent}%`).attr('aria-valuenow', percent);
                        $('#webpConvertStatus').text(`Processing ${currentWebpIndex} / ${totalWebpFiles} files...`);
                        
                        processNextWebpFile();
                    }
                });
            }

            $(document).on('submit', '#mediaUploadForm', (e) => handleMediaFormSubmit(e));
            $(document).on('click', '.uploaderCancelBtn', clearUploader);

            $('[name=order_by]').on('change', function() {
                const selected = $(this).val();
                const url = new URL(window.location.href);
                url.searchParams.set('order_by', selected);
                window.location.href = url.toString();
            });
        })(jQuery);
    </script>
@endpush

@push('style')
    <style>
        .file-uploader {
            border-radius: 5px;
            background-color: #fff;
            border: 1px solid #ebebeb;
        }

        .media-content {
            display: grid;
            gap: 0.5rem;
            grid-template-columns: repeat(6, 1fr);
        }

        @media (max-width: 1299px) {
            .media-content {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 767px) {
            .media-content {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 575px) {
            .media-content {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .media-item {
            background-color: #fff;
            border: 1px solid #ebebeb;
            border-radius: 5px;
            position: relative;
        }

        .media-item .media {
            aspect-ratio: 1 / 1;
            padding: 1rem;
            padding-bottom: .3rem;
        }

        .media-item .media-button {
            position: absolute;
            right: 8px;
            top: 8px;
            background: #f9f9f9;
            border-radius: 4px;
        }

        .media-item .content {
            padding: 0 1rem 1rem 1rem;
            display: flex;
            flex-direction: column;
        }

        .media-item .content .file-name {
            color: #272727 !important;
            font-size: .8rem;
        }

        .media-item .content .file-size {
            font-size: .75rem;
            color: #8a8a8a
        }

        .media-item .dropdown-item {
            font-size: 0.875rem;
        }

        .media-item .content small {
            font-size: 0.8rem;
            color: #272727 !important;
        }

        .media-item img {
            height: 100%;
            object-fit: cover;
        }

        .media-item .delete-btn {
            position: absolute;
            top: 8px;
            right: 10px;
            border-radius: 50%;
            height: 30px;
            width: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1;
        }

        .btn--light {
            background-color: #ffffff !important;
        }

        .media-info {
            position: absolute;
            bottom: 0;
            width: 100%;
            height: 100%;
            background: #000000b4;
            color: #eeeeee;
            display: flex;
            justify-content: end;
            padding: 1rem;
            transition: all 0.2s ease-in-out;
            background: rgb(0, 0, 0);
            background: linear-gradient(2deg, rgb(0 0 0) 0%, rgb(255 255 255 / 0%) 100%);
            font-size: 0.8125rem;
            visibility: hidden;
            opacity: 0;
        }

        .media-item .media:hover .media-info {
            opacity: 1;
            visibility: visible;
        }

        .content .card-body small {
            font-size: 0.75rem;
        }
    </style>
@endpush
