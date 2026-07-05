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

                        <hr>

                        <h6 class="mb-3 text--primary"><i class="las la-shopping-cart"></i> @lang('Google Sheets Orders Posting Settings')</h6>
                        <div class="row">
                            @if(!empty($settings['google_refresh_token']))
                                <div class="col-md-6 mb-3">
                                    <div class="form-group">
                                        <label class="fw-bold">@lang('Select Google Spreadsheet for Orders')</label>
                                        <select class="form-control" name="google_orders_spreadsheet_id" id="google_orders_spreadsheet_id" required>
                                            <option value="">@lang('Loading spreadsheets...')</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="fw-bold">@lang('Select Orders Sheet Tab')</label>
                                        <select class="form-control" name="google_orders_sheet_name" id="google_orders_sheet_name" required>
                                            <option value="">@lang('Select Spreadsheet first')</option>
                                        </select>
                                    </div>
                                </div>
                            @else
                                <div class="col-md-6 mb-3">
                                    <div class="form-group">
                                        <label class="fw-bold">@lang('Google Spreadsheet ID / Document URL for Orders')</label>
                                        <input type="text" class="form-control" name="google_orders_spreadsheet_id" value="{{ $settings['google_orders_spreadsheet_id'] ?? '' }}" placeholder="Enter spreadsheet ID or full URL">
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="fw-bold">@lang('Orders Sheet Name')</label>
                                        <input type="text" class="form-control" name="google_orders_sheet_name" value="{{ $settings['google_orders_sheet_name'] ?? 'Sheet1' }}" placeholder="Sheet1">
                                    </div>
                                </div>
                            @endif
                            <div class="col-md-3 mb-3">
                                <div class="form-group">
                                    <label class="fw-bold">@lang('Enable Order Posting')</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" name="google_orders_sync_enabled" id="google_orders_sync_enabled" value="1" @checked($settings['google_orders_sync_enabled'] ?? false)>
                                        <label class="form-check-label" for="google_orders_sync_enabled">@lang('Post Orders')</label>
                                    </div>
                                </div>
                        </div>

                        <div class="row mt-3" id="mapping-container" style="display: none;">
                            <div class="col-12">
                                <h6 class="mb-3 text--warning"><i class="las la-project-diagram"></i> @lang('Google Sheet Column Mapping')</h6>
                                <p class="text-muted small">@lang('For each column found in your Google Sheet, select the corresponding Vayromart order attribute to populate it.')</p>
                            </div>
                            <div class="col-12">
                                <div class="row" id="dynamic-mappings-list">
                                    <!-- Populated dynamically via JS -->
                                </div>
                            </div>
                        </div>

                        <hr>

                        <h6 class="mb-3 text--info"><i class="las la-search-location"></i> @lang('Google Sheets Order Status Lookup Settings')</h6>
                        <div class="row">
                            @if(!empty($settings['google_refresh_token']))
                                <div class="col-md-6 mb-3">
                                    <div class="form-group">
                                        <label class="fw-bold">@lang('Select Google Spreadsheet for Order Check')</label>
                                        <select class="form-control" name="google_lookup_spreadsheet_id" id="google_lookup_spreadsheet_id" required>
                                            <option value="">@lang('Loading spreadsheets...')</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="fw-bold">@lang('Select Order Check Sheet Tab')</label>
                                        <select class="form-control" name="google_lookup_sheet_name" id="google_lookup_sheet_name" required>
                                            <option value="">@lang('Select Spreadsheet first')</option>
                                        </select>
                                    </div>
                                </div>
                            @else
                                <div class="col-md-6 mb-3">
                                    <div class="form-group">
                                        <label class="fw-bold">@lang('Google Spreadsheet ID / Document URL for Order Check')</label>
                                        <input type="text" class="form-control" name="google_lookup_spreadsheet_id" value="{{ $settings['google_lookup_spreadsheet_id'] ?? '' }}" placeholder="Enter spreadsheet ID or full URL">
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="fw-bold">@lang('Order Check Sheet Name')</label>
                                        <input type="text" class="form-control" name="google_lookup_sheet_name" value="{{ $settings['google_lookup_sheet_name'] ?? 'Sheet1' }}" placeholder="Sheet1">
                                    </div>
                                </div>
                            @endif
                            <div class="col-md-3 mb-3">
                                <div class="form-group">
                                    <label class="fw-bold">@lang('Enable Order Check lookup')</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" name="google_lookup_sync_enabled" id="google_lookup_sync_enabled" value="1" @checked($settings['google_lookup_sync_enabled'] ?? false)>
                                        <label class="form-check-label" for="google_lookup_sync_enabled">@lang('Check from Sheet')</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3" id="mapping-container-lookup" style="display: none;">
                            <div class="col-12">
                                <h6 class="mb-3 text--info"><i class="las la-project-diagram"></i> @lang('Google Sheet Lookup Column Mapping')</h6>
                                <p class="text-muted small">@lang('For each column found in your Order Check Google Sheet, select the corresponding Vayromart order attribute to map it.')</p>
                            </div>
                            <div class="col-12">
                                <div class="row" id="dynamic-lookup-mappings-list">
                                    <!-- Populated dynamically via JS -->
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
                                
                                <a href="{{ route('admin.setting.sales_agent.google.redirect') }}" class="btn btn-sm btn-outline--warning">
                                    <i class="las la-sync-alt"></i> @lang('Reconnect Account')
                                </a>
                                
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
                                // Products Select
                                const select = $('#google_spreadsheet_id');
                                select.empty().append('<option value="">-- @lang("Select Spreadsheet") --</option>');
                                const savedId = "{{ $settings['google_spreadsheet_id'] ?? '' }}";
                                
                                // Orders Select
                                const ordersSelect = $('#google_orders_spreadsheet_id');
                                if (ordersSelect.length) {
                                     ordersSelect.empty().append('<option value="">-- @lang("Select Spreadsheet") --</option>');
                                }
                                const savedOrdersId = "{{ $settings['google_orders_spreadsheet_id'] ?? '' }}";

                                // Lookup Select
                                const lookupSelect = $('#google_lookup_spreadsheet_id');
                                if (lookupSelect.length) {
                                     lookupSelect.empty().append('<option value="">-- @lang("Select Spreadsheet") --</option>');
                                }
                                const savedLookupId = "{{ $settings['google_lookup_spreadsheet_id'] ?? '' }}";

                                response.files.forEach(function(file) {
                                    const selected = (file.id === savedId) ? 'selected' : '';
                                    select.append(`<option value="${file.id}" ${selected}>${file.name}</option>`);

                                    if (ordersSelect.length) {
                                        const orderSelected = (file.id === savedOrdersId) ? 'selected' : '';
                                        ordersSelect.append(`<option value="${file.id}" ${orderSelected}>${file.name}</option>`);
                                    }

                                    if (lookupSelect.length) {
                                        const lookupSelected = (file.id === savedLookupId) ? 'selected' : '';
                                        lookupSelect.append(`<option value="${file.id}" ${lookupSelected}>${file.name}</option>`);
                                    }
                                });

                                // Trigger load sheets
                                if (savedId) {
                                    loadSheets(savedId, 'google_sheet_name', "{{ $settings['google_sheet_name'] ?? 'Sheet1' }}");
                                }
                                if (savedOrdersId && ordersSelect.length) {
                                    loadSheets(savedOrdersId, 'google_orders_sheet_name', "{{ $settings['google_orders_sheet_name'] ?? 'Sheet1' }}");
                                }
                                if (savedLookupId && lookupSelect.length) {
                                    loadSheets(savedLookupId, 'google_lookup_sheet_name', "{{ $settings['google_lookup_sheet_name'] ?? 'Sheet1' }}");
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
                function loadSheets(spreadsheetId, targetSelectId, savedValue) {
                    const sheetSelect = $('#' + targetSelectId);
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
                                response.sheets.forEach(function(sheet) {
                                    const selected = (sheet === savedValue) ? 'selected' : '';
                                    sheetSelect.append(`<option value="${sheet}" ${selected}>${sheet}</option>`);
                                });

                                if (targetSelectId === 'google_orders_sheet_name' && savedValue) {
                                    loadSheetHeaders(spreadsheetId, savedValue);
                                }
                                if (targetSelectId === 'google_lookup_sheet_name' && savedValue) {
                                    loadLookupSheetHeaders(spreadsheetId, savedValue);
                                }
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

                // Load headers list for column mapping
                function loadSheetHeaders(spreadsheetId, sheetName) {
                    const container = $('#mapping-container');
                    const listContainer = $('#dynamic-mappings-list');
                    
                    listContainer.empty().append('<div class="col-12 text-center p-3 text-muted"><i class="las la-spinner la-spin"></i> @lang("Fetching columns from sheet...")</div>');

                    let absoluteUrl = "{{ route('admin.setting.sales_agent.google.sheets.headers', [':id', ':sheet']) }}"
                        .replace(':id', spreadsheetId).replace('%3Aid', spreadsheetId)
                        .replace(':sheet', sheetName).replace('%3Asheet', sheetName);
                    
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
                                container.show();
                                listContainer.empty();

                                if (response.headers.length === 0) {
                                    listContainer.append('<div class="col-12 text-center p-3 text-danger"><i class="las la-exclamation-triangle"></i> @lang("No columns found in the first row of this sheet. Please add some column headers first.")</div>');
                                    return;
                                }

                                const savedMappings = @json($settings['google_orders_field_mapping'] ?? []);

                                const availableOptions = {
                                    '': '-- @lang("Do not fill this column") --',
                                    'order_date': '@lang("Order Date/Time")',
                                    'order_number': '@lang("Order Number")',
                                    'customer_name': '@lang("Customer Name")',
                                    'customer_mobile': '@lang("Customer Mobile/Phone")',
                                    'product_name': '@lang("Product Name")',
                                    'quantity': '@lang("Quantity")',
                                    'total_amount': '@lang("Total Amount")',
                                    'shipping_address': '@lang("Shipping Address")',
                                    'order_status': '@lang("Order Status")'
                                };

                                response.headers.forEach(function(header) {
                                    if (!header || header.trim() === '') return;

                                    const savedValue = savedMappings[header] || '';
                                    
                                    let selectOptionsHtml = '';
                                    Object.keys(availableOptions).forEach(function(key) {
                                        const selected = (key === savedValue) ? 'selected' : '';
                                        selectOptionsHtml += `<option value="${key}" ${selected}>${availableOptions[key]}</option>`;
                                    });

                                    const fieldHtml = `
                                        <div class="col-md-4 mb-3">
                                            <div class="form-group">
                                                <label class="fw-bold text--primary">${header}</label>
                                                <select class="form-control" name="google_orders_field_mapping[${header}]">
                                                    ${selectOptionsHtml}
                                                </select>
                                            </div>
                                        </div>
                                    `;
                                    listContainer.append(fieldHtml);
                                });
                            } else {
                                container.hide();
                                notify('error', response.message);
                            }
                        },
                        error: function() {
                            container.hide();
                            notify('error', 'Failed to load sheet column headers.');
                        }
                    });
                }

                // Load lookup headers list for column mapping
                function loadLookupSheetHeaders(spreadsheetId, sheetName) {
                    const container = $('#mapping-container-lookup');
                    const listContainer = $('#dynamic-lookup-mappings-list');
                    
                    listContainer.empty().append('<div class="col-12 text-center p-3 text-muted"><i class="las la-spinner la-spin"></i> @lang("Fetching columns from sheet...")</div>');

                    let absoluteUrl = "{{ route('admin.setting.sales_agent.google.sheets.headers', [':id', ':sheet']) }}"
                        .replace(':id', spreadsheetId).replace('%3Aid', spreadsheetId)
                        .replace(':sheet', sheetName).replace('%3Asheet', sheetName);
                    
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
                                container.show();
                                listContainer.empty();

                                if (response.headers.length === 0) {
                                    listContainer.append('<div class="col-12 text-center p-3 text-danger"><i class="las la-exclamation-triangle"></i> @lang("No columns found in the first row of this sheet. Please add some column headers first.")</div>');
                                    return;
                                }

                                const savedMappings = @json($settings['google_lookup_field_mapping'] ?? []);

                                const availableOptions = {
                                    '': '-- @lang("Do not map this column") --',
                                    'order_date': '@lang("Order Date/Time")',
                                    'order_number': '@lang("Order Number")',
                                    'customer_name': '@lang("Customer Name")',
                                    'customer_mobile': '@lang("Customer Mobile/Phone")',
                                    'product_name': '@lang("Product Name")',
                                    'quantity': '@lang("Quantity")',
                                    'total_amount': '@lang("Total Amount")',
                                    'shipping_address': '@lang("Shipping Address")',
                                    'order_status': '@lang("Order Status")'
                                };

                                response.headers.forEach(function(header) {
                                    if (!header || header.trim() === '') return;

                                    const savedValue = savedMappings[header] || '';
                                    
                                    let selectOptionsHtml = '';
                                    Object.keys(availableOptions).forEach(function(key) {
                                        const selected = (key === savedValue) ? 'selected' : '';
                                        selectOptionsHtml += `<option value="${key}" ${selected}>${availableOptions[key]}</option>`;
                                    });

                                    const fieldHtml = `
                                        <div class="col-md-4 mb-3">
                                            <div class="form-group">
                                                <label class="fw-bold text--info">${header}</label>
                                                <select class="form-control" name="google_lookup_field_mapping[${header}]">
                                                    ${selectOptionsHtml}
                                                </select>
                                            </div>
                                        </div>
                                    `;
                                    listContainer.append(fieldHtml);
                                });
                            } else {
                                container.hide();
                                notify('error', response.message);
                            }
                        },
                        error: function() {
                            container.hide();
                            notify('error', 'Failed to load sheet column headers.');
                        }
                    });
                }

                // Initial loading
                loadSpreadsheets();

                // Products changed event
                $('#google_spreadsheet_id').on('change', function() {
                    const val = $(this).val();
                    if (val) {
                        loadSheets(val, 'google_sheet_name', '');
                    } else {
                        $('#google_sheet_name').empty().append('<option value="">@lang("Select Spreadsheet first")</option>');
                    }
                });

                // Orders changed event
                $('#google_orders_spreadsheet_id').on('change', function() {
                    const val = $(this).val();
                    if (val) {
                        loadSheets(val, 'google_orders_sheet_name', '');
                    } else {
                        $('#google_orders_sheet_name').empty().append('<option value="">@lang("Select Spreadsheet first")</option>');
                        $('#mapping-container').hide();
                    }
                });

                // Orders sheet tab changed event
                $('#google_orders_sheet_name').on('change', function() {
                    const spreadsheetId = $('#google_orders_spreadsheet_id').val();
                    const sheetName = $(this).val();
                    if (spreadsheetId && sheetName) {
                        loadSheetHeaders(spreadsheetId, sheetName);
                    } else {
                        $('#mapping-container').hide();
                    }
                });

                // Lookup changed event
                $('#google_lookup_spreadsheet_id').on('change', function() {
                    const val = $(this).val();
                    if (val) {
                        loadSheets(val, 'google_lookup_sheet_name', '');
                    } else {
                        $('#google_lookup_sheet_name').empty().append('<option value="">@lang("Select Spreadsheet first")</option>');
                        $('#mapping-container-lookup').hide();
                    }
                });

                // Lookup sheet tab changed event
                $('#google_lookup_sheet_name').on('change', function() {
                    const spreadsheetId = $('#google_lookup_spreadsheet_id').val();
                    const sheetName = $(this).val();
                    if (spreadsheetId && sheetName) {
                        loadLookupSheetHeaders(spreadsheetId, sheetName);
                    } else {
                        $('#mapping-container-lookup').hide();
                    }
                });
            @endif

        })(jQuery);
    </script>
@endpush
