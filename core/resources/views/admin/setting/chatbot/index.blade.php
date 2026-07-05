@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-md-12">
            <!-- Tabs Navigation -->
            <div class="card mb-4">
                <div class="card-header bg--dark">
                    <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-2">
                        <ul class="nav nav-tabs card-header-tabs border-0" id="chatbotTabs" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active text-white" id="config-tab" data-bs-toggle="tab" data-bs-target="#config" type="button" role="tab">
                                    <i class="las la-cog"></i> @lang('AI Configuration')
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link text-white" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab">
                                    <i class="las la-history"></i> @lang('Chat History Logs')
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link text-white" id="knowledge-tab" data-bs-toggle="tab" data-bs-target="#knowledge" type="button" role="tab">
                                    <i class="las la-brain"></i> @lang('Knowledge Base (Self-Learning)')
                                </button>
                            </li>
                        </ul>
                        <a href="{{ route('admin.setting.chatbot.export') }}" class="btn btn-sm btn--success text-white me-2"><i class="las la-file-export"></i> @lang('Export JSON Context')</a>
                    </div>
                </div>
            </div>

            <!-- Tabs Content -->
            <div class="tab-content" id="chatbotTabsContent">
                
                <!-- Tab 1: AI Configuration -->
                <div class="tab-pane fade show active" id="config" role="tabpanel">
                    <div class="card">
                        <form action="{{ route('admin.setting.chatbot.update') }}" method="POST">
                            @csrf
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-bold">@lang('Chatbot Enable / Disable')</label>
                                            <div class="form-check form-switch mt-2">
                                                <input class="form-check-input" type="checkbox" name="chatbot_enabled" id="chatbot_enabled" value="1" @checked($chatbotEnabled)>
                                                <label class="form-check-label" for="chatbot_enabled">@lang('Enable AI Chatbot Widget')</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-bold">@lang('Bot Display Name')</label>
                                            <input type="text" class="form-control" name="bot_name" value="{{ $chatbotSettings['bot_name'] ?? 'VayroBot' }}" required>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-group">
                                            <label class="fw-bold">@lang('Welcome Message')</label>
                                            <input type="text" class="form-control" name="welcome_message" value="{{ $chatbotSettings['welcome_message'] ?? 'Hello! How can I help you today?' }}" required>
                                        </div>
                                    </div>
                                </div>

                                <hr>

                                <div class="form-group mb-3">
                                    <label class="fw-bold">@lang('Active AI Provider')</label>
                                    <select name="active_provider" id="active_provider" class="form-control select2" data-minimum-results-for-search="-1">
                                        <option value="gemini" @selected(($chatbotSettings['active_provider'] ?? '') == 'gemini')>@lang('Google Gemini API')</option>
                                        <option value="openai" @selected(($chatbotSettings['active_provider'] ?? '') == 'openai')>@lang('OpenAI ChatGPT API')</option>
                                        <option value="grok" @selected(($chatbotSettings['active_provider'] ?? '') == 'grok')>@lang('xAI Grok API')</option>
                                        <option value="nvidia" @selected(($chatbotSettings['active_provider'] ?? '') == 'nvidia')>@lang('Nvidia NIM API')</option>
                                        <option value="custom" @selected(($chatbotSettings['active_provider'] ?? '') == 'custom')>@lang('Custom OpenAI-Compatible Endpoint')</option>
                                    </select>
                                </div>

                                <!-- Provider API Configurations -->
                                <!-- 1. Gemini -->
                                <div class="provider-config d-none" id="config-gemini">
                                    <h6 class="mb-3 text--primary"><i class="las la-key"></i> @lang('Google Gemini Credentials')</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="form-group">
                                                <label>@lang('Gemini API Key')</label>
                                                <input type="password" class="form-control" name="api_key_gemini" value="{{ $chatbotSettings['api_key']['gemini'] ?? '' }}">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-group">
                                                <label>@lang('Gemini Model Name')</label>
                                                <input type="text" class="form-control" name="model_name_gemini" value="{{ $chatbotSettings['model_name']['gemini'] ?? 'gemini-1.5-flash' }}" placeholder="gemini-1.5-flash">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 2. OpenAI -->
                                <div class="provider-config d-none" id="config-openai">
                                    <h6 class="mb-3 text--primary"><i class="las la-key"></i> @lang('OpenAI ChatGPT Credentials')</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="form-group">
                                                <label>@lang('OpenAI API Key')</label>
                                                <input type="password" class="form-control" name="api_key_openai" value="{{ $chatbotSettings['api_key']['openai'] ?? '' }}">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-group">
                                                <label>@lang('OpenAI Model Name')</label>
                                                <input type="text" class="form-control" name="model_name_openai" value="{{ $chatbotSettings['model_name']['openai'] ?? 'gpt-4o-mini' }}" placeholder="gpt-4o-mini">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. Grok -->
                                <div class="provider-config d-none" id="config-grok">
                                    <h6 class="mb-3 text--primary"><i class="las la-key"></i> @lang('xAI Grok Credentials')</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="form-group">
                                                <label>@lang('Grok API Key')</label>
                                                <input type="password" class="form-control" name="api_key_grok" value="{{ $chatbotSettings['api_key']['grok'] ?? '' }}">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-group">
                                                <label>@lang('Grok Model Name')</label>
                                                <input type="text" class="form-control" name="model_name_grok" value="{{ $chatbotSettings['model_name']['grok'] ?? 'grok-beta' }}" placeholder="grok-beta">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 4. Nvidia NIM -->
                                <div class="provider-config d-none" id="config-nvidia">
                                    <h6 class="mb-3 text--primary"><i class="las la-key"></i> @lang('Nvidia NIM Credentials')</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="form-group">
                                                <label>@lang('Nvidia API Key')</label>
                                                <input type="password" class="form-control" name="api_key_nvidia" value="{{ $chatbotSettings['api_key']['nvidia'] ?? '' }}">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-group">
                                                <label>@lang('Nvidia Model Name')</label>
                                                <input type="text" class="form-control" name="model_name_nvidia" value="{{ $chatbotSettings['model_name']['nvidia'] ?? 'nvidia/llama-3.1-nemotron-70b-instruct' }}" placeholder="nvidia/llama-3.1-nemotron-70b-instruct">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 5. Custom -->
                                <div class="provider-config d-none" id="config-custom">
                                    <h6 class="mb-3 text--primary"><i class="las la-key"></i> @lang('Custom Endpoint Credentials')</h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="form-group">
                                                <label>@lang('API Key')</label>
                                                <input type="password" class="form-control" name="api_key_custom" value="{{ $chatbotSettings['api_key']['custom'] ?? '' }}">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="form-group">
                                                <label>@lang('Model Name')</label>
                                                <input type="text" class="form-control" name="model_name_custom" value="{{ $chatbotSettings['model_name']['custom'] ?? 'default' }}">
                                            </div>
                                        </div>
                                        <div class="col-12 mb-3">
                                            <div class="form-group">
                                                <label>@lang('API Endpoint URL (Base URL)')</label>
                                                <input type="url" class="form-control" name="custom_url" value="{{ $chatbotSettings['custom_url'] ?? '' }}" placeholder="https://api.yourprovider.com/v1/chat/completions">
                                            </div>
                                        </div>
                                </div>

                                <hr>

                                <h6 class="mb-3 text--primary"><i class="lab la-facebook-messenger"></i> @lang('Facebook Webhook Integration')</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-bold">@lang('Facebook Webhook Verify Token')</label>
                                            <input type="text" class="form-control" name="facebook_verify_token" value="{{ $chatbotSettings['facebook_verify_token'] ?? '' }}" placeholder="VayromartFBVerifyToken">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-bold">@lang('Facebook Page Access Token')</label>
                                            <input type="password" class="form-control" name="facebook_page_access_token" value="{{ $chatbotSettings['facebook_page_access_token'] ?? '' }}">
                                        </div>
                                    </div>
                                </div>

                                <hr>

                                <h6 class="mb-3 text--primary"><i class="las la-table"></i> @lang('Google Sheets OAuth Sync Settings')</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-bold">@lang('Google Sheets API Client ID')</label>
                                            <input type="text" class="form-control" name="google_client_id" value="{{ $chatbotSettings['google_client_id'] ?? '' }}" placeholder="575075009929-....apps.googleusercontent.com">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-bold">@lang('Google Sheets API Client Secret')</label>
                                            <input type="password" class="form-control" name="google_client_secret" value="{{ $chatbotSettings['google_client_secret'] ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group">
                                            <label class="fw-bold">@lang('Google Spreadsheet ID / Document URL')</label>
                                            <input type="text" class="form-control" name="google_spreadsheet_id" value="{{ $chatbotSettings['google_spreadsheet_id'] ?? '' }}" placeholder="Enter spreadsheet ID or full URL">
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="form-group">
                                            <label class="fw-bold">@lang('Sheet Name')</label>
                                            <input type="text" class="form-control" name="google_sheet_name" value="{{ $chatbotSettings['google_sheet_name'] ?? 'Sheet1' }}" placeholder="Sheet1">
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="form-group">
                                            <label class="fw-bold">@lang('Enable Google Sheet Sync')</label>
                                            <div class="form-check form-switch mt-2">
                                                <input class="form-check-input" type="checkbox" name="google_sheet_sync_enabled" id="google_sheet_sync_enabled" value="1" @checked($chatbotSettings['google_sheet_sync_enabled'] ?? false)>
                                                <label class="form-check-label" for="google_sheet_sync_enabled">@lang('Auto-Sync')</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="alert alert-info">
                                            <strong>@lang('OAuth Redirect URI (Google Console):')</strong><br>
                                            <code>{{ route('admin.setting.chatbot.google.callback') }}</code>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3 d-flex align-items-center flex-wrap gap-2">
                                    @if(empty($chatbotSettings['google_refresh_token']))
                                        @if(!empty($chatbotSettings['google_client_id']))
                                            <a href="{{ route('admin.setting.chatbot.google.redirect') }}" class="btn btn-sm btn--warning text-dark">
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

                                <div class="form-group mb-4">
                                    <label class="fw-bold">@lang('System Instructions Prompt Override')</label>
                                    <textarea class="form-control" name="system_prompt" rows="5" placeholder="@lang('You can supply custom guidelines for your AI assistant here...')">{{ $chatbotSettings['system_prompt'] ?? '' }}</textarea>
                                    <small class="text-muted">@lang('Give your bot a custom voice, rules, or tell it how to act when asked tricky questions. Standard store name and links context are injected automatically.')</small>
                                </div>

                                <button type="submit" class="btn btn--primary w-100 h-45">@lang('Save Settings')</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tab 2: Chat History Logs -->
                <div class="tab-pane fade" id="logs" role="tabpanel">
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive--md table-responsive">
                                <table class="table table--light style--two">
                                    <thead>
                                        <tr>
                                            <th>@lang('Date')</th>
                                            <th>@lang('User/Session')</th>
                                            <th>@lang('IP Address')</th>
                                            <th>@lang('Messages')</th>
                                            <th>@lang('Action')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($conversations as $convo)
                                            <tr>
                                                <td>{{ $convo->updated_at->format('Y-m-d H:i') }}</td>
                                                <td>
                                                    @if($convo->user)
                                                        <a href="{{ route('admin.users.detail', $convo->user_id) }}" class="fw-bold text--primary">{{ $convo->user->username }}</a>
                                                    @else
                                                        <span class="badge badge--dark">@lang('Guest User')</span>
                                                    @endif
                                                </td>
                                                <td><code>{{ $convo->ip_address }}</code></td>
                                                <td><span class="badge bg-secondary">{{ $convo->messages_count }}</span></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline--primary view-transcript-btn" data-id="{{ $convo->id }}">
                                                        <i class="las la-eye"></i> @lang('View Transcript')
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">@lang('No chat conversations logged yet.')</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @if($conversations->hasPages())
                            <div class="card-footer py-4">
                                {{ paginateLinks($conversations) }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Tab 3: Knowledge Base FAQ -->
                <div class="tab-pane fade" id="knowledge" role="tabpanel">
                    <div class="row">
                        <!-- Add Knowledge / FAQ Form -->
                        <div class="col-xl-4 col-lg-5 mb-4">
                            <div class="card border--dark">
                                <div class="card-header bg--dark">
                                    <h5 class="text-white m-0" id="kb-form-title"><i class="las la-plus-circle"></i> @lang('Add QA Rule / Fact')</h5>
                                </div>
                                <div class="card-body">
                                    <form action="{{ route('admin.setting.chatbot.knowledge.store') }}" method="POST" id="kb-form">
                                        @csrf
                                        <input type="hidden" name="id" id="kb-id">
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">@lang('Question Keywords / Topic') <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="question" id="kb-question" required placeholder="e.g. delivery time, returns, location">
                                            <small class="text-muted">@lang('Enter terms the user might use to query this rule.')</small>
                                        </div>
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">@lang('Bot Answer (Fact Context)') <span class="text-danger">*</span></label>
                                            <textarea class="form-control" name="answer" id="kb-answer" rows="6" required placeholder="e.g. We deliver orders inside Dhaka in 24 hours, and outside Dhaka in 2-3 days."></textarea>
                                        </div>
                                        <div class="form-group mb-3">
                                            <label class="fw-bold">@lang('Status')</label>
                                            <select name="is_active" id="kb-status" class="form-control form-select">
                                                <option value="1">@lang('Active')</option>
                                                <option value="0">@lang('Inactive')</option>
                                            </select>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <button type="submit" class="btn btn--primary w-100 h-40">@lang('Save FAQ')</button>
                                            </div>
                                            <div class="col-6">
                                                <button type="button" class="btn btn--secondary w-100 h-40 d-none" id="kb-reset-btn">@lang('Cancel')</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Knowledge List -->
                        <div class="col-xl-8 col-lg-7">
                            <div class="card">
                                <div class="card-body p-0">
                                    <div class="table-responsive--md table-responsive">
                                        <table class="table table--light style--two">
                                            <thead>
                                                <tr>
                                                    <th style="width: 25%;">@lang('Question Keywords')</th>
                                                    <th style="width: 50%;">@lang('Answer Fact')</th>
                                                    <th style="width: 10%;">@lang('Status')</th>
                                                    <th style="width: 15%;">@lang('Action')</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($knowledges as $kb)
                                                    <tr>
                                                        <td class="fw-bold">{{ $kb->question }}</td>
                                                        <td class="text-wrap">{{ Str::limit($kb->answer, 120) }}</td>
                                                        <td>
                                                            @if($kb->is_active)
                                                                <span class="badge badge--success">@lang('Active')</span>
                                                            @else
                                                                <span class="badge badge--danger">@lang('Inactive')</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <div class="d-flex gap-1 justify-content-center">
                                                                <button type="button" class="btn btn-sm btn-outline--primary edit-kb-btn" 
                                                                        data-id="{{ $kb->id }}" 
                                                                        data-question="{{ $kb->question }}" 
                                                                        data-answer="{{ $kb->answer }}" 
                                                                        data-status="{{ $kb->is_active }}">
                                                                    <i class="las la-pen"></i>
                                                                </button>
                                                                <form action="{{ route('admin.setting.chatbot.knowledge.delete', $kb->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this FAQ?');">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-sm btn-outline--danger"><i class="las la-trash"></i></button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="4" class="text-center text-muted">@lang('No knowledge base rules added yet. Start training your bot!')</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                @if($knowledges->hasPages())
                                    <div class="card-footer py-4">
                                        {{ paginateLinks($knowledges) }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- VIEW TRANSCRIPT MODAL -->
    <div id="transcriptModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Chat Session Transcript')</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <div class="modal-body p-0" style="background: #f8f9fa;">
                    <!-- Message Transcript Viewer -->
                    <div id="chat-messages-container" style="max-height: 450px; overflow-y: auto; padding: 20px;">
                        <!-- messages loaded dynamically via Ajax -->
                    </div>
                </div>
                <div class="modal-footer bg-light d-flex justify-content-between">
                    <span class="small text-muted" id="modal-meta-info"></span>
                    <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Close')</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        (function($) {
            "use strict";

            // Provider Switcher Script
            const providerSelect = $('#active_provider');
            function switchProvider(provider) {
                $('.provider-config').addClass('d-none');
                $(`#config-${provider}`).removeClass('d-none');
            }

            providerSelect.on('change', function() {
                switchProvider($(this).val());
            });

            // Trigger on page load
            switchProvider(providerSelect.val());

            // Tab retention script (keeps current active tab on page refresh)
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                localStorage.setItem('activeChatbotTab', $(e.target).attr('id'));
            });
            const activeTab = localStorage.getItem('activeChatbotTab');
            if (activeTab) {
                const tabEl = document.getElementById(activeTab);
                if (tabEl) {
                    const tabInstance = bootstrap.Tab.getOrCreateInstance(tabEl);
                    tabInstance.show();
                }
            }

            // Edit Knowledge Base Rule Action
            $('.edit-kb-btn').on('click', function() {
                const id = $(this).data('id');
                const question = $(this).data('question');
                const answer = $(this).data('answer');
                const status = $(this).data('status');

                $('#kb-id').val(id);
                $('#kb-question').val(question);
                $('#kb-answer').val(answer);
                $('#kb-status').val(status);

                $('#kb-form-title').html('<i class="las la-pen"></i> Edit QA Rule / Fact');
                $('#kb-reset-btn').removeClass('d-none');
            });

            // Reset FAQ form to create state
            $('#kb-reset-btn').on('click', function() {
                $('#kb-id').val('');
                $('#kb-form')[0].reset();
                $('#kb-form-title').html('<i class="las la-plus-circle"></i> Add QA Rule / Fact');
                $(this).addClass('d-none');
            });

            // View transcript ajax action
            $(document).on('click', '.view-transcript-btn', function() {
                const id = $(this).data('id');
                const modal = $('#transcriptModal');
                const container = $('#chat-messages-container');
                const meta = $('#modal-meta-info');

                container.html('<div class="text-center py-5"><i class="las la-spinner la-spin fs-2 text--primary"></i><p class="mt-2 text-muted">Loading transcript...</p></div>');
                modal.modal('show');

                let absoluteUrl = "{{ route('admin.setting.chatbot.logs.view', ':id') }}".replace(':id', id).replace('%3Aid', id);
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
                        if (!response.success) {
                            container.html(`<div class="alert alert-danger">${response.message}</div>`);
                            return;
                        }

                        meta.text(`Session ID: ${response.session_id} | IP: ${response.ip_address} | User: ${response.user}`);
                        container.empty();

                        if (response.messages.length === 0) {
                            container.html('<div class="text-center text-muted py-4">No messages in this chat session.</div>');
                            return;
                        }

                        response.messages.forEach(function(msg) {
                            const isBot = msg.sender === 'bot';
                            const senderName = isBot ? 'Bot' : response.user;
                            const bubbleClass = isBot ? 'bg-white text-dark border' : 'bg--primary text-white';
                            const alignClass = isBot ? 'justify-content-start' : 'justify-content-end';
                            const floatStyle = isBot ? 'margin-right: 20%;' : 'margin-left: 20%;';
                            
                            // Training action button for user questions
                            let trainButtonHtml = '';
                            if (!isBot) {
                                trainButtonHtml = `
                                    <button class="btn btn-xs btn-outline-secondary mt-1 train-from-msg" 
                                            data-question="${msg.message.replace(/"/g, '&quot;')}" 
                                            style="font-size: 10px; padding: 2px 6px;">
                                        <i class="las la-brain text--primary"></i> Train Bot from this
                                    </button>
                                `;
                            }

                            const bubble = `
                                <div class="d-flex ${alignClass} mb-3">
                                    <div class="p-3 rounded-3 ${bubbleClass}" style="${floatStyle} max-width: 80%; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                                        <strong class="d-block small mb-1 ${isBot ? 'text-muted' : 'text-light'}">${senderName}</strong>
                                        <div style="white-space: pre-wrap; font-size: 13px; word-break: break-word;">${msg.message}</div>
                                        <span class="d-block text-end mt-1" style="font-size: 9px; opacity: 0.7;">${msg.time}</span>
                                        ${trainButtonHtml}
                                    </div>
                                </div>
                            `;
                            container.append(bubble);
                        });

                        // Training shortcut event handler
                        $('.train-from-msg').off('click').on('click', function() {
                            const question = $(this).data('question');
                            modal.modal('hide');
                            
                            // Switch tab to Knowledge Base
                            const kbTabEl = document.getElementById('knowledge-tab');
                            if (kbTabEl) {
                                const tabInstance = bootstrap.Tab.getOrCreateInstance(kbTabEl);
                                tabInstance.show();
                            }
                            
                            // Fill knowledge base form
                            $('#kb-question').val(question).focus();
                            $('#kb-answer').val('').attr('placeholder', 'Enter the ideal response for this question...');
                            
                            notify('info', 'Question copied. Type the answer to train the bot.');
                        });

                        // Scroll container to bottom
                        setTimeout(function() {
                            container.scrollTop(container[0].scrollHeight);
                        }, 200);
                    },
                    error: function() {
                        container.html('<div class="alert alert-danger">An error occurred while loading chat history.</div>');
                    }
                });
            });

            // Google Sheet Sync manual trigger
            $('#sync-sheet-btn').on('click', function() {
                const btn = $(this);
                const status = $('#sync-status');
                
                btn.prop('disabled', true).find('i').addClass('la-spin');
                status.text('Synchronizing product database with Google Sheet...').removeClass('text-danger text-success').addClass('text-muted');

                let absoluteUrl = "{{ route('admin.setting.chatbot.sync.sheet') }}";
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

        })(jQuery);
    </script>
@endpush

@push('style')
    <style>
        .nav-tabs .nav-link {
            border: none;
            padding: 12px 20px;
            font-weight: 500;
            border-bottom: 2px transparent solid;
            background: transparent !important;
            transition: all 0.2s ease;
        }
        .nav-tabs .nav-link.active {
            border-bottom-color: var(--bs-primary) !important;
            color: var(--bs-primary) !important;
        }
        .nav-tabs .nav-link:hover {
            opacity: 0.8;
        }
        .btn-xs {
            padding: 1px 5px;
            font-size: 10px;
            line-height: 1.5;
            border-radius: 3px;
        }
    </style>
@endpush
