@extends('admin.layouts.app')
@section('panel')
    <div class="sync-container">
        <!-- Tab Navigation -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card sync-tabs-card shadow-sm">
                    <div class="card-body p-2 bg-white rounded">
                        <ul class="nav nav-pills custom-nav-pills gap-2" id="syncTabs" role="tablist" style="border: none;">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="bulk-sync-tab" data-bs-toggle="tab" data-bs-target="#bulk-sync" type="button" role="tab" aria-controls="bulk-sync" aria-selected="true">
                                    <i class="las la-sync-alt"></i> @lang('Bulk Category Sync')
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="single-search-tab" data-bs-toggle="tab" data-bs-target="#single-search" type="button" role="tab" aria-controls="single-search" aria-selected="false">
                                    <i class="las la-search"></i> @lang('Search & Single Import')
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="link-scraper-tab" data-bs-toggle="tab" data-bs-target="#link-scraper" type="button" role="tab" aria-controls="link-scraper" aria-selected="false">
                                    <i class="las la-link"></i> @lang('Scrape & Import from Link')
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-content" id="syncTabsContent">
            <!-- Tab 1: Bulk Category Sync -->
            <div class="tab-pane fade show active" id="bulk-sync" role="tabpanel" aria-labelledby="bulk-sync-tab">
                <div class="row">
                    <!-- Configuration Form Card & Credentials Card -->
                    <div class="col-xl-5 col-lg-6">
                        <!-- API Credentials Card -->
                        <div class="card mb-4 premium-card border--dark">
                            <div class="card-header bg--dark-custom text-white">
                                <h5 class="text-white m-0 d-flex align-items-center gap-2">
                                    <i class="las la-key fs-4"></i> @lang('API Credentials Configuration')
                                </h5>
                            </div>
                            <div class="card-body p-4">
                                <form id="credentialsForm">
                                    @csrf
                                    <div class="form-group mb-3">
                                        <label class="fw-bold mb-1">@lang('API Key') <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="api_key" id="api_key" value="{{ $apiKey }}" required placeholder="@lang('Enter Mohasagor API Key')">
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="fw-bold mb-1">@lang('Secret Key') <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" name="secret_key" id="secret_key" value="{{ $secretKey }}" required placeholder="@lang('Enter Mohasagor Secret Key')">
                                            <button class="btn btn-outline-secondary" type="button" id="toggle-secret-btn" style="border-color: #ced4da;"><i class="las la-eye"></i></button>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-custom-primary w-100 h-40" id="save-credentials-btn">
                                        <i class="las la-save"></i> @lang('Save Credentials')
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Configuration Form Card -->
                        <div class="card premium-card border--primary">
                            <div class="card-header bg--primary-custom text-white">
                                <h5 class="text-white m-0 d-flex align-items-center gap-2">
                                    <i class="las la-cog fs-4"></i> @lang('Advanced Sync Configuration')
                                </h5>
                            </div>
                            <div class="card-body p-4">
                                <form id="syncForm">
                                    @csrf
                                    <!-- Mohasagor Category -->
                                    <div class="form-group mb-3">
                                        <label class="fw-bold mb-1">@lang('Select API Category') <span class="text-danger">*</span></label>
                                        <select class="form-control form-select select2-basic" name="category" id="category" required>
                                            <option value="" disabled selected>@lang('Choose a category...')</option>
                                            @foreach($mohasagorCategories as $catName => $stats)
                                                <option value="{{ $catName }}">{{ $catName }} ({{ $stats['total'] }} @lang('products') - {{ $stats['available'] }} @lang('in stock'), {{ $stats['out_of_stock'] }} @lang('out of stock'))</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <!-- Local Category Mapping -->
                                    <div class="form-group mb-3">
                                        <label class="fw-bold mb-1">@lang('Map to Local Category')</label>
                                        <select class="form-control form-select select2-basic" name="local_category_id" id="local_category_id">
                                            <option value="">@lang('Auto-Create / Match from API Category')</option>
                                            @foreach($categories as $localCat)
                                                <option value="{{ $localCat->id }}">{{ __($localCat->name) }}</option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">@lang('Specify a local category to force all imported products into it.')</small>
                                    </div>

                                    <!-- Price Markup Setup -->
                                    <div class="form-group mb-3">
                                        <label class="fw-bold mb-1">@lang('Pricing Markup / Margin')</label>
                                        <div class="input-group">
                                            <select class="form-control form-select" style="max-width: 120px;" name="price_markup_type" id="price_markup_type">
                                                <option value="none">@lang('None')</option>
                                                <option value="percent">@lang('Percent (%)')</option>
                                                <option value="flat">@lang('Flat BDT')</option>
                                            </select>
                                            <input type="number" class="form-control" name="price_markup_value" id="price_markup_value" placeholder="0.00" min="0" step="0.01">
                                        </div>
                                        <small class="text-muted">@lang('Add profit margins automatically to the API product price.')</small>
                                    </div>

                                    <!-- Regular Price Markup Setup -->
                                    <div class="form-group mb-3">
                                        <label class="fw-bold mb-1">@lang('Regular Price Markup / Original Price (%)')</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="regular_price_markup" id="regular_price_markup" placeholder="e.g. 10" min="0" step="0.1">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <small class="text-muted">@lang('Set regular price higher than selling price by this percentage to show a discount. If empty/0, regular price will equal selling price.')</small>
                                    </div>

                                    <!-- Limit & Status -->
                                    <div class="row">
                                        <div class="col-md-6 col-12 mb-3">
                                            <label class="fw-bold mb-1">@lang('Import Limit') <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" name="limit" id="limit" value="50" min="1" max="1000" required>
                                        </div>
                                        <div class="col-md-6 col-12 mb-3">
                                            <label class="fw-bold mb-1">@lang('Default Status')</label>
                                            <select class="form-control form-select" name="publish_status" id="publish_status">
                                                <option value="1">@lang('Published')</option>
                                                <option value="0">@lang('Draft')</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Default Stock for Out-of-Stock Products -->
                                    <div class="form-group mb-3">
                                        <label class="fw-bold mb-1">@lang('Default Stock for Out-of-Stock Products') <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="out_of_stock_default_qty" id="out_of_stock_default_qty" value="100" min="0" required>
                                        <small class="text-muted">@lang('Out-of-stock products from the API will be imported with this local stock quantity.')</small>
                                    </div>

                                    <!-- Update Existing -->
                                    <div class="form-group mb-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="update_existing" id="update_existing" checked style="cursor: pointer;">
                                            <label class="form-check-label fw-bold" for="update_existing" style="cursor: pointer;">@lang('Update Existing Products')</label>
                                        </div>
                                        <small class="text-muted">@lang('Overwrite/update details of existing matching SKUs.')</small>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <button type="button" class="btn btn-custom-primary w-100 h-45 d-flex align-items-center justify-content-center gap-2" id="start-sync-btn">
                                                <i class="las la-play"></i> @lang('Start Synchronization')
                                            </button>
                                        </div>
                                        <div class="col-6 d-none" id="pause-container">
                                            <button type="button" class="btn btn--warning w-100 h-40 d-flex align-items-center justify-content-center gap-2" id="pause-sync-btn">
                                                <i class="las la-pause"></i> @lang('Pause')
                                            </button>
                                        </div>
                                        <div class="col-6 d-none" id="stop-container">
                                            <button type="button" class="btn btn--danger w-100 h-40 d-flex align-items-center justify-content-center gap-2" id="stop-sync-btn">
                                                <i class="las la-stop"></i> @lang('Stop')
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Live Sync & Analytics Card -->
                    <div class="col-xl-7 col-lg-6 mt-lg-0 mt-4">
                        <!-- Progress Status Card -->
                        <div class="card h-100 premium-card d-none" id="progress-card" style="background: #0f172a !important; color: #fff;">
                            <div class="card-header bg--dark-custom text-white d-flex justify-content-between align-items-center" style="border-bottom: 1px solid #1e293b;">
                                <h5 class="text-white m-0 d-flex align-items-center gap-2"><i class="las la-tasks"></i> @lang('Live Sync Progress')</h5>
                                <span class="badge bg-primary px-3 py-2" id="time-elapsed-badge" style="font-size: 11px;">00:00</span>
                            </div>
                            <div class="card-body p-4 d-flex flex-column" style="min-height: 380px;">
                                <div class="progress-wrapper mb-4">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="fw-bold text-info" id="sync-status-text">@lang('Initializing...')</span>
                                        <span class="fw-bold text-info" id="sync-percentage">0%</span>
                                    </div>
                                    <div class="progress" style="height: 12px; background-color: #1e293b; border-radius: 30px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-info" role="progressbar" style="width: 0%; border-radius: 30px;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>

                                <!-- Statistics Dashboard -->
                                <div class="row g-2 mb-4">
                                    <div class="col-3">
                                        <div class="border rounded p-3 text-center" style="background: #1e293b; border-color: #334155 !important;">
                                            <span class="text-muted d-block small text-uppercase" style="font-size: 10px;">@lang('Target')</span>
                                            <strong class="fs-5 text-white" id="stat-target">0</strong>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="border rounded p-3 text-center" style="background: #1e293b; border-color: #334155 !important;">
                                            <span class="text-success d-block small text-uppercase" style="font-size: 10px;">@lang('Created')</span>
                                            <strong class="fs-5 text-success" id="stat-created">0</strong>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="border rounded p-3 text-center" style="background: #1e293b; border-color: #334155 !important;">
                                            <span class="text-info d-block small text-uppercase" style="font-size: 10px;">@lang('Updated')</span>
                                            <strong class="fs-5 text-info" id="stat-updated">0</strong>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="border rounded p-3 text-center" style="background: #1e293b; border-color: #334155 !important;">
                                            <span class="text-warning d-block small text-uppercase" style="font-size: 10px;">@lang('Skipped')</span>
                                            <strong class="fs-5 text-warning" id="stat-skipped">0</strong>
                                        </div>
                                    </div>
                                </div>

                                <!-- Terminal Log Console -->
                                <label class="fw-bold mb-1 text-white"><i class="las la-terminal text-info"></i> @lang('Sync Log Console')</label>
                                <div class="terminal-wrapper mb-4">
                                    <div class="terminal-header d-flex align-items-center justify-content-between px-3 py-2" style="background: #1e293b; border-bottom: 1px solid #334155;">
                                        <div class="d-flex gap-2 align-items-center">
                                            <span class="terminal-dot dot-red"></span>
                                            <span class="terminal-dot dot-yellow"></span>
                                            <span class="terminal-dot dot-green"></span>
                                            <span class="text-muted ms-2 fw-bold" style="font-size: 11px; font-family: monospace;">mohasagor-sync-cli</span>
                                        </div>
                                        <span class="badge bg-secondary-custom text-white" style="font-size: 9px; text-transform: uppercase;">Active Session</span>
                                    </div>
                                    <div id="log-console" class="p-3 text-light font-monospace terminal-log" style="height: 140px; overflow-y: auto; font-size: 11px; line-height: 1.4; margin-bottom: 0; border-radius: 0 0 12px 12px; border: none !important;">
                                    </div>
                                </div>

                                <!-- Live Imported Product Table -->
                                <label class="fw-bold mb-1 text-white"><i class="las la-images text-info"></i> @lang('Recently Synced Products')</label>
                                <div class="premium-table-wrapper border" style="border-color: #1e293b !important; max-height: 150px; overflow-y: auto; background: #1e293b;">
                                    <table class="table premium-table m-0" style="font-size: 11px; background: transparent; color: #fff;">
                                        <thead class="sticky-top" style="background: #1e293b; border-bottom: 2px solid #334155;">
                                            <tr>
                                                <th style="background: #1e293b; color: #94a3b8; border: none;">@lang('Image')</th>
                                                <th style="background: #1e293b; color: #94a3b8; border: none;">@lang('Product Name')</th>
                                                <th style="background: #1e293b; color: #94a3b8; border: none;">@lang('SKU')</th>
                                                <th style="background: #1e293b; color: #94a3b8; border: none;">@lang('Price')</th>
                                                <th style="background: #1e293b; color: #94a3b8; border: none;">@lang('Status')</th>
                                            </tr>
                                        </thead>
                                        <tbody id="live-products-body" style="color: #cbd5e1;">
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4 border-0">@lang('Waiting for products...')</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Category Products Selector Card -->
                        <div class="card h-100 premium-card d-none" id="selector-card">
                            <div class="card-header bg--primary-custom text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h5 class="text-white m-0 d-flex align-items-center gap-2"><i class="las la-list fs-4"></i> @lang('Products in API Category')</h5>
                                <span class="badge bg-white text-dark py-2 px-3 fw-bold" id="selector-category-name" style="font-size: 12px; border-radius: 30px;"></span>
                            </div>
                            <div class="card-body p-4 d-flex flex-column" style="min-height: 380px;">
                                <!-- Filters and Sorting Grid -->
                                <div class="row g-2 mb-3">
                                    <div class="col-sm-5 col-12">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-white" style="border-color: #ced4da;"><i class="las la-search text-muted"></i></span>
                                            <input type="text" class="form-control" id="selector-search-input" placeholder="@lang('Filter products by name or SKU...')">
                                        </div>
                                    </div>
                                    <div class="col-sm-4 col-6">
                                        <select class="form-select form-select-sm" id="selector-sort-select">
                                            <option value="default">@lang('Default Order')</option>
                                            <option value="price_low">@lang('Price: Low to High')</option>
                                            <option value="price_high">@lang('Price: High to Low')</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-3 col-6">
                                        <select class="form-select form-select-sm" id="selector-status-select">
                                            <option value="all">@lang('All Status')</option>
                                            <option value="not_imported">@lang('Not Imported')</option>
                                            <option value="imported">@lang('Imported')</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Table of products -->
                                <div class="premium-table-wrapper border flex-grow-1 mb-3" style="max-height: 250px; overflow-y: auto;">
                                    <table class="table premium-table m-0">
                                        <thead class="sticky-top bg-light">
                                            <tr>
                                                <th style="width: 45px; text-align: center; vertical-align: middle;">
                                                    <input class="form-check-input" type="checkbox" id="selector-select-all" style="cursor: pointer;">
                                                </th>
                                                <th>@lang('Image')</th>
                                                <th>@lang('Product Name')</th>
                                                <th>@lang('SKU')</th>
                                                <th>@lang('Price')</th>
                                                <th>@lang('Status')</th>
                                            </tr>
                                        </thead>
                                        <tbody id="selector-products-body">
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">@lang('Select a category to view products.')</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Selection Summary and Actions -->
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top">
                                    <span class="fw-bold text--dark fs-6" id="selector-count-text">0 @lang('products selected')</span>
                                    <button type="button" class="btn btn-custom-primary btn-sm px-4 d-flex align-items-center gap-2" id="selector-import-btn" disabled style="height: 38px;">
                                        <i class="las la-download"></i> @lang('Import Selected')
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Pre-sync Guide Card -->
                        <div class="card h-100 premium-card" id="info-card">
                            <div class="card-header bg-info text-white">
                                <h5 class="text-white m-0 d-flex align-items-center gap-2"><i class="las la-info-circle fs-4"></i> @lang('API Sync Guide')</h5>
                            </div>
                            <div class="card-body p-4">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 pt-0 pb-3">
                                        <span class="fw-bold text-secondary"><i class="las la-key text--primary fs-5"></i> @lang('API Connection Status')</span>
                                        <span class="badge bg-dark px-3 py-2 fw-bold" id="api-status-badge">@lang('Checking...')</span>
                                    </li>
                                    <li class="list-group-item bg-transparent px-0 py-3">
                                        <h6 class="mb-3 text-dark font-weight-bold"><i class="las la-sliders-h text-primary"></i> @lang('Advanced Options Description:')</h6>
                                        <div class="d-flex flex-column gap-2">
                                            <p class="small text-muted mb-1">
                                                <strong>1. @lang('Local Category Mapping:')</strong> @lang('Allows you to force all selected products from the API to go directly into a specific category in your shop instead of creating/using the category name from the API.')
                                            </p>
                                            <p class="small text-muted mb-1">
                                                <strong>2. @lang('Pricing Markup / Margin:')</strong> @lang('Set a markup to automatically increase the selling price on your site. E.g., setting a 15% markup on a 100 BDT product will store it in your shop for 115 BDT.')
                                            </p>
                                            <p class="small text-muted mb-1">
                                                <strong>3. @lang('Default Status:')</strong> @lang('Choose whether you want to publish the synced products immediately or save them as Drafts so you can review them first.')
                                            </p>
                                            <p class="small text-muted">
                                                <strong>4. @lang('Default Stock for Out-of-Stock Products:')</strong> @lang('Set the default stock quantity for products that are out of stock on the API. This allows you to import and list them with a custom stock level (e.g. 100).')
                                            </p>
                                        </div>
                                    </li>
                                    <li class="list-group-item bg-transparent px-0 pt-3 pb-0 border-0">
                                        <div class="alert alert-warning border-0 p-3 d-flex align-items-start gap-2 mb-0" style="border-radius: 10px;">
                                            <i class="las la-exclamation-triangle fs-4 text-warning"></i>
                                            <div>
                                                <strong class="text-warning-dark">@lang('Safety Warning:')</strong>
                                                <p class="small text-muted m-0 mt-1">
                                                    @lang('Do not close this window or reload the browser while the synchronization is active. You can safely pause or stop the process at any point using the control buttons.')
                                                </p>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Single Search & Import -->
            <div class="tab-pane fade" id="single-search" role="tabpanel" aria-labelledby="single-search-tab">
                <div class="row">
                    <!-- Left Column: Single Import Config -->
                    <div class="col-xl-4 col-lg-5">
                        <div class="card premium-card border--primary">
                            <div class="card-header bg--primary-custom text-white">
                                <h5 class="text-white m-0 d-flex align-items-center gap-2"><i class="las la-sliders-h fs-4"></i> @lang('Single Import Configuration')</h5>
                            </div>
                            <div class="card-body p-4">
                                <form id="singleImportConfigForm">
                                    <!-- Local Category Mapping -->
                                    <div class="form-group mb-3">
                                        <label class="fw-bold mb-1">@lang('Map to Local Category')</label>
                                        <select class="form-control form-select select2-basic" name="single_local_category_id" id="single_local_category_id">
                                            <option value="">@lang('Auto-Create / Match from API Category')</option>
                                            @foreach($categories as $localCat)
                                                <option value="{{ $localCat->id }}">{{ __($localCat->name) }}</option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">@lang('Specify a local category to force the imported product into it.')</small>
                                    </div>

                                    <!-- Price Markup Setup -->
                                    <div class="form-group mb-3">
                                        <label class="fw-bold mb-1">@lang('Pricing Markup / Margin')</label>
                                        <div class="input-group">
                                            <select class="form-control form-select" style="max-width: 120px;" name="single_price_markup_type" id="single_price_markup_type">
                                                <option value="none">@lang('None')</option>
                                                <option value="percent">@lang('Percent (%)')</option>
                                                <option value="flat">@lang('Flat BDT')</option>
                                            </select>
                                            <input type="number" class="form-control" name="single_price_markup_value" id="single_price_markup_value" placeholder="0.00" min="0" step="0.01">
                                        </div>
                                        <small class="text-muted">@lang('Add profit margins automatically to the product price.')</small>
                                    </div>

                                    <!-- Regular Price Markup Setup -->
                                    <div class="form-group mb-3">
                                        <label class="fw-bold mb-1">@lang('Regular Price Markup / Original Price (%)')</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="single_regular_price_markup" id="single_regular_price_markup" placeholder="e.g. 10" min="0" step="0.1">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <small class="text-muted">@lang('Set regular price higher than selling price by this percentage to show a discount.')</small>
                                    </div>

                                    <!-- Default Status -->
                                    <div class="form-group mb-3">
                                        <label class="fw-bold mb-1">@lang('Default Status')</label>
                                        <select class="form-control form-select" name="single_publish_status" id="single_publish_status">
                                            <option value="1">@lang('Published')</option>
                                            <option value="0">@lang('Draft')</option>
                                        </select>
                                    </div>

                                    <!-- Default Stock for Out-of-Stock Products -->
                                    <div class="form-group mb-3">
                                        <label class="fw-bold mb-1">@lang('Default Stock for Out-of-Stock Products')</label>
                                        <input type="number" class="form-control" name="single_out_of_stock_default_qty" id="single_out_of_stock_default_qty" value="100" min="0" required>
                                        <small class="text-muted">@lang('Used if the product is reported out of stock on the API.')</small>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Product Search & Results Grid -->
                    <div class="col-xl-8 col-lg-7 mt-lg-0 mt-4">
                        <div class="card h-100 premium-card" id="search-card">
                            <div class="card-header bg--dark-custom text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h5 class="text-white m-0 d-flex align-items-center gap-2"><i class="las la-search fs-4"></i> @lang('Search API Products')</h5>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-secondary p-2 d-none d-sm-inline-block" id="catalog-cache-badge" style="border-radius: 8px;">
                                        @lang('Cache updated: ') <span id="catalog-cache-time">@lang('Loading...')</span>
                                    </span>
                                    <button type="button" class="btn btn-sm btn-custom-outline" id="refresh-catalog-btn" style="background: #fff; color: #4f46e5; border-color: #e0e7ff;">
                                        <i class="las la-sync"></i> @lang('Refresh Catalog')
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <!-- Search Form -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="search-input" placeholder="@lang('Search by Product Name, SKU (MHS-XX) or Category...')">
                                            <button class="btn btn-custom-primary" type="button" id="search-btn" style="border-radius: 0 10px 10px 0;">
                                                <i class="las la-search"></i> @lang('Search')
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Results Table -->
                                <div class="premium-table-wrapper border" style="min-height: 300px; max-height: 500px; overflow-y: auto;">
                                    <table class="table premium-table m-0">
                                        <thead class="sticky-top bg-light">
                                            <tr>
                                                <th>@lang('Image')</th>
                                                <th>@lang('Name')</th>
                                                <th>@lang('SKU')</th>
                                                <th>@lang('Category')</th>
                                                <th>@lang('Price')</th>
                                                <th>@lang('Stock')</th>
                                                <th>@lang('Status')</th>
                                                <th>@lang('Action')</th>
                                            </tr>
                                        </thead>
                                        <tbody id="search-results-body">
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-5">
                                                    <i class="las la-search fs-2 d-block mb-2 text-secondary"></i>
                                                    @lang('Enter a search term or click Search to view catalog')
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Scrape & Import from Link -->
            <div class="tab-pane fade" id="link-scraper" role="tabpanel" aria-labelledby="link-scraper-tab">
                <div class="row">
                    <!-- Left Column: Scraper Config -->
                    <div class="col-xl-5 col-lg-6">
                        <div class="card premium-card border--primary">
                            <div class="card-header bg--primary-custom text-white">
                                <h5 class="text-white m-0 d-flex align-items-center gap-2">
                                    <i class="las la-link fs-4"></i> @lang('URL Scraper Configuration')
                                </h5>
                            </div>
                            <div class="card-body p-4">
                                <form id="scraperConfigForm">
                                    @csrf
                                    <!-- URL Input field -->
                                    <div class="form-group mb-3">
                                        <label class="fw-bold mb-1">@lang('Mohasagor Page URL') <span class="text-danger">*</span></label>
                                        <input type="url" class="form-control" name="scrape_url" id="scrape_url" required placeholder="e.g. https://mohasagor.com.bd/category/3c-accessories">
                                        <small class="text-muted">@lang('Paste any Mohasagor shop, category, search, or product detail page URL.')</small>
                                    </div>

                                    <!-- Map to Local Category -->
                                    <div class="form-group mb-3">
                                        <label class="fw-bold mb-1">@lang('Map to Local Category')</label>
                                        <select class="form-control form-select select2-basic" name="scraper_local_category_id" id="scraper_local_category_id">
                                            <option value="">@lang('Auto-Create / Match from API Category')</option>
                                            @foreach($categories as $localCat)
                                                <option value="{{ $localCat->id }}">{{ __($localCat->name) }}</option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">@lang('Force all scraped products into this local category.')</small>
                                    </div>

                                    <!-- Price Markup Setup -->
                                    <div class="form-group mb-3">
                                        <label class="fw-bold mb-1">@lang('Pricing Markup / Margin')</label>
                                        <div class="input-group">
                                            <select class="form-control form-select" style="max-width: 120px;" name="scraper_price_markup_type" id="scraper_price_markup_type">
                                                <option value="none">@lang('None')</option>
                                                <option value="percent">@lang('Percent (%)')</option>
                                                <option value="flat">@lang('Flat BDT')</option>
                                            </select>
                                            <input type="number" class="form-control" name="scraper_price_markup_value" id="scraper_price_markup_value" placeholder="0.00" min="0" step="0.01">
                                        </div>
                                        <small class="text-muted">@lang('Add profit margins automatically to the product price.')</small>
                                    </div>

                                    <!-- Regular Price Markup Setup -->
                                    <div class="form-group mb-3">
                                        <label class="fw-bold mb-1">@lang('Regular Price Markup / Original Price (%)')</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="scraper_regular_price_markup" id="scraper_regular_price_markup" placeholder="e.g. 10" min="0" step="0.1">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <small class="text-muted">@lang('Set regular price higher than selling price by this percentage to show a discount.')</small>
                                    </div>

                                    <!-- Stock & Status -->
                                    <div class="row">
                                        <div class="col-md-6 col-12 mb-3">
                                            <label class="fw-bold mb-1">@lang('Default Status')</label>
                                            <select class="form-control form-select" name="scraper_publish_status" id="scraper_publish_status">
                                                <option value="1">@lang('Published')</option>
                                                <option value="0">@lang('Draft')</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 col-12 mb-3">
                                            <label class="fw-bold mb-1">@lang('Default Stock')</label>
                                            <input type="number" class="form-control" name="scraper_out_of_stock_default_qty" id="scraper_out_of_stock_default_qty" value="100" min="0" required>
                                        </div>
                                    </div>

                                    <button type="button" class="btn btn-custom-primary w-100 h-45 d-flex align-items-center justify-content-center gap-2" id="start-scan-btn">
                                        <i class="las la-search-plus"></i> @lang('Scan & Extract Products')
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Scanned Products Review & Action -->
                    <div class="col-xl-7 col-lg-6 mt-lg-0 mt-4">
                        <!-- Scraper Guide Panel (shown initially) -->
                        <div class="card h-100 premium-card" id="scraper-guide-card">
                            <div class="card-header bg-info text-white">
                                <h5 class="text-white m-0 d-flex align-items-center gap-2"><i class="las la-info-circle fs-4"></i> @lang('URL Scraper Instructions')</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="alert alert-info border-0 p-3 mb-3" style="border-radius: 10px;">
                                    <p class="small m-0">
                                        <strong>@lang('How it works:')</strong> Paste a Mohasagor URL in the input field on the left. The system will crawl the page, discover all linked products, match them with our live API database, and load them in a review checklist so you can import or update them.
                                    </p>
                                </div>
                                <ul class="list-group list-group-flush gap-2" style="font-size: 13px;">
                                    <li class="list-group-item bg-transparent px-0 border-0">
                                        <strong>1. Category URL Scraping:</strong> Scan pages like <code>https://mohasagor.com.bd/category/mens-fashion</code> to load all products under that category.
                                    </li>
                                    <li class="list-group-item bg-transparent px-0 border-0">
                                        <strong>2. Search URL Scraping:</strong> Scan search results like <code>https://mohasagor.com.bd/search?q=tshirt</code>.
                                    </li>
                                    <li class="list-group-item bg-transparent px-0 border-0">
                                        <strong>3. Single Product Scraping:</strong> Scan a single detail page to preview and import a single product instantly.
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Scraper Products Preview Selector Card (shown after scanning) -->
                        <div class="card h-100 premium-card d-none" id="scraper-results-card">
                            <div class="card-header bg-primary-custom text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h5 class="text-white m-0 d-flex align-items-center gap-2"><i class="las la-list fs-4"></i> @lang('Scanned Products Review')</h5>
                                <span class="badge bg-white text-dark py-2 px-3 fw-bold" id="scanned-count-badge" style="font-size: 12px; border-radius: 30px;">0 found</span>
                            </div>
                            <div class="card-body p-4 d-flex flex-column" style="min-height: 380px;">
                                <!-- Filters and Sorting Grid -->
                                <div class="row g-2 mb-3">
                                    <div class="col-sm-5 col-12">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-white" style="border-color: #ced4da;"><i class="las la-search text-muted"></i></span>
                                            <input type="text" class="form-control" id="scraper-search-input" placeholder="@lang('Filter scanned list...')">
                                        </div>
                                    </div>
                                    <div class="col-sm-4 col-6">
                                        <select class="form-select form-select-sm" id="scraper-sort-select">
                                            <option value="default">@lang('Default Order')</option>
                                            <option value="price_low">@lang('Price: Low to High')</option>
                                            <option value="price_high">@lang('Price: High to Low')</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-3 col-6">
                                        <select class="form-select form-select-sm" id="scraper-status-select">
                                            <option value="all">@lang('All Status')</option>
                                            <option value="not_imported">@lang('Not Imported')</option>
                                            <option value="imported">@lang('Imported')</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Table of products -->
                                <div class="premium-table-wrapper border flex-grow-1 mb-3" style="max-height: 250px; overflow-y: auto;">
                                    <table class="table premium-table m-0">
                                        <thead class="sticky-top bg-light">
                                            <tr>
                                                <th style="width: 45px; text-align: center; vertical-align: middle;">
                                                    <input class="form-check-input" type="checkbox" id="scraper-select-all" style="cursor: pointer;">
                                                </th>
                                                <th>@lang('Image')</th>
                                                <th>@lang('Product Name')</th>
                                                <th>@lang('SKU')</th>
                                                <th>@lang('Price')</th>
                                                <th>@lang('Status')</th>
                                            </tr>
                                        </thead>
                                        <tbody id="scraper-products-body">
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">@lang('Scraped products will list here.')</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Selection Summary and Actions -->
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top">
                                    <span class="fw-bold text--dark fs-6" id="scraper-count-text">0 @lang('products selected')</span>
                                    <button type="button" class="btn btn-custom-primary btn-sm px-4 d-flex align-items-center gap-2" id="scraper-import-btn" disabled style="height: 38px;">
                                        <i class="las la-download"></i> @lang('Import Selected')
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Details Modal -->
        <div class="modal fade" id="productDetailsModal" tabindex="-1" aria-labelledby="productDetailsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content" style="border-radius: 16px; overflow: hidden; border: none; box-shadow: 0 15px 50px rgba(0,0,0,0.15);">
                    <div class="modal-header bg--dark-custom text-white">
                        <h5 class="modal-title text-white d-flex align-items-center gap-2" id="productDetailsModalLabel">
                            <i class="las la-info-circle fs-4"></i> @lang('Mohasagor Product Details')
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4" id="product-details-content">
                        <!-- Loaded dynamically via Javascript -->
                    </div>
                    <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #f1f5f9;">
                        <button type="button" class="btn btn-custom-outline" data-bs-dismiss="modal">@lang('Close')</button>
                        <button type="button" class="btn btn-custom-primary px-4" id="modal-import-btn">
                            <i class="las la-download"></i> @lang('Import/Update Product')
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        $(document).ready(function() {
            // Bulk sync variables
            let isSyncing = false;
            let isPaused = false;
            let page = 1;
            let importedCount = 0;
            
            // Stats tracker
            let createdStats = 0;
            let updatedStats = 0;
            let skippedStats = 0;
            
            let targetLimit = 0;
            let selectedCategory = '';
            let localCategoryId = '';
            let priceMarkupType = 'none';
            let priceMarkupValue = 0;
            let regularPriceMarkup = 0;
            let publishStatus = 1;
            let updateExisting = true;
            let outOfStockDefaultQty = 100;

            // Scraper variables
            let scrapedProducts = [];
            let filteredScrapedProducts = [];
            let scrapedProductIds = new Set();
            let isScraperSync = false;

            // Timer
            let timerInterval = null;
            let secondsElapsed = 0;

            // Elements mapping
            const startBtn = $('#start-sync-btn');
            const pauseBtn = $('#pause-sync-btn');
            const stopBtn = $('#stop-sync-btn');
            
            const categorySelect = $('#category');
            const localCategorySelect = $('#local_category_id');
            const markupTypeSelect = $('#price_markup_type');
            const markupValueInput = $('#price_markup_value');
            const limitInput = $('#limit');
            const statusSelect = $('#publish_status');
            const updateCheckbox = $('#update_existing');
            const outOfStockDefaultQtyInput = $('#out_of_stock_default_qty');
            
            const pauseContainer = $('#pause-container');
            const stopContainer = $('#stop-container');
            const progressCard = $('#progress-card');
            const infoCard = $('#info-card');
            const progressBar = $('.progress-bar');
            const progressPercentage = $('#sync-percentage');
            const statusText = $('#sync-status-text');
            const logConsole = $('#log-console');
            const liveProductsBody = $('#live-products-body');
            const timeElapsedBadge = $('#time-elapsed-badge');

            // Stat elements
            const statTarget = $('#stat-target');
            const statCreated = $('#stat-created');
            const statUpdated = $('#stat-updated');
            const statSkipped = $('#stat-skipped');

            function formatTime(seconds) {
                const mins = Math.floor(seconds / 60);
                const secs = seconds % 60;
                return (mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs;
            }

            function startTimer() {
                secondsElapsed = 0;
                timeElapsedBadge.text('00:00');
                clearInterval(timerInterval);
                timerInterval = setInterval(function() {
                    if (!isPaused && isSyncing) {
                        secondsElapsed++;
                        timeElapsedBadge.text(formatTime(secondsElapsed));
                    }
                }, 1000);
            }

            function stopTimer() {
                clearInterval(timerInterval);
            }

            function appendLog(message, type = 'info') {
                const now = new Date();
                const timeStr = now.toTimeString().split(' ')[0];
                let colorClass = 'text-light';
                if (type === 'success') colorClass = 'text-success';
                if (type === 'error') colorClass = 'text-danger';
                if (type === 'warning') colorClass = 'text-warning';

                logConsole.append(`<div class="${colorClass}">[${timeStr}] ${message}</div>`);
                logConsole.scrollTop(logConsole[0].scrollHeight);
            }

            function addLiveProductRow(product) {
                if (liveProductsBody.find('tr').first().find('td').length === 1) {
                    liveProductsBody.empty();
                }
                
                let statusBadge = '';
                if (product.status === 'Created') {
                    statusBadge = '<span class="badge bg-success badge-custom">Created</span>';
                } else if (product.status === 'Updated') {
                    statusBadge = '<span class="badge bg-info badge-custom">Updated</span>';
                } else {
                    statusBadge = '<span class="badge bg-warning badge-custom">Skipped</span>';
                }

                let imgHtml = product.image ? `<img src="${product.image}" class="product-thumb" style="width: 30px; height: 30px;">` : `<i class="las la-image text-muted fs-5"></i>`;

                let rowHtml = `
                    <tr style="border-bottom: 1px solid #334155;">
                        <td style="border: none;">${imgHtml}</td>
                        <td class="text-truncate" style="max-width: 180px; border: none;" title="${product.name}">${product.name}</td>
                        <td style="border: none;"><code>${product.sku}</code></td>
                        <td style="border: none;">${product.price} BDT</td>
                        <td style="border: none;">${statusBadge}</td>
                    </tr>
                `;
                liveProductsBody.prepend(rowHtml);
            }

            // Start Sync Button Trigger
            startBtn.on('click', function(e) {
                e.preventDefault();
                if (isSyncing) return;

                selectedCategory = categorySelect.val();
                targetLimit = parseInt(limitInput.val());
                updateExisting = updateCheckbox.is(':checked');
                localCategoryId = localCategorySelect.val();
                priceMarkupType = markupTypeSelect.val();
                priceMarkupValue = parseFloat(markupValueInput.val()) || 0;
                regularPriceMarkup = parseFloat($('#regular_price_markup').val()) || 0;
                publishStatus = parseInt(statusSelect.val());
                outOfStockDefaultQty = parseInt(outOfStockDefaultQtyInput.val());

                if (!selectedCategory) {
                    notify('error', 'Please select an API category first.');
                    return;
                }

                if (isNaN(targetLimit) || targetLimit <= 0) {
                    notify('error', 'Please enter a valid limit greater than 0.');
                    return;
                }

                if (isNaN(outOfStockDefaultQty) || outOfStockDefaultQty < 0) {
                    notify('error', 'Please enter a valid default stock quantity.');
                    return;
                }

                // Reset states
                isSyncing = true;
                isPaused = false;
                isSelectionSync = false;
                page = 1;
                importedCount = 0;
                createdStats = 0;
                updatedStats = 0;
                skippedStats = 0;

                // Disable inputs
                categorySelect.prop('disabled', true);
                localCategorySelect.prop('disabled', true);
                markupTypeSelect.prop('disabled', true);
                markupValueInput.prop('disabled', true);
                limitInput.prop('disabled', true);
                statusSelect.prop('disabled', true);
                updateCheckbox.prop('disabled', true);
                outOfStockDefaultQtyInput.prop('disabled', true);

                // Update Control Buttons
                startBtn.prop('disabled', true).addClass('d-none');
                pauseContainer.removeClass('d-none');
                stopContainer.removeClass('d-none');
                pauseBtn.html('<i class="las la-pause"></i> Pause').removeClass('btn--success').addClass('btn--warning');

                infoCard.addClass('d-none');
                $('#selector-card').addClass('d-none');
                progressCard.removeClass('d-none');
                logConsole.empty();
                liveProductsBody.html('<tr><td colspan="5" class="text-center text-muted border-0 py-4">Waiting for products...</td></tr>');
                
                // Set stats
                statTarget.text(targetLimit);
                statCreated.text(0);
                statUpdated.text(0);
                statSkipped.text(0);
                
                progressBar.css('width', '0%').attr('aria-valuenow', 0).addClass('progress-bar-animated');
                progressPercentage.text('0%');
                statusText.text('Connecting to API...');

                appendLog(`Starting synchronization process...`, 'info');
                appendLog(`Selected Category: ${selectedCategory}`, 'info');
                appendLog(`Default Stock for Out-of-Stock Products: ${outOfStockDefaultQty}`, 'info');
                if (localCategoryId) {
                    appendLog(`Forced Mapping Local Category ID: ${localCategoryId}`, 'info');
                } else {
                    appendLog(`Auto-creating / matching categories by name`, 'info');
                }
                if (priceMarkupType !== 'none') {
                    appendLog(`Applying price markup: ${priceMarkupValue} (${priceMarkupType})`, 'warning');
                }

                startTimer();
                performSync();
            });

            // Pause / Resume Trigger
            pauseBtn.on('click', function() {
                if (!isSyncing) return;
                if (!isPaused) {
                    isPaused = true;
                    pauseBtn.html('<i class="las la-play"></i> Resume').removeClass('btn--warning').addClass('btn--success');
                    statusText.text('Paused');
                    appendLog('Synchronization paused by administrator.', 'warning');
                } else {
                    isPaused = false;
                    pauseBtn.html('<i class="las la-pause"></i> Pause').removeClass('btn--success').addClass('btn--warning');
                    statusText.text('Resuming...');
                    appendLog('Synchronization resumed.', 'info');
                    if (isSelectionSync) {
                        performSelectedImport();
                    } else {
                        performSync();
                    }
                }
            });

            // Stop Trigger
            stopBtn.on('click', function() {
                if (!isSyncing) return;
                isSyncing = false;
                appendLog('Synchronization stopped by administrator.', 'error');
                finishSync(false, 'Sync stopped');
            });

            function performSync() {
                if (!isSyncing || isPaused) return;

                statusText.text(`Processing page ${page}...`);
                appendLog(`Scanning API Page ${page}...`, 'info');

                $.ajax({
                    url: "{{ route('admin.products.mohasagor.sync.import_chunk') }}",
                    method: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}",
                        category: selectedCategory,
                        limit: targetLimit,
                        update_existing: updateExisting ? 1 : 0,
                        page: page,
                        imported_count: importedCount,
                        local_category_id: localCategoryId,
                        price_markup_type: priceMarkupType,
                        price_markup_value: priceMarkupValue,
                        regular_price_markup: regularPriceMarkup,
                        publish_status: publishStatus,
                        out_of_stock_default_qty: outOfStockDefaultQty
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (!isSyncing) return; // In case it was stopped during the request

                        if (!response.success) {
                            appendLog(response.message || 'An error occurred during sync.', 'error');
                            finishSync(false, response.message || 'Sync failed');
                            return;
                        }

                        // Update counts
                        importedCount = response.imported_count;
                        
                        // Parse detailed logs and live products
                        if (response.imported_products && response.imported_products.length > 0) {
                            response.imported_products.forEach(function(product) {
                                addLiveProductRow(product);
                                if (product.status === 'Created') {
                                    createdStats++;
                                    statCreated.text(createdStats);
                                } else if (product.status === 'Updated') {
                                    updatedStats++;
                                    statUpdated.text(updatedStats);
                                }
                            });
                        }

                        if (response.logs && response.logs.length > 0) {
                            response.logs.forEach(function(log) {
                                let logType = 'success';
                                if (log.indexOf('Skipped') !== -1) {
                                    logType = 'warning';
                                    skippedStats++;
                                    statSkipped.text(skippedStats);
                                }
                                appendLog(log, logType);
                            });
                        }

                        // Calculate progress percent
                        let limitPercent = Math.round((importedCount / targetLimit) * 100);
                        let pagePercent = Math.round((response.current_page / response.last_page) * 100);
                        let calculatedPercent = Math.max(limitPercent, pagePercent);
                        if (calculatedPercent > 100) calculatedPercent = 100;

                        progressBar.css('width', calculatedPercent + '%').attr('aria-valuenow', calculatedPercent);
                        progressPercentage.text(calculatedPercent + '%');

                        if (response.finished) {
                            appendLog(`Sync completed successfully. Total processed: ${importedCount}`, 'success');
                            finishSync(true);
                        } else {
                            page = response.current_page + 1;
                            // Recurse to next chunk
                            setTimeout(performSync, 400); 
                        }
                    },
                    error: function(xhr, status, error) {
                        appendLog(`HTTP Error: ${error || 'Connection failed'}`, 'error');
                        finishSync(false, 'Connection error');
                    }
                });
            }

            function finishSync(success, msg = '') {
                isSyncing = false;
                isPaused = false;
                stopTimer();

                // Re-enable form elements
                categorySelect.prop('disabled', false);
                localCategorySelect.prop('disabled', false);
                markupTypeSelect.prop('disabled', false);
                markupValueInput.prop('disabled', false);
                limitInput.prop('disabled', false);
                statusSelect.prop('disabled', false);
                updateCheckbox.prop('disabled', false);
                outOfStockDefaultQtyInput.prop('disabled', false);

                // Re-enable scraper form elements
                $('#scrape_url').prop('disabled', false);
                $('#scraper_local_category_id').prop('disabled', false);
                $('#scraper_price_markup_type').prop('disabled', false);
                $('#scraper_price_markup_value').prop('disabled', false);
                $('#scraper_publish_status').prop('disabled', false);
                $('#scraper_out_of_stock_default_qty').prop('disabled', false);
                $('#start-scan-btn').prop('disabled', false);

                // Controls visibility reset
                startBtn.removeClass('d-none').prop('disabled', false);
                pauseContainer.addClass('d-none');
                stopContainer.addClass('d-none');
                progressBar.removeClass('progress-bar-animated');

                if (success) {
                    statusText.text('Sync Finished!');
                    progressBar.css('width', '100%').attr('aria-valuenow', 100);
                    progressPercentage.text('100%');
                    notify('success', `Sync completed! Total products processed: ${importedCount}`);
                } else {
                    statusText.text(msg || 'Sync Interrupted');
                    notify('warning', msg || 'Sync stopped.');
                }

                // If selection sync, switch back to selector card after a delay
                if (isSelectionSync) {
                    setTimeout(function() {
                        progressCard.addClass('d-none');
                        if (isScraperSync) {
                            $('#scraper-results-card').removeClass('d-none');
                            applyScraperFiltersAndSorting();
                        } else {
                            $('#selector-card').removeClass('d-none');
                            applyFiltersAndSorting();
                        }
                    }, 2000);
                }
            }

            // Toggle secret key visibility
            $('#toggle-secret-btn').on('click', function() {
                const input = $('#secret_key');
                const icon = $(this).find('i');
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('la-eye').addClass('la-eye-slash');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('la-eye-slash').addClass('la-eye');
                }
            });

            function checkApiConnection() {
                const badge = $('#api-status-badge');
                badge.removeClass('badge--success badge--danger badge--warning badge--dark')
                     .addClass('badge--warning')
                     .text('Checking...');

                $.ajax({
                    url: "{{ route('admin.products.mohasagor.sync.test_connection') }}",
                    method: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        badge.removeClass('badge--warning');
                        if (response.success) {
                            badge.addClass('badge--success').text('Active & Connected');
                        } else {
                            badge.addClass('badge--danger').text('Disconnected / Invalid Keys');
                        }
                    },
                    error: function() {
                        badge.removeClass('badge--warning').addClass('badge--danger').text('Connection Error');
                    }
                });
            }

            // Check connection on page load
            checkApiConnection();

            // Save Credentials Trigger
            $('#credentialsForm').on('submit', function(e) {
                e.preventDefault();
                const btn = $('#save-credentials-btn');
                btn.prop('disabled', true).html('<i class="las la-spinner la-spin"></i> Saving...');
                
                $.ajax({
                    url: "{{ route('admin.products.mohasagor.sync.save_credentials') }}",
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        btn.prop('disabled', false).html('<i class="las la-save"></i> Save Credentials');
                        if (response.success) {
                            notify('success', response.message);
                            checkApiConnection();
                        } else {
                            notify('error', response.message || 'Failed to save credentials.');
                        }
                    },
                    error: function(xhr, status, error) {
                        btn.prop('disabled', false).html('<i class="las la-save"></i> Save Credentials');
                        notify('error', 'An error occurred while saving credentials.');
                    }
                });
            });


            // ==========================================
            // Category-Based Selection & Preview Sync
            // ==========================================
            let categoryProducts = [];
            let filteredProducts = [];
            let selectedProductIds = new Set();
            let isSelectionSync = false;
            let currentSelectedImportIndex = 0;

            // Handle Category change to show preview
            categorySelect.on('change', function() {
                const categoryName = $(this).val();
                if (!categoryName) return;

                // Disable select and show loader
                categorySelect.prop('disabled', true);
                
                $('#info-card').addClass('d-none');
                progressCard.addClass('d-none');
                $('#selector-card').removeClass('d-none');
                $('#selector-category-name').text(categoryName);
                
                // Clear inputs
                $('#selector-search-input').val('');
                $('#selector-sort-select').val('default');
                $('#selector-status-select').val('all');
                $('#selector-select-all').prop('checked', false);
                selectedProductIds.clear();
                updateSelectorUI();

                // Show spinner
                $('#selector-products-body').html(`
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="las la-spinner la-spin fs-2 d-block mb-2 text-primary"></i>
                            @lang('Loading products from API cache...')
                        </td>
                    </tr>
                `);

                // Fetch category products from server
                $.ajax({
                    url: "{{ route('admin.products.mohasagor.sync.search') }}",
                    method: 'GET',
                    data: {
                        category: categoryName
                    },
                    dataType: 'json',
                    success: function(response) {
                        categorySelect.prop('disabled', false);

                        if (!response.success) {
                            notify('error', response.message);
                            $('#selector-products-body').html(`<tr><td colspan="6" class="text-center text-danger py-4">${response.message}</td></tr>`);
                            return;
                        }

                        categoryProducts = response.results;
                        applyFiltersAndSorting();
                    },
                    error: function() {
                        categorySelect.prop('disabled', false);
                        notify('error', 'Failed to load category products.');
                        $('#selector-products-body').html('<tr><td colspan="6" class="text-center text-danger py-4">Failed to load products.</td></tr>');
                    }
                });
            });

            function applyFiltersAndSorting() {
                const keyword = $('#selector-search-input').val().toLowerCase().trim();
                const sortBy = $('#selector-sort-select').val();
                const statusFilter = $('#selector-status-select').val();

                // 1. Filter
                filteredProducts = categoryProducts.filter(p => {
                    const nameMatch = !keyword || (p.name && String(p.name).toLowerCase().includes(keyword));
                    const skuMatch = !keyword || (p.product_code && String(p.product_code).toLowerCase().includes(keyword)) || ('mhs-' + p.id).includes(keyword);
                    
                    let statusMatch = true;
                    if (statusFilter === 'imported') {
                        statusMatch = p.is_imported === true;
                    } else if (statusFilter === 'not_imported') {
                        statusMatch = p.is_imported === false;
                    }

                    return (nameMatch || skuMatch) && statusMatch;
                });

                // 2. Sort
                if (sortBy === 'price_low') {
                    filteredProducts.sort((a, b) => parseFloat(a.price || 0) - parseFloat(b.price || 0));
                } else if (sortBy === 'price_high') {
                    filteredProducts.sort((a, b) => parseFloat(b.price || 0) - parseFloat(a.price || 0));
                }

                renderSelectorTable();
            }

            function renderSelectorTable() {
                const tbody = $('#selector-products-body');
                tbody.empty();

                if (filteredProducts.length === 0) {
                    tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">No products match your filters.</td></tr>');
                    return;
                }

                filteredProducts.forEach(p => {
                    const isChecked = selectedProductIds.has(p.id) ? 'checked' : '';
                    const imgHtml = p.thumbnail_img 
                        ? `<img src="${p.thumbnail_img}" class="product-thumb">` 
                        : `<i class="las la-image text-muted fs-5"></i>`;
                    
                    const statusBadge = p.is_imported 
                        ? `<span class="badge badge-custom imported">Imported</span>` 
                        : `<span class="badge badge-custom not-imported">Not Imported</span>`;

                    tbody.append(`
                        <tr>
                            <td class="text-center" style="vertical-align: middle;">
                                <input class="form-check-input selector-check" type="checkbox" data-id="${p.id}" ${isChecked} style="cursor: pointer;">
                            </td>
                            <td>${imgHtml}</td>
                            <td class="text-truncate fw-bold" style="max-width: 280px;" title="${p.name}">${p.name}</td>
                            <td><code>MHS-${p.id}</code></td>
                            <td>${p.price} BDT</td>
                            <td id="selector-badge-${p.id}">${statusBadge}</td>
                        </tr>
                    `);
                });

                updateSelectAllCheckboxState();
            }

            function updateSelectAllCheckboxState() {
                const renderedChecks = $('.selector-check');
                if (renderedChecks.length === 0) {
                    $('#selector-select-all').prop('checked', false);
                    return;
                }
                let allChecked = true;
                renderedChecks.each(function() {
                    if (!$(this).is(':checked')) {
                        allChecked = false;
                        return false;
                    }
                });
                $('#selector-select-all').prop('checked', allChecked);
            }

            function updateSelectorUI() {
                const count = selectedProductIds.size;
                $('#selector-count-text').text(`${count} products selected`);
                $('#selector-import-btn').prop('disabled', count === 0);
            }

            // Single Checkbox toggle
            $(document).on('change', '.selector-check', function() {
                const id = $(this).data('id');
                if ($(this).is(':checked')) {
                    selectedProductIds.add(id);
                } else {
                    selectedProductIds.delete(id);
                }
                updateSelectorUI();
                updateSelectAllCheckboxState();
            });

            // Select All Toggle
            $('#selector-select-all').on('change', function() {
                const isChecked = $(this).is(':checked');
                $('.selector-check').each(function() {
                    const id = $(this).data('id');
                    $(this).prop('checked', isChecked);
                    if (isChecked) {
                        selectedProductIds.add(id);
                    } else {
                        selectedProductIds.delete(id);
                    }
                });
                updateSelectorUI();
            });

            // Filter event triggers
            $('#selector-search-input').on('input', function() {
                applyFiltersAndSorting();
            });
            $('#selector-sort-select').on('change', function() {
                applyFiltersAndSorting();
            });
            $('#selector-status-select').on('change', function() {
                applyFiltersAndSorting();
            });

            // ==========================================
            // Link Scraper & Selection Sync Logic
            // ==========================================

            // Handle Scan URL button click
            $('#start-scan-btn').on('click', function(e) {
                e.preventDefault();
                const url = $('#scrape_url').val().trim();
                if (!url) {
                    notify('error', 'Please enter a valid Mohasagor URL.');
                    return;
                }

                const scanBtn = $(this);
                const originalHtml = scanBtn.html();
                scanBtn.prop('disabled', true).html('<i class="las la-spinner la-spin"></i> Scanning page...');

                $('#scraper-guide-card').addClass('d-none');
                $('#scraper-results-card').removeClass('d-none');
                scrapedProductIds.clear();
                scrapedProducts = [];
                filteredScrapedProducts = [];
                updateScraperUI();

                $('#scraper-products-body').html(`
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="las la-spinner la-spin fs-2 d-block mb-2 text-primary"></i>
                            Scraping products from the pasted URL. Please wait...
                        </td>
                    </tr>
                `);

                $.ajax({
                    url: "{{ route('admin.products.mohasagor.sync.scrape_link') }}",
                    method: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}",
                        url: url
                    },
                    dataType: 'json',
                    success: function(response) {
                        scanBtn.prop('disabled', false).html(originalHtml);
                        if (!response.success) {
                            notify('error', response.message);
                            $('#scraper-products-body').html(`<tr><td colspan="6" class="text-center text-danger py-4">${response.message}</td></tr>`);
                            return;
                        }

                        scrapedProducts = response.results;
                        $('#scanned-count-badge').text(`${scrapedProducts.length} found`);
                        applyScraperFiltersAndSorting();
                    },
                    error: function(xhr) {
                        scanBtn.prop('disabled', false).html(originalHtml);
                        let errMsg = 'An error occurred while scanning the URL.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errMsg = xhr.responseJSON.message;
                        }
                        notify('error', errMsg);
                        $('#scraper-products-body').html(`<tr><td colspan="6" class="text-center text-danger py-4">${errMsg}</td></tr>`);
                    }
                });
            });

            function applyScraperFiltersAndSorting() {
                const keyword = $('#scraper-search-input').val().toLowerCase().trim();
                const sortBy = $('#scraper-sort-select').val();
                const statusFilter = $('#scraper-status-select').val();

                // 1. Filter
                filteredScrapedProducts = scrapedProducts.filter(p => {
                    const nameMatch = !keyword || (p.name && String(p.name).toLowerCase().includes(keyword));
                    const skuMatch = !keyword || (p.product_code && String(p.product_code).toLowerCase().includes(keyword)) || ('mhs-' + p.id).includes(keyword);
                    
                    let statusMatch = true;
                    if (statusFilter === 'imported') {
                        statusMatch = p.is_imported === true;
                    } else if (statusFilter === 'not_imported') {
                        statusMatch = p.is_imported === false;
                    }

                    return (nameMatch || skuMatch) && statusMatch;
                });

                // 2. Sort
                if (sortBy === 'price_low') {
                    filteredScrapedProducts.sort((a, b) => parseFloat(a.price || 0) - parseFloat(b.price || 0));
                } else if (sortBy === 'price_high') {
                    filteredScrapedProducts.sort((a, b) => parseFloat(b.price || 0) - parseFloat(a.price || 0));
                }

                renderScraperTable();
            }

            function renderScraperTable() {
                const tbody = $('#scraper-products-body');
                tbody.empty();

                if (filteredScrapedProducts.length === 0) {
                    tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">No scraped products match your filters.</td></tr>');
                    return;
                }

                filteredScrapedProducts.forEach(p => {
                    const isChecked = scrapedProductIds.has(p.id) ? 'checked' : '';
                    const imgHtml = p.thumbnail_img 
                        ? `<img src="${p.thumbnail_img}" class="product-thumb">` 
                        : `<i class="las la-image text-muted fs-5"></i>`;
                    
                    const statusBadge = p.is_imported 
                        ? `<span class="badge badge-custom imported">Imported</span>` 
                        : `<span class="badge badge-custom not-imported">Not Imported</span>`;

                    tbody.append(`
                        <tr>
                            <td class="text-center" style="vertical-align: middle;">
                                <input class="form-check-input scraper-check" type="checkbox" data-id="${p.id}" ${isChecked} style="cursor: pointer;">
                            </td>
                            <td>${imgHtml}</td>
                            <td class="text-truncate fw-bold" style="max-width: 280px;" title="${p.name}">${p.name}</td>
                            <td><code>${p.product_code || 'MHS-' + p.id}</code></td>
                            <td>${p.price} BDT</td>
                            <td id="scraper-badge-${p.id}">${statusBadge}</td>
                        </tr>
                    `);
                });

                updateScraperSelectAllCheckboxState();
            }

            function updateScraperSelectAllCheckboxState() {
                const renderedChecks = $('.scraper-check');
                if (renderedChecks.length === 0) {
                    $('#scraper-select-all').prop('checked', false);
                    return;
                }
                let allChecked = true;
                renderedChecks.each(function() {
                    if (!$(this).is(':checked')) {
                        allChecked = false;
                        return false;
                    }
                });
                $('#scraper-select-all').prop('checked', allChecked);
            }

            function updateScraperUI() {
                const count = scrapedProductIds.size;
                $('#scraper-count-text').text(`${count} products selected`);
                $('#scraper-import-btn').prop('disabled', count === 0);
            }

            // Single Scraper Checkbox toggle
            $(document).on('change', '.scraper-check', function() {
                const id = $(this).data('id');
                if ($(this).is(':checked')) {
                    scrapedProductIds.add(id);
                } else {
                    scrapedProductIds.delete(id);
                }
                updateScraperUI();
                updateScraperSelectAllCheckboxState();
            });

            // Select All Toggle for Scraper
            $('#scraper-select-all').on('change', function() {
                const isChecked = $(this).is(':checked');
                $('.scraper-check').each(function() {
                    const id = $(this).data('id');
                    $(this).prop('checked', isChecked);
                    if (isChecked) {
                        scrapedProductIds.add(id);
                    } else {
                        scrapedProductIds.delete(id);
                    }
                });
                updateScraperUI();
            });

            // Scraper Filters change triggers
            $('#scraper-search-input').on('input', function() {
                applyScraperFiltersAndSorting();
            });
            $('#scraper-sort-select').on('change', function() {
                applyScraperFiltersAndSorting();
            });
            $('#scraper-status-select').on('change', function() {
                applyScraperFiltersAndSorting();
            });

            // Scraper Import Selected trigger
            $('#scraper-import-btn').on('click', function(e) {
                e.preventDefault();
                if (isSyncing || scrapedProductIds.size === 0) return;

                // Set config from Scraper Form
                localCategoryId = $('#scraper_local_category_id').val();
                priceMarkupType = $('#scraper_price_markup_type').val();
                priceMarkupValue = parseFloat($('#scraper_price_markup_value').val()) || 0;
                regularPriceMarkup = parseFloat($('#scraper_regular_price_markup').val()) || 0;
                publishStatus = parseInt($('#scraper_publish_status').val());
                outOfStockDefaultQty = parseInt($('#scraper_out_of_stock_default_qty').val());

                if (isNaN(outOfStockDefaultQty) || outOfStockDefaultQty < 0) {
                    notify('error', 'Please enter a valid default stock quantity.');
                    return;
                }

                // Reset and setup states
                isSyncing = true;
                isPaused = false;
                isSelectionSync = true;
                isScraperSync = true;
                currentSelectedImportIndex = 0;
                importedCount = 0;
                createdStats = 0;
                updatedStats = 0;
                skippedStats = 0;
                targetLimit = scrapedProductIds.size;

                // Disable Scraper Form elements
                $('#scrape_url').prop('disabled', true);
                $('#scraper_local_category_id').prop('disabled', true);
                $('#scraper_price_markup_type').prop('disabled', true);
                $('#scraper_price_markup_value').prop('disabled', true);
                $('#scraper_publish_status').prop('disabled', true);
                $('#scraper_out_of_stock_default_qty').prop('disabled', true);
                $('#start-scan-btn').prop('disabled', true);

                // Controls visibility
                startBtn.prop('disabled', true).addClass('d-none');
                pauseContainer.removeClass('d-none');
                stopContainer.removeClass('d-none');
                pauseBtn.html('<i class="las la-pause"></i> Pause').removeClass('btn--success').addClass('btn--warning');

                $('#scraper-results-card').addClass('d-none');
                progressCard.removeClass('d-none');
                logConsole.empty();
                liveProductsBody.html('<tr><td colspan="5" class="text-center text-muted border-0 py-4">Waiting for products...</td></tr>');

                statTarget.text(targetLimit);
                statCreated.text(0);
                statUpdated.text(0);
                statSkipped.text(0);

                progressBar.css('width', '0%').attr('aria-valuenow', 0).addClass('progress-bar-animated');
                progressPercentage.text('0%');
                statusText.text('Initializing scraped products import...');

                appendLog(`Starting synchronization of ${targetLimit} scraped products...`, 'info');
                appendLog(`Default Stock: ${outOfStockDefaultQty}`, 'info');
                if (localCategoryId) {
                    appendLog(`Local Category Mapping ID: ${localCategoryId}`, 'info');
                }
                if (priceMarkupType !== 'none') {
                    appendLog(`Applying price markup: ${priceMarkupValue} (${priceMarkupType})`, 'warning');
                }

                startTimer();
                performSelectedImport();
            });

            // Selected Products Import trigger
            $('#selector-import-btn').on('click', function(e) {
                e.preventDefault();
                if (isSyncing || selectedProductIds.size === 0) return;

                // Set configuration from the form
                localCategoryId = localCategorySelect.val();
                priceMarkupType = markupTypeSelect.val();
                priceMarkupValue = parseFloat(markupValueInput.val()) || 0;
                regularPriceMarkup = parseFloat($('#regular_price_markup').val()) || 0;
                publishStatus = parseInt(statusSelect.val());
                outOfStockDefaultQty = parseInt(outOfStockDefaultQtyInput.val());

                if (isNaN(outOfStockDefaultQty) || outOfStockDefaultQty < 0) {
                    notify('error', 'Please enter a valid default stock quantity.');
                    return;
                }

                // Reset and setup states
                isSyncing = true;
                isPaused = false;
                isSelectionSync = true;
                currentSelectedImportIndex = 0;
                importedCount = 0;
                createdStats = 0;
                updatedStats = 0;
                skippedStats = 0;
                targetLimit = selectedProductIds.size;

                // Disable form elements
                categorySelect.prop('disabled', true);
                localCategorySelect.prop('disabled', true);
                markupTypeSelect.prop('disabled', true);
                markupValueInput.prop('disabled', true);
                limitInput.prop('disabled', true);
                statusSelect.prop('disabled', true);
                updateCheckbox.prop('disabled', true);
                outOfStockDefaultQtyInput.prop('disabled', true);

                // Controls visibility
                startBtn.prop('disabled', true).addClass('d-none');
                pauseContainer.removeClass('d-none');
                stopContainer.removeClass('d-none');
                pauseBtn.html('<i class="las la-pause"></i> Pause').removeClass('btn--success').addClass('btn--warning');

                $('#selector-card').addClass('d-none');
                progressCard.removeClass('d-none');
                logConsole.empty();
                liveProductsBody.html('<tr><td colspan="5" class="text-center text-muted border-0 py-4">Waiting for products...</td></tr>');

                statTarget.text(targetLimit);
                statCreated.text(0);
                statUpdated.text(0);
                statSkipped.text(0);

                progressBar.css('width', '0%').attr('aria-valuenow', 0).addClass('progress-bar-animated');
                progressPercentage.text('0%');
                statusText.text('Initializing selected products import...');

                appendLog(`Starting synchronization of ${targetLimit} selected products...`, 'info');
                appendLog(`Default Stock for Out-of-Stock Products: ${outOfStockDefaultQty}`, 'info');
                if (localCategoryId) {
                    appendLog(`Forced Mapping Local Category ID: ${localCategoryId}`, 'info');
                }
                if (priceMarkupType !== 'none') {
                    appendLog(`Applying price markup: ${priceMarkupValue} (${priceMarkupType})`, 'warning');
                }

                startTimer();
                performSelectedImport();
            });

            function performSelectedImport() {
                if (!isSyncing || isPaused) return;

                const idsArray = Array.from(isScraperSync ? scrapedProductIds : selectedProductIds);
                if (currentSelectedImportIndex >= idsArray.length) {
                    appendLog(`Sync completed successfully. Total processed: ${importedCount}`, 'success');
                    finishSync(true);
                    return;
                }

                const productId = idsArray[currentSelectedImportIndex];
                const product = (isScraperSync ? scrapedProducts : categoryProducts).find(p => p.id == productId);
                const productName = product ? product.name : `Product ID ${productId}`;

                statusText.text(`Syncing product ${currentSelectedImportIndex + 1} of ${targetLimit}...`);
                appendLog(`Processing selected: ${productName} (SKU: ${product && product.product_code ? product.product_code : 'MHS-' + productId})`, 'info');

                let postData = {
                    _token: "{{ csrf_token() }}",
                    product_id: productId,
                    local_category_id: localCategoryId,
                    price_markup_type: priceMarkupType,
                    price_markup_value: priceMarkupValue,
                    regular_price_markup: regularPriceMarkup,
                    publish_status: publishStatus,
                    out_of_stock_default_qty: outOfStockDefaultQty
                };

                if (product && product.is_generic) {
                    postData.is_generic = true;
                    postData.name = product.name;
                    postData.price = product.price;
                    postData.sale_price = product.sale_price;
                    postData.image = product.thumbnail_img;
                    postData.details = product.details;
                    postData.sku = product.product_code;
                }

                $.ajax({
                    url: "{{ route('admin.products.mohasagor.sync.import_single') }}",
                    method: 'POST',
                    data: postData,
                    dataType: 'json',
                    success: function(response) {
                        if (!isSyncing) return;

                        if (response.success) {
                            importedCount++;
                            const importProduct = response.product || {};
                            
                            let statusTextVal = 'Created';
                            if (importProduct.status === 'created') {
                                createdStats++;
                                statCreated.text(createdStats);
                            } else {
                                updatedStats++;
                                statUpdated.text(updatedStats);
                                statusTextVal = 'Updated';
                            }

                            // Update live table
                            addLiveProductRow({
                                name: productName,
                                sku: `MHS-${productId}`,
                                price: importProduct.price || (product ? product.price : 0),
                                image: importProduct.image || (product ? product.thumbnail_img : ''),
                                status: statusTextVal
                            });

                            appendLog(`Successfully ${statusTextVal.toLowerCase()}: ${productName}`, 'success');

                            // Mark as imported locally in our cached list
                            if (product) {
                                product.is_imported = true;
                            }
                        } else {
                            appendLog(`Failed: ${productName} - ${response.message || 'Unknown error'}`, 'error');
                        }

                        // Update progress bar
                        let currentProgressPercent = Math.round(((currentSelectedImportIndex + 1) / targetLimit) * 100);
                        if (currentProgressPercent > 100) currentProgressPercent = 100;
                        progressBar.css('width', currentProgressPercent + '%').attr('aria-valuenow', currentProgressPercent);
                        progressPercentage.text(currentProgressPercent + '%');

                        // Move to next product
                        currentSelectedImportIndex++;
                        setTimeout(performSelectedImport, 350);
                    },
                    error: function(xhr, status, error) {
                        appendLog(`HTTP Error on ${productName}: ${error || 'Connection failed'}`, 'error');
                        
                        // Update progress bar
                        let currentProgressPercent = Math.round(((currentSelectedImportIndex + 1) / targetLimit) * 100);
                        progressBar.css('width', currentProgressPercent + '%').attr('aria-valuenow', currentProgressPercent);
                        progressPercentage.text(currentProgressPercent + '%');

                        currentSelectedImportIndex++;
                        setTimeout(performSelectedImport, 350);
                    }
                });
            }


            // ==========================================
            // Catalog Search & Single Import Logic
            // ==========================================
            let activeCatalog = [];
            let currentDetailProduct = null;

            function loadSearch(query = '', refresh = false) {
                const searchResultsBody = $('#search-results-body');
                const searchBtn = $('#search-btn');
                const refreshBtn = $('#refresh-catalog-btn');
                
                searchBtn.prop('disabled', true).html('<i class="las la-search"></i> Searching...');
                if (refresh) {
                    refreshBtn.prop('disabled', true).html('<i class="las la-sync la-spin"></i> Syncing...');
                    searchResultsBody.html(`
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted border-0">
                                <i class="las la-spinner la-spin fs-2 d-block mb-2 text-primary"></i>
                                Downloading entire Mohasagor catalog API pages. This might take 5-8 seconds. Please wait...
                            </td>
                        </tr>
                    `);
                }

                $.ajax({
                    url: "{{ route('admin.products.mohasagor.sync.search') }}",
                    method: 'GET',
                    data: {
                        q: query,
                        refresh: refresh ? 1 : 0
                    },
                    dataType: 'json',
                    success: function(response) {
                        searchBtn.prop('disabled', false).html('<i class="las la-search"></i> Search');
                        refreshBtn.prop('disabled', false).html('<i class="las la-sync"></i> Refresh Catalog');

                        if (!response.success) {
                            notify('error', response.message);
                            searchResultsBody.html(`<tr><td colspan="8" class="text-center text-danger py-4 border-0">${response.message}</td></tr>`);
                            return;
                        }

                        $('#catalog-cache-time').text(response.last_updated);
                        searchResultsBody.empty();
                        activeCatalog = response.results;

                        if (activeCatalog.length === 0) {
                            searchResultsBody.html('<tr><td colspan="8" class="text-center text-muted py-4 border-0">No matching products found. Try refreshing the catalog cache.</td></tr>');
                            return;
                        }

                        activeCatalog.forEach(function(product, index) {
                            let stockBadge = product.stock_status === 'available' 
                                ? '<span class="badge badge-custom in-stock">In Stock</span>' 
                                : '<span class="badge badge-custom out-of-stock">Out of Stock</span>';
                            
                            let importBadge = product.is_imported 
                                ? `<span class="badge badge-custom imported">Imported</span>` 
                                : '<span class="badge badge-custom not-imported">Not Imported</span>';

                            let imgHtml = product.thumbnail_img 
                                ? `<img src="${product.thumbnail_img}" class="product-thumb">` 
                                : `<i class="las la-image text-muted fs-4"></i>`;

                            let actionBtn = `
                                <div class="btn-group gap-1">
                                    <button type="button" class="btn btn-sm btn-custom-outline view-details-btn" data-index="${index}" style="padding: 4px 10px; font-size: 11px;">
                                        <i class="las la-eye"></i> Details
                                    </button>
                                    <button type="button" class="btn btn-sm btn-custom-primary import-single-btn" data-id="${product.id}" style="padding: 4px 10px; font-size: 11px;">
                                        <i class="las la-download"></i> ${product.is_imported ? 'Update' : 'Import'}
                                    </button>
                                </div>
                            `;

                            searchResultsBody.append(`
                                <tr>
                                    <td>${imgHtml}</td>
                                    <td class="text-truncate fw-bold" style="max-width: 220px;" title="${product.name}">${product.name}</td>
                                    <td><code>MHS-${product.id}</code></td>
                                    <td><span class="badge badge--dark">${product.category}</span></td>
                                    <td>${product.price} BDT</td>
                                    <td>${stockBadge}</td>
                                    <td id="import-badge-${product.id}">${importBadge}</td>
                                    <td>${actionBtn}</td>
                                </tr>
                            `);
                        });
                    },
                    error: function() {
                        searchBtn.prop('disabled', false).html('<i class="las la-search"></i> Search');
                        refreshBtn.prop('disabled', false).html('<i class="las la-sync"></i> Refresh Catalog');
                        notify('error', 'An error occurred while loading the product catalog.');
                        searchResultsBody.html('<tr><td colspan="8" class="text-center text-danger py-4 border-0">Failed to load catalog. Ensure API keys are active.</td></tr>');
                    }
                });
            }

            function importSingleProduct(productId, btnElement) {
                const originalBtnHtml = btnElement.html();
                btnElement.prop('disabled', true).html('<i class="las la-spinner la-spin"></i> Synced...');

                const localCategoryId = $('#single_local_category_id').val();
                const priceMarkupType = $('#single_price_markup_type').val();
                const priceMarkupValue = parseFloat($('#single_price_markup_value').val()) || 0;
                const regularPriceMarkup = parseFloat($('#single_regular_price_markup').val()) || 0;
                const publishStatus = parseInt($('#single_publish_status').val());
                const outOfStockDefaultQty = parseInt($('#single_out_of_stock_default_qty').val()) || 100;

                $.ajax({
                    url: "{{ route('admin.products.mohasagor.sync.import_single') }}",
                    method: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}",
                        product_id: productId,
                        local_category_id: localCategoryId,
                        price_markup_type: priceMarkupType,
                        price_markup_value: priceMarkupValue,
                        regular_price_markup: regularPriceMarkup,
                        publish_status: publishStatus,
                        out_of_stock_default_qty: outOfStockDefaultQty
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            notify('success', response.message);
                            btnElement.prop('disabled', false).html('<i class="las la-check"></i> Updated').removeClass('btn-custom-primary').addClass('btn-success');
                            
                            $(`#import-badge-${productId}`).html('<span class="badge badge-custom imported">Imported</span>');
                            $('#modal-import-status-badge').html('<span class="badge badge-custom imported">Imported in Shop</span>');
                            
                            if (activeCatalog) {
                                const prod = activeCatalog.find(p => p.id == productId);
                                if (prod) {
                                    prod.is_imported = true;
                                }
                            }
                        } else {
                            notify('error', response.message || 'Import failed.');
                            btnElement.prop('disabled', false).html(originalBtnHtml);
                        }
                    },
                    error: function(xhr) {
                        notify('error', 'Server error or timeout during product import.');
                        btnElement.prop('disabled', false).html(originalBtnHtml);
                    }
                });
            }

            // Tab active retention
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                localStorage.setItem('activeMohasagorSyncTab', $(e.target).attr('id'));
            });
            const activeTab = localStorage.getItem('activeMohasagorSyncTab');
            if (activeTab) {
                const tabEl = document.getElementById(activeTab);
                if (tabEl) {
                    const tabInstance = bootstrap.Tab.getOrCreateInstance(tabEl);
                    tabInstance.show();
                }
            }

            // Auto-load search tab catalog on first view
            let catalogLoaded = false;
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                if (e.target.id === 'single-search-tab' && !catalogLoaded) {
                    loadSearch('');
                    catalogLoaded = true;
                }
            });

            // Single search triggers
            $('#search-btn').on('click', function() {
                const val = $('#search-input').val();
                loadSearch(val);
            });

            $('#search-input').on('keypress', function(e) {
                if (e.which === 13) {
                    const val = $(this).val();
                    loadSearch(val);
                }
            });

            // Refresh catalog trigger
            $('#refresh-catalog-btn').on('click', function() {
                loadSearch('', true);
            });

            // Row click trigger
            $(document).on('click', '.import-single-btn', function() {
                const productId = $(this).data('id');
                importSingleProduct(productId, $(this));
            });

            // View details modal trigger
            $(document).on('click', '.view-details-btn', function() {
                const index = $(this).data('index');
                const product = activeCatalog[index];
                currentDetailProduct = product;

                let modalContent = $('#product-details-content');
                modalContent.empty();

                // Prepare images carousel
                let images = [];
                if (product.thumbnail_img) {
                    images.push(product.thumbnail_img);
                }
                if (product.product_images && Array.isArray(product.product_images)) {
                    product.product_images.forEach(imgObj => {
                        if (imgObj.product_image) {
                            images.push(imgObj.product_image);
                        }
                    });
                }

                let carouselHtml = '';
                if (images.length > 0) {
                    carouselHtml = `
                        <div id="productCarousel" class="carousel slide border rounded mb-3 bg-light" data-bs-ride="carousel" style="max-height: 280px; overflow: hidden;">
                            <div class="carousel-inner text-center">
                                ${images.map((img, idx) => `
                                    <div class="carousel-item ${idx === 0 ? 'active' : ''}">
                                        <img src="${img}" class="d-inline-block img-fluid" style="height: 280px; object-fit: contain;">
                                    </div>
                                `).join('')}
                            </div>
                            ${images.length > 1 ? `
                                <button class="carousel-control-prev" type="button" data-bs-target="#productCarousel" data-bs-slide="prev" style="background: rgba(0,0,0,0.15); width: 10%;">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Previous</span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#productCarousel" data-bs-slide="next" style="background: rgba(0,0,0,0.15); width: 10%;">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Next</span>
                                </button>
                            ` : ''}
                        </div>
                    `;
                } else {
                    carouselHtml = `<div class="border rounded mb-3 text-center p-5 bg-light"><i class="las la-image text-muted fs-1"></i><p>No Image Available</p></div>`;
                }

                let stockBadge = product.stock_status === 'available' 
                    ? '<span class="badge badge-custom in-stock">In Stock</span>' 
                    : '<span class="badge badge-custom out-of-stock">Out of Stock</span>';

                let importBadge = product.is_imported 
                    ? '<span class="badge badge-custom imported">Imported in Shop</span>' 
                    : '<span class="badge badge-custom not-imported">Not Imported</span>';

                let html = `
                    <div class="row">
                        <div class="col-md-5">
                            ${carouselHtml}
                        </div>
                        <div class="col-md-7">
                            <h4 class="mb-2 text-dark font-weight-bold" style="font-family: 'Outfit', sans-serif;">${product.name}</h4>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge bg-dark">Category: ${product.category}</span>
                                <span class="badge bg-secondary text-white">SKU: MHS-${product.id}</span>
                                <span id="modal-import-status-badge">${importBadge}</span>
                            </div>
                            <div class="price-summary-box mb-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 10px;">Retail/Selling Price</small>
                                    <strong class="fs-4 text-primary" style="font-family: 'Outfit', sans-serif;">${product.price} BDT</strong>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 10px;">Dropship/Wholesale Cost</small>
                                    <strong class="fs-4 text-success" style="font-family: 'Outfit', sans-serif;">${product.sale_price} BDT</strong>
                                </div>
                            </div>
                            <div class="mb-3">
                                <strong>API Stock Status:</strong> ${stockBadge}
                            </div>
                            <div class="mb-3">
                                <strong>Description Summary:</strong>
                                <div class="border rounded p-3 bg-light mt-1" style="max-height: 150px; overflow-y: auto; font-size: 12px; line-height: 1.5; color: #475569;">
                                    ${product.details || 'No description summary available.'}
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                modalContent.html(html);
                const modal = new bootstrap.Modal(document.getElementById('productDetailsModal'));
                modal.show();
            });

            // Modal import button trigger
            $('#modal-import-btn').on('click', function() {
                if (!currentDetailProduct) return;
                const productId = currentDetailProduct.id;
                
                const listBtn = $(`.import-single-btn[data-id="${productId}"]`);
                const modalBtn = $(this);
                
                const originalModalHtml = modalBtn.html();
                modalBtn.prop('disabled', true).html('<i class="las la-spinner la-spin"></i> Importing...');
                
                importSingleProduct(productId, listBtn);
                
                setTimeout(() => {
                    modalBtn.prop('disabled', false).html(originalModalHtml);
                    const modalEl = document.getElementById('productDetailsModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }, 1200);
            });
        });
    </script>
@endpush

@push('style')
    <style>
        /* Custom Premium Dashboard Style */
        .sync-container {
            font-family: 'Outfit', 'Inter', sans-serif;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Segmented Control Pill Tab Styling */
        .sync-tabs-card {
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 10px 30px rgba(0,0,0,0.02);
            border-radius: 16px;
            background: #fff;
            overflow: hidden;
        }
        .nav-pills.custom-nav-pills {
            background: #f1f5f9;
            padding: 6px;
            border-radius: 12px;
            border: none;
            display: flex;
            width: 100%;
        }
        .nav-pills.custom-nav-pills .nav-item {
            flex: 1;
        }
        .nav-pills.custom-nav-pills .nav-link {
            border: none;
            width: 100%;
            justify-content: center;
            padding: 12px 24px;
            font-weight: 600;
            color: #64748b;
            background: transparent !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .nav-pills.custom-nav-pills .nav-link.active {
            background: #fff !important;
            color: #4f46e5 !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06), 0 1px 3px rgba(0,0,0,0.02);
            transform: scale(1.02);
        }
        .nav-pills.custom-nav-pills .nav-link:not(.active):hover {
            color: #334155;
            background: rgba(0, 0, 0, 0.03) !important;
        }

        /* Card Styling with Interactive lift */
        .premium-card {
            border: 1px solid rgba(226, 232, 240, 0.8) !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.03), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
            border-radius: 16px !important;
            overflow: hidden;
            background: #fff;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .premium-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 35px -5px rgba(79, 70, 229, 0.06), 0 12px 16px -6px rgba(79, 70, 229, 0.06);
            border-color: rgba(99, 102, 241, 0.25) !important;
        }
        .premium-card .card-header {
            padding: 18px 24px;
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .premium-card.border--dark {
            border-top: none !important;
        }
        .premium-card.border--primary {
            border-top: none !important;
        }
        
        /* Headers elegant linear gradients */
        .bg--dark-custom {
            background: linear-gradient(135deg, #334155 0%, #1e293b 100%) !important;
            color: #fff !important;
        }
        .bg--primary-custom {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%) !important;
            color: #fff !important;
        }
        .bg-primary-custom {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%) !important;
            color: #fff !important;
        }
        .bg-info {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%) !important;
            color: #fff !important;
            border: none !important;
        }

        /* Inputs & Form Controls with Focus Glow */
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 10px 14px;
            font-size: 14px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            color: #334155;
            background-color: #f8fafc;
        }
        .form-control:focus, .form-select:focus {
            background-color: #fff;
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
        }
        .input-group-text {
            border-radius: 10px 0 0 10px;
            border: 1px solid #e2e8f0;
            background-color: #f1f5f9;
        }
        .fw-bold {
            color: #1e293b;
            font-weight: 600 !important;
            font-size: 13.5px;
        }
        .text-muted {
            font-size: 12px;
            color: #64748b !important;
        }

        /* Interactive Checkboxes & Toggle Switches */
        .form-check-input {
            border-radius: 6px;
            border-color: #cbd5e1;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .form-check-input:checked {
            background-color: #4f46e5;
            border-color: #4f46e5;
            box-shadow: 0 2px 5px rgba(79, 70, 229, 0.2);
        }

        /* Custom Premium Table Layout */
        .premium-table-wrapper {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0 !important;
        }
        .premium-table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }
        .premium-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.6px;
            padding: 14px 18px;
            border-bottom: 2px solid #f1f5f9;
        }
        .premium-table td {
            padding: 14px 18px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            transition: background-color 0.2s ease;
        }
        .premium-table tbody tr {
            transition: all 0.2s ease;
        }
        .premium-table tbody tr:hover td {
            background-color: rgba(99, 102, 241, 0.03) !important;
        }
        .premium-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Rounded images with zoom effect */
        .product-thumb {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .product-thumb:hover {
            transform: scale(1.15);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        /* Glowing Badges */
        .badge-custom {
            padding: 6px 12px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            border: 1px solid transparent;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        }
        .badge-custom.imported {
            background: rgba(16, 185, 129, 0.12) !important;
            color: #10b981 !important;
            border-color: rgba(16, 185, 129, 0.2) !important;
            box-shadow: 0 2px 10px rgba(16, 185, 129, 0.05);
        }
        .badge-custom.not-imported {
            background: rgba(245, 158, 11, 0.12) !important;
            color: #f59e0b !important;
            border-color: rgba(245, 158, 11, 0.2) !important;
            box-shadow: 0 2px 10px rgba(245, 158, 11, 0.05);
        }
        .badge-custom.in-stock {
            background: rgba(59, 130, 246, 0.12) !important;
            color: #3b82f6 !important;
            border-color: rgba(59, 130, 246, 0.2) !important;
        }
        .badge-custom.out-of-stock {
            background: rgba(239, 68, 68, 0.12) !important;
            color: #ef4444 !important;
            border-color: rgba(239, 68, 68, 0.2) !important;
        }

        /* Buttons Custom */
        .btn-custom-primary {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%) !important;
            border: none !important;
            color: #fff !important;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);
        }
        .btn-custom-primary:hover {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%) !important;
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.35);
            transform: translateY(-2px);
        }
        .btn-custom-outline {
            border: 1px solid #e2e8f0 !important;
            background: #fff !important;
            color: #475569 !important;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.25s ease;
        }
        .btn-custom-outline:hover {
            background: #f8fafc !important;
            border-color: #cbd5e1 !important;
            color: #1e293b !important;
            transform: translateY(-1px);
        }

        /* Futuristic macOS simulated Terminal wrapper */
        .terminal-wrapper {
            box-shadow: 0 15px 35px rgba(0,0,0,0.18);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #1e293b;
        }
        .terminal-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        .dot-red { background: #ef4444; }
        .dot-yellow { background: #f59e0b; }
        .dot-green { background: #10b981; }
        .terminal-log {
            background: #0f172a !important;
            border-radius: 0;
            border: none !important;
            padding: 16px !important;
            font-family: 'Fira Code', 'Courier New', monospace;
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.5);
            color: #cbd5e1 !important;
        }

        /* Neon glow progress bars */
        .progress {
            background-color: rgba(255, 255, 255, 0.1) !important;
            height: 12px !important;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.2);
        }
        .progress-bar {
            background: linear-gradient(90deg, #6366f1, #3b82f6) !important;
            box-shadow: 0 0 10px rgba(99, 102, 241, 0.5);
            border-radius: 30px;
        }

        /* Custom scrollbar globally for scrollable wrappers */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        .progress-card::-webkit-scrollbar-track,
        .terminal-log::-webkit-scrollbar-track,
        #log-console::-webkit-scrollbar-track {
            background: #1e293b;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        #log-console::-webkit-scrollbar-thumb,
        .terminal-log::-webkit-scrollbar-thumb {
            background: #334155;
        }

        /* Detail Modal price elements */
        .price-summary-box {
            background: linear-gradient(135deg, #e0e7ff 0%, #e0f2fe 100%);
            border-radius: 12px;
            padding: 16px;
            border: 1px solid rgba(79, 70, 229, 0.1);
        }
        
        /* Font Loading */
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');
    </style>
@endpush
