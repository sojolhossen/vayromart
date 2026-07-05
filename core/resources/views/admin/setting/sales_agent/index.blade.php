@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg--dark">
                    <h5 class="text-white m-0"><i class="las la-headset"></i> @lang('Facebook Sales Agent & Google Sheets Integration')</h5>
                </div>
                <form action="{{ route('admin.setting.sales_agent.update') }}" method="POST">
                    @csrf
                    <div class="card-body">
                        <h6 class="mb-3 text--primary"><i class="lab la-facebook-messenger"></i> @lang('Facebook Webhook Integration')</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label class="fw-bold">@lang('Facebook Webhook Verify Token')</label>
                                    <input type="text" class="form-control" name="facebook_verify_token" value="{{ $settings['facebook_verify_token'] ?? '' }}" placeholder="VayromartFBVerifyToken">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label class="fw-bold">@lang('Facebook Page Access Token')</label>
                                    <input type="password" class="form-control" name="facebook_page_access_token" value="{{ $settings['facebook_page_access_token'] ?? '' }}">
                                </div>
                            </div>
                            <div class="col-12 mb-3">
                                <div class="alert alert-info">
                                    <strong>@lang('Facebook Callback URL (Messenger Setup):')</strong><br>
                                    <code>{{ request()->getSchemeAndHttpHost() }}/facebook/webhook</code>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <h6 class="mb-3 text--primary"><i class="las la-table"></i> @lang('Google Sheets OAuth Sync Settings')</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label class="fw-bold">@lang('Google Sheets API Client ID')</label>
                                    <input type="text" class="form-control" name="google_client_id" value="{{ $settings['google_client_id'] ?? '' }}" placeholder="575075009929-....apps.googleusercontent.com">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label class="fw-bold">@lang('Google Sheets API Client Secret')</label>
                                    <input type="password" class="form-control" name="google_client_secret" value="{{ $settings['google_client_secret'] ?? '' }}">
                                </div>
                            </div>
                            @if(!empty($settings['google_refresh_token']))
                                <div class="col-md-6 mb-3">
                                    <div class="form-group">
                                        <label class="fw-bold">@lang('Select Google Spreadsheet (Document)')</label>
                                        <select class="form-control" name="google_spreadsheet_id" id="google_spreadsheet_id" required>
                                            <option value="">@lang('Loading spreadsheets...')</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="fw-bold">@lang('Select Sheet Tab')</label>
                                        <select class="form-control" name="google_sheet_name" id="google_sheet_name" required>
                                            <option value="">@lang('Select Spreadsheet first')</option>
                                        </select>
                                    </div>
                                </div>
                            @else
                                <div class="col-md-6 mb-3">
                                    <div class="form-group">
                                        <label class="fw-bold">@lang('Google Spreadsheet ID / Document URL')</label>
                                        <input type="text" class="form-control" name="google_spreadsheet_id" value="{{ $settings['google_spreadsheet_id'] ?? '' }}" placeholder="Enter spreadsheet ID or full URL">
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="fw-bold">@lang('Sheet Name')</label>
                                        <input type="text" class="form-control" name="google_sheet_name" value="{{ $settings['google_sheet_name'] ?? 'Sheet1' }}" placeholder="Sheet1">
                                    </div>
                                </div>
                            @endif
                            <div class="col-md-3 mb-3">
                                <div class="form-group">
                                    <label class="fw-bold">@lang('Enable Google Sheet Sync')</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" name="google_sheet_sync_enabled" id="google_sheet_sync_enabled" value="1" @checked($settings['google_sheet_sync_enabled'] ?? false)>
                                        <label class="form-check-label" for="google_sheet_sync_enabled">@lang('Auto-Sync')</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 mb-3">
                                <div class="alert alert-info">
                                    <strong>@lang('OAuth Redirect URI (Google Console):')</strong><br>
                                    <code>{{ route('admin.setting.sales_agent.google.callback') }}</code>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 d-flex align-items-center flex-wrap gap-2">
                            @if(empty($settings['google_refresh_token']))
                                @if(!empty($settings['google_client_id']))
                                    <a href="{{ route('admin.setting.sales_agent.google.redirect') }}" class="btn btn-sm btn--warning text-dark">
                                        <i class="las la-link"></i> @lang('Connect Google Account')
                                    </a>
                                @else
                                    <span class="text-danger small"><i class="las la-exclamation-triangle"></i> @lang('Enter Client ID and Secret and Save to authenticate.')</span>
                                @endif
                            @else
                                <span class="badge badge--success py-2 px-3"><i class="las la-check-circle"></i> @lang('Google Connected')</span>
                                
                                <button type="button" class="btn btn-sm btn--info text-white" id="sync-sheet-btn">
                                    <i class="las la-sync-alt"></i> @lang('Sync Google Sheet Now')
                                </button>
                                <span id="sync-status" class="ms-2 text-muted small"></span>
                            @endif
                        </div>

                        <hr>

                        <button type="submit" class="btn btn--primary w-100 h-45">@lang('Save Settings')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        (function($) {
            "use strict";

            // Google Sheet Sync manual trigger
            $('#sync-sheet-btn').on('click', function() {
                const btn = $(this);
                const status = $('#sync-status');
                
                btn.prop('disabled', true).find('i').addClass('la-spin');
                status.text('Synchronizing product database with Google Sheet...').removeClass('text-danger text-success').addClass('text-muted');

                let absoluteUrl = "{{ route('admin.setting.sales_agent.sync.sheet') }}";
                let ajaxUrl = absoluteUrl;
                if (absoluteUrl.startsWith('http://') || absoluteUrl.startsWith('https://')) {
                    try {
                        let parsedUrl = new URL(absoluteUrl);
                        ajaxUrl = window.location.protocol + '//' + window.location.host + parsedUrl.pathname + parsedUrl.search;
                    } catch(e) {
                        ajaxUrl = absoluteUrl.replace(/^https?:\/\/[^\/]+/i, '');
                    }
                }

                $.ajax({
                    url: ajaxUrl,
                    method: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}"
                    },
                    dataType: 'json',
                    success: function(response) {
                        btn.prop('disabled', false).find('i').removeClass('la-spin');
                        if (response.success) {
                            status.text(response.message).removeClass('text-muted').addClass('text-success');
                            notify('success', response.message);
                        } else {
                            status.text(response.message).removeClass('text-muted').addClass('text-danger');
                            notify('error', response.message);
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false).find('i').removeClass('la-spin');
                        status.text('An error occurred during synchronization.').removeClass('text-muted').addClass('text-danger');
                        notify('error', 'An error occurred during synchronization.');
                    }
                });
            });

            @if(!empty($settings['google_refresh_token']))
                // Load spreadsheets list
                function loadSpreadsheets() {
                    let absoluteUrl = "{{ route('admin.setting.sales_agent.google.spreadsheets') }}";
                    let ajaxUrl = absoluteUrl;
                    if (absoluteUrl.startsWith('http://') || absoluteUrl.startsWith('https://')) {
                        try {
                            let parsedUrl = new URL(absoluteUrl);
                            ajaxUrl = window.location.protocol + '//' + window.location.host + parsedUrl.pathname + parsedUrl.search;
                        } catch(e) {
                            ajaxUrl = absoluteUrl.replace(/^https?:\/\/[^\/]+/i, '');
                        }
                    }

                    $.ajax({
                        url: ajaxUrl,
                        method: 'GET',
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                const select = $('#google_spreadsheet_id');
                                select.empty().append('<option value="">-- @lang("Select Spreadsheet") --</option>');
                                
                                const savedId = "{{ $settings['google_spreadsheet_id'] ?? '' }}";
                                
                                response.files.forEach(function(file) {
                                    const selected = (file.id === savedId) ? 'selected' : '';
                                    select.append(`<option value="${file.id}" ${selected}>${file.name}</option>`);
                                });

                                // If there was a saved ID, trigger load sheets
                                if (savedId) {
                                    loadSheets(savedId);
                                }
                            } else {
                                notify('error', response.message);
                            }
                        },
                        error: function() {
                            notify('error', 'Failed to load Google Spreadsheets.');
                        }
                    });
                }

                // Load tabs of selected spreadsheet
                function loadSheets(spreadsheetId) {
                    const sheetSelect = $('#google_sheet_name');
                    sheetSelect.empty().append('<option value="">@lang("Loading sheets...")</option>');

                    let absoluteUrl = "{{ route('admin.setting.sales_agent.google.sheets.list', ':id') }}".replace(':id', spreadsheetId).replace('%3Aid', spreadsheetId);
                    let ajaxUrl = absoluteUrl;
                    if (absoluteUrl.startsWith('http://') || absoluteUrl.startsWith('https://')) {
                        try {
                            let parsedUrl = new URL(absoluteUrl);
                            ajaxUrl = window.location.protocol + '//' + window.location.host + parsedUrl.pathname + parsedUrl.search;
                        } catch(e) {
                            ajaxUrl = absoluteUrl.replace(/^https?:\/\/[^\/]+/i, '');
                        }
                    }

                    $.ajax({
                        url: ajaxUrl,
                        method: 'GET',
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                sheetSelect.empty().append('<option value="">-- @lang("Select Tab") --</option>');
                                
                                const savedSheet = "{{ $settings['google_sheet_name'] ?? 'Sheet1' }}";
                                
                                response.sheets.forEach(function(sheet) {
                                    const selected = (sheet === savedSheet) ? 'selected' : '';
                                    sheetSelect.append(`<option value="${sheet}" ${selected}>${sheet}</option>`);
                                });
                            } else {
                                notify('error', response.message);
                                sheetSelect.empty().append('<option value="">@lang("Failed to load tabs")</option>');
                            }
                        },
                        error: function() {
                            notify('error', 'Failed to load sheets list.');
                            sheetSelect.empty().append('<option value="">@lang("Failed to load tabs")</option>');
                        }
                    });
                }

                // Initial loading
                loadSpreadsheets();

                // Spreadsheet changed event
                $('#google_spreadsheet_id').on('change', function() {
                    const val = $(this).val();
                    if (val) {
                        loadSheets(val);
                    } else {
                        $('#google_sheet_name').empty().append('<option value="">@lang("Select Spreadsheet first")</option>');
                    }
                });
            @endif

        })(jQuery);
    </script>
@endpush
