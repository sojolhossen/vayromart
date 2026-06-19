@extends('admin.layouts.app')

@section('panel')
    <!-- Loading Overlay for AI Generation -->
    <div id="loadingOverlay" class="d-none" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.95); z-index: 999999; display: flex; flex-direction: column; align-items: center; justify-content: center; color: white; font-family: sans-serif;">
        <div class="spinner-container mb-4" style="position: relative; width: 100px; height: 100px;">
            <!-- Outer Glow Ring -->
            <div style="box-sizing: border-box; display: block; position: absolute; width: 100px; height: 100px; border: 8px solid transparent; border-radius: 50%; border-top-color: #10b981; animation: spin 1.5s cubic-bezier(0.5, 0, 0.5, 1) infinite;"></div>
            <div style="box-sizing: border-box; display: block; position: absolute; width: 80px; height: 80px; margin: 10px; border: 6px solid transparent; border-radius: 50%; border-bottom-color: #06b6d4; animation: spin-reverse 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;"></div>
            <!-- Center Icon / Logo placeholder -->
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #10b981;">
                <i class="las la-robot la-3x"></i>
            </div>
        </div>
        
        <h3 class="mb-2" style="font-weight: 700; background: linear-gradient(135deg, #10b981, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">AI Landing Page Engine Active</h3>
        <p id="loadingMessage" class="text-muted text-center px-4" style="max-width: 500px; font-size: 16px; color: #94a3b8 !important;">Initializing product analyzer...</p>
        
        <!-- Live progress indicators -->
        <div class="mt-4 w-100" style="max-width: 400px; background: #1e293b; height: 6px; border-radius: 10px; overflow: hidden; border: 1px solid #334155;">
            <div id="loadingProgressBar" style="width: 5%; height: 100%; background: linear-gradient(90deg, #10b981, #06b6d4); transition: width 0.4s ease-border;"></div>
        </div>
    </div>

    <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes spin-reverse {
            0% { transform: rotate(360deg); }
            100% { transform: rotate(0deg); }
        }
    </style>

    <div class="row">
        <!-- Generator Form Card -->
        <div class="col-xl-4 col-lg-5 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg--dark d-flex align-items-center">
                    <h5 class="text-white card-title mb-0"><i class="las la-magic"></i> @lang('AI Generator Form')</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.landing.generate') }}" method="POST" id="generateForm">
                        @csrf
                        <div class="form-group mb-3">
                            <label class="fw-bold">@lang('Select Product') <span class="text-danger">*</span></label>
                            <select name="product_id" class="form-control select2" required>
                                <option value="">-- @lang('Choose a product') --</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">@lang('Select the product to research and generate page for.')</small>
                        </div>

                        <div class="form-group mb-3">
                            <label class="fw-bold">@lang('Design Theme/Style') <span class="text-danger">*</span></label>
                            <select name="style" class="form-control" required>
                                <option value="Modern">@lang('Modern (Sleek Gradients, Visuals)')</option>
                                <option value="Clean">@lang('Clean (Standard White/Light E-commerce)')</option>
                                <option value="Minimalist">@lang('Minimalist (Lots of Whitespace, Bold Typography)')</option>
                                <option value="Corporate">@lang('Corporate (Trustworthy, Professional)')</option>
                                <option value="Dark Mode">@lang('Dark Mode (Cyberpunk/Premium Dark Aesthetics)')</option>
                                <option value="Elegant">@lang('Elegant (Classic Serif, High-end feel)')</option>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label class="fw-bold">@lang('Focus Keyword (SEO)')</label>
                            <input type="text" name="focus_keyword" class="form-control" placeholder="e.g. Smart Watch Bangladesh, Cheap Router">
                            <small class="text-muted">@lang('AI will optimize the title and headings for this keyword.')</small>
                        </div>

                        <div class="form-group mb-3">
                            <label class="fw-bold">@lang('Extra Custom Instructions')</label>
                            <textarea name="extra_instructions" class="form-control" rows="4" placeholder="e.g. Highlight 10% discount, mention free home delivery in Dhaka, emphasize 1-year replacement warranty..."></textarea>
                            <small class="text-muted">@lang('Provide specific selling points or offers you want in the page.')</small>
                        </div>

                        <button type="submit" class="btn btn--primary w-100 py-2" id="submitBtn">
                            <i class="las la-rocket"></i> @lang('Generate Landing Page')
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Generated List Card -->
        <div class="col-xl-8 col-lg-7 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg--dark d-flex align-items-center justify-content-between">
                    <h5 class="text-white card-title mb-0"><i class="las la-list"></i> @lang('Generated Landing Pages')</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive--md table-responsive">
                        <table class="table table--light style--two">
                            <thead>
                                <tr>
                                    <th>@lang('Product Name')</th>
                                    <th>@lang('URL Slug / Link')</th>
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
                                                <a href="{{ route('landing.view', $page->slug) }}" target="_blank" class="btn btn-sm btn-outline--primary">
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
                                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage ?? 'No landing pages generated yet') }}</td>
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

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Delete Landing Page')</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
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

        // Setup Select2 for product search dropdown
        $('.select2').select2({
            dropdownParent: $('.card-body')
        });

        // Trigger delete confirmation modal
        $('.deleteBtn').on('click', function () {
            var id = $(this).data('id');
            var productName = $(this).data('product');
            var actionUrl = "{{ route('admin.landing.delete', '') }}/" + id;
            
            $('#deleteProductName').text(productName);
            $('#deleteForm').attr('action', actionUrl);
            $('#deleteModal').modal('show');
        });

        // Handle generator submit to show premium loading steps
        $('#generateForm').on('submit', function () {
            $('#loadingOverlay').removeClass('d-none');
            
            var steps = [
                { percent: 10, text: "Initializing market researcher agent..." },
                { percent: 25, text: "Retrieving e-commerce product specifications..." },
                { percent: 45, text: "Analyzing competitor details and target audience pain points..." },
                { percent: 65, text: "Writing highly persuasive copywriting copy in Bengali (Benglish)..." },
                { percent: 80, text: "Creating a premium Tailwind CSS landing layout theme..." },
                { percent: 90, text: "Integrating standard Cash on Delivery checkout form..." },
                { percent: 95, text: "Formatting and compiling clean, responsive HTML outputs..." }
            ];

            var currentStep = 0;
            var interval = setInterval(function () {
                if (currentStep < steps.length) {
                    $('#loadingProgressBar').css('width', steps[currentStep].percent + '%');
                    $('#loadingMessage').text(steps[currentStep].text);
                    currentStep++;
                }
            }, 2500);

            // Safety check: clear interval if page unloads or takes extremely long
            $(window).on('unload', function () {
                clearInterval(interval);
            });
        });

    })(jQuery);
</script>
@endpush
