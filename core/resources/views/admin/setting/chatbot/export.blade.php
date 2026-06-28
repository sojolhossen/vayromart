@extends('admin.layouts.app')
@section('panel')
    <div class="row justify-content-center">
        <div class="col-xl-8 col-lg-10">
            <div class="card border--dark">
                <div class="card-header bg--dark d-flex justify-content-between align-items-center">
                    <h5 class="text-white m-0"><i class="las la-file-export"></i> @lang('AI Chatbot Data JSON Exporter')</h5>
                    <a href="{{ route('admin.setting.chatbot.index') }}" class="btn btn-sm btn-outline-light"><i class="las la-cog"></i> @lang('Go Back')</a>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        @lang('Select the database tables you want to export. The exporter will read raw records, run them through the AI formatter, and compile a single compact, optimized JSON context file at') <code>storage/app/chatbot/data.json</code>. @lang('The chatbot will read directly from this JSON context to give instant responses.')
                    </p>

                    <form id="exporter-form" method="POST">
                        @csrf
                        <div class="row mb-4">
                            <div class="col-12">
                                <label class="fw-bold mb-2">@lang('Select Tables to Include in Export:')</label>
                                <div class="row g-3">
                                    @foreach($tables as $key => $label)
                                        <div class="col-md-6">
                                            <div class="form-check p-3 border rounded shadow-sm hover-shadow bg-light">
                                                <input class="form-check-input ms-0 me-2" type="checkbox" name="tables[]" value="{{ $key }}" id="table-{{ $key }}" checked>
                                                <label class="form-check-label fw-bold text-dark" for="table-{{ $key }}">
                                                    {{ trans($label) }}
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <!-- Progress Bar Section (Initially Hidden) -->
                        <div id="progress-section" class="d-none mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-bold" id="progress-status">@lang('Initializing exporter...')</span>
                                <span class="fw-bold text--primary" id="progress-percent">0%</span>
                            </div>
                            <div class="progress" style="height: 18px; border-radius: 9px; overflow: hidden; background: #e9ecef; box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
                                <div id="progress-bar-el" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" id="submit-btn" class="btn btn--primary w-100 h-45 fs-6"><i class="las la-rocket"></i> @lang('Generate Chatbot JSON Database')</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        (function($) {
            "use strict";

            $('#exporter-form').on('submit', function(e) {
                e.preventDefault();

                const selectedTables = $('input[name="tables[]"]:checked').map(function() {
                    return this.value;
                }).get();

                if (selectedTables.length === 0) {
                    notify('error', 'Please select at least one table to export!');
                    return;
                }

                // UI adjustments
                $('#submit-btn').prop('disabled', true).html('<i class="las la-spinner la-spin"></i> Processing and converting via AI...');
                $('#progress-section').removeClass('d-none');
                
                // Reset progress bar
                let progressPercent = 0;
                let progressBar = $('#progress-bar-el');
                let progressPercentText = $('#progress-percent');
                let progressStatusText = $('#progress-status');

                progressBar.css('width', '0%').attr('aria-valuenow', 0);
                progressPercentText.text('0%');
                progressStatusText.text('Reading selected database tables...');

                // Dynamic UI animation interval
                let progressInterval = setInterval(function() {
                    if (progressPercent < 90) {
                        progressPercent += Math.floor(Math.random() * 8) + 2;
                        if (progressPercent > 90) progressPercent = 90;

                        progressBar.css('width', progressPercent + '%').attr('aria-valuenow', progressPercent);
                        progressPercentText.text(progressPercent + '%');

                        if (progressPercent < 30) {
                            progressStatusText.text('Reading selected database tables...');
                        } else if (progressPercent < 60) {
                            progressStatusText.text('Formatting and cleaning data schemas...');
                        } else {
                            progressStatusText.text('AI Engine: Formatting and optimizing to support-friendly JSON schema...');
                        }
                    }
                }, 300);

                // Make AJAX export request
                $.ajax({
                    url: "{{ route('admin.setting.chatbot.export.process') }}",
                    method: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        tables: selectedTables
                    },
                    success: function(response) {
                        clearInterval(progressInterval);
                        
                        if (response.success) {
                            // Finish progress bar to 100%
                            progressBar.css('width', '100%').attr('aria-valuenow', 100);
                            progressPercentText.text('100%');
                            progressStatusText.html(`<span class="text-success"><i class="las la-check-circle"></i> ${response.message}</span>`);
                            notify('success', response.message);
                        } else {
                            resetUIOnError(response.message);
                        }
                    },
                    error: function(xhr) {
                        clearInterval(progressInterval);
                        resetUIOnError('An error occurred during export processing.');
                    }
                });

                function resetUIOnError(errMsg) {
                    $('#submit-btn').prop('disabled', false).html('<i class="las la-rocket"></i> Generate Chatbot JSON Database');
                    progressBar.css('width', '0%').attr('aria-valuenow', 0);
                    progressPercentText.text('0%');
                    progressStatusText.html(`<span class="text-danger"><i class="las la-exclamation-triangle"></i> ${errMsg}</span>`);
                    notify('error', errMsg);
                }
            });

        })(jQuery);
    </script>
@endpush

@push('style')
    <style>
        .hover-shadow {
            transition: all 0.2s ease-in-out;
        }
        .hover-shadow:hover {
            box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
            border-color: var(--bs-primary) !important;
            background-color: #fff !important;
        }
    </style>
@endpush
