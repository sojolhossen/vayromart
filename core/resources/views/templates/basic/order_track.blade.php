@extends('Template::layouts.master')

@section('content')
    <div class="order-track-section py-60">
        <div class="container">
            <h5 class="title mb-4 text-center" style="font-weight: 700; font-size: 2rem; color: #333;">@lang('Track Your Order')</h5>
            <p class="text-muted text-center mb-5" style="font-size: 1rem; margin-top: -15px;">@lang('Enter your order number below to check the real-time status of your package.')</p>
            
            <div class="row justify-content-center mb-5">
                <div class="col-lg-8 col-md-10 col-xl-6">
                    <form class="order-track-form" id="order-track">
                        <div class="order-track-form-group">
                            <input type="text" name="order_number" placeholder="@lang('Enter Your Order ID (e.g. OID-00001)')" required>
                            <button type="submit" class="track-btn">@lang('Track Now')</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Custom alert for canceled/returned states (initially hidden) -->
            <div class="row justify-content-center d-none mb-4" id="order-alert-box">
                <div class="col-lg-10 col-xl-8">
                    <div class="alert alert-danger d-flex align-items-center gap-3 border-0 rounded-3 p-3 shadow-sm" role="alert">
                        <i class="las la-exclamation-circle" style="font-size: 2rem;"></i>
                        <div>
                            <h6 class="alert-heading mb-1" style="font-weight: 700;" id="alert-title"></h6>
                            <p class="mb-0" id="alert-desc" style="font-size: 0.9rem;"></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-10 col-xl-8">
                    <div class="order-track-wrapper d-flex flex-wrap justify-content-center">
                        <div class="order-track-progress-bar"></div>
                        
                        <div class="confirm-state order-track-item">
                            <div class="thumb">
                                <i class="las la-check-square"></i>
                            </div>
                            <div class="content">
                                <h6 class="title">@lang('Pending')</h6>
                                <span class="status-date d-block text-muted mt-1" style="font-size: 0.72rem; min-height: 15px;"></span>
                            </div>
                        </div>

                        <div class="order-track-item processing-state">
                            <div class="thumb">
                                <i class="las la-sync-alt"></i>
                            </div>
                            <div class="content">
                                <h6 class="title">@lang('Processing')</h6>
                                <span class="status-date d-block text-muted mt-1" style="font-size: 0.72rem; min-height: 15px;"></span>
                            </div>
                        </div>

                        <div class="order-track-item dispatched-state">
                            <div class="thumb">
                                <i class="las la-truck-pickup"></i>
                            </div>
                            <div class="content">
                                <h6 class="title">@lang('Dispatched')</h6>
                                <span class="status-date d-block text-muted mt-1" style="font-size: 0.72rem; min-height: 15px;"></span>
                            </div>
                        </div>

                        <div class="order-track-item delivered-state">
                            <div class="thumb">
                                <i class="las la-map-signs"></i>
                            </div>
                            <div class="content">
                                <h6 class="title">@lang('Delivered')</h6>
                                <span class="status-date d-block text-muted mt-1" style="font-size: 0.72rem; min-height: 15px;"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Order Summary Details Card (initially hidden) -->
            <div class="row justify-content-center mt-5 d-none" id="order-details-card">
                <div class="col-lg-10 col-xl-8">
                    <div class="card border-0 shadow-lg rounded-4 overflow-hidden" style="background: rgba(255, 255, 255, 0.92); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.6);">
                        <div class="card-header bg-transparent py-4 border-bottom border-light">
                            <h5 class="card-title mb-0 text-center text-dark" style="font-weight: 700; font-size: 1.35rem; letter-spacing: 0.3px;">@lang('Order Summary & Items')</h5>
                        </div>
                        <div class="card-body p-4 p-md-5">
                            <div class="row g-4 pb-4 border-bottom border-light">
                                <div class="col-md-3 col-6">
                                    <span class="text-muted d-block mb-1" style="font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">@lang('Order Number')</span>
                                    <strong id="detail-order-number" class="text-dark" style="font-size: 1.05rem;"></strong>
                                </div>
                                <div class="col-md-3 col-6">
                                    <span class="text-muted d-block mb-1" style="font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">@lang('Order Date')</span>
                                    <strong id="detail-order-date" class="text-dark" style="font-size: 1.05rem;"></strong>
                                </div>
                                <div class="col-md-3 col-6">
                                    <span class="text-muted d-block mb-1" style="font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">@lang('Total Amount')</span>
                                    <strong id="detail-total-amount" class="text-dark" style="font-size: 1.25rem; font-weight: 800; color: hsl(var(--base)) !important;"></strong>
                                </div>
                                <div class="col-md-3 col-6 text-md-end">
                                    <span class="text-muted d-block mb-1" style="font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">@lang('Payment')</span>
                                    <span id="detail-payment-status" class="badge py-2 px-3 rounded-pill" style="font-size: 0.8rem; font-weight: 600; letter-spacing: 0.3px;"></span>
                                </div>
                            </div>

                            <!-- List of Items in the Order -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h6 class="mb-3 text-dark pb-2 border-bottom border-light" style="font-weight: 700; font-size: 1.05rem;">@lang('Items Ordered')</h6>
                                    <div id="detail-items-list" class="d-flex flex-column gap-3">
                                        <!-- Rendered dynamically -->
                                    </div>
                                </div>
                            </div>

                            <!-- Recipient and Shipping info -->
                            <div class="row mt-5 border-top border-light pt-4" id="shipping-details-row">
                                <div class="col-12">
                                    <h6 class="mb-3 text-dark" style="font-weight: 700; font-size: 1.05rem;">@lang('Delivery Information')</h6>
                                    <div class="row g-3">
                                        <div class="col-sm-6">
                                            <span class="text-muted d-block mb-1" style="font-size: 0.82rem; font-weight: 500;">@lang('Recipient Name')</span>
                                            <strong id="detail-recipient-name" class="text-dark" style="font-size: 0.95rem;"></strong>
                                        </div>
                                        <div class="col-sm-6">
                                            <span class="text-muted d-block mb-1" style="font-size: 0.82rem; font-weight: 500;">@lang('Recipient Phone')</span>
                                            <strong id="detail-recipient-mobile" class="text-dark" style="font-size: 0.95rem;"></strong>
                                        </div>
                                        <div class="col-12">
                                            <span class="text-muted d-block mb-1" style="font-size: 0.82rem; font-weight: 500;">@lang('Shipping Address')</span>
                                            <strong id="detail-shipping-address" class="text-dark" style="font-size: 0.95rem; font-weight: 500;"></strong>
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
@endsection

@push('style')
    <style>
        .order-track-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #f1f3f5 100%);
            position: relative;
            overflow: hidden;
        }
        
        /* Background decor elements */
        .order-track-section::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(94, 234, 212, 0.08) 0%, transparent 70%);
            top: -100px;
            left: -100px;
            border-radius: 50%;
            pointer-events: none;
        }

        .order-track-section::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(94, 234, 212, 0.08) 0%, transparent 70%);
            bottom: -150px;
            right: -150px;
            border-radius: 50%;
            pointer-events: none;
        }

        /* Glassmorphic tracking form */
        .order-track-form {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.6);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .order-track-form:focus-within {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
        }

        .order-track-form-group {
            display: flex;
            background: #fff;
            padding: 6px;
            border-radius: 30px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.02);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .order-track-form-group input {
            flex-grow: 1;
            border: none !important;
            outline: none !important;
            padding: 12px 24px;
            font-size: 0.95rem;
            color: #333;
            background: transparent;
            height: auto !important;
        }

        .order-track-form-group input::placeholder {
            color: #aaa;
        }

        .order-track-form-group .track-btn {
            background: hsl(var(--base)) !important;
            border: none !important;
            border-radius: 30px !important;
            padding: 12px 30px !important;
            color: #fff !important;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease !important;
            height: auto !important;
        }

        .order-track-form-group .track-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            background: hsl(var(--base)) !important;
            opacity: 0.95;
        }

        /* Redesigned tracking wrapper and timeline */
        .order-track-wrapper {
            position: relative;
            margin-top: 40px;
            padding: 20px 0;
        }

        .order-track-item {
            width: 25%;
            position: relative;
            text-align: center;
            z-index: 2;
        }

        /* Connector Line background */
        .order-track-wrapper::before {
            content: '';
            position: absolute;
            top: 60px;
            left: 12.5%;
            width: 75%;
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            z-index: 1;
        }

        /* Active Connector Line overlay */
        .order-track-progress-bar {
            position: absolute;
            top: 60px;
            left: 12.5%;
            width: 0%;
            height: 4px;
            background: linear-gradient(90deg, hsl(var(--base)) 0%, #28a745 100%);
            border-radius: 2px;
            z-index: 1;
            transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .order-track-item .thumb {
            width: 80px;
            height: 80px;
            background: #fff;
            border: 4px solid #e9ecef;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.5s ease;
            position: relative;
            z-index: 3;
        }

        .order-track-item .thumb i {
            font-size: 2rem;
            color: #adb5bd;
            transition: all 0.5s ease;
        }

        .order-track-item .content .title {
            font-size: 1.05rem;
            font-weight: 600;
            color: #6c757d;
            margin-top: 10px;
            transition: all 0.5s ease;
        }

        /* Active states style overrides */
        .order-track-item.active .thumb {
            border-color: #28a745;
            background: #28a745;
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.4);
            transform: scale(1.1);
        }

        .order-track-item.active .thumb i {
            color: #fff;
            transform: scale(1.1);
        }

        .order-track-item.active .content .title {
            color: #28a745;
            font-weight: 700;
        }

        /* Pulsing animation for the current active state */
        .order-track-item.active.latest-active .thumb {
            animation: pulse-active 2s infinite;
        }

        @keyframes pulse-active {
            0% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.5);
            }
            70% {
                box-shadow: 0 0 0 15px rgba(40, 167, 69, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
            }
        }

        /* Rotation spin for processing state if active but not completed */
        .order-track-item.processing-state.active.latest-active .thumb i {
            animation: spin 3s linear infinite;
        }

        @keyframes spin {
            100% {
                transform: rotate(360deg);
            }
        }

        /* Hide the old dashed after lines */
        .order-track-item::after {
            display: none !important;
        }

        /* Product items list animations */
        .detail-item-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            border-radius: 10px;
            background: rgba(248, 249, 250, 0.6);
            border: 1px solid rgba(0, 0, 0, 0.03);
            transition: all 0.25s ease;
        }

        .detail-item-card:hover {
            background: rgba(248, 249, 250, 0.9);
            transform: translateX(4px);
            border-color: rgba(0, 0, 0, 0.08);
        }

        /* Responsive timeline */
        @media (max-width: 991px) {
            .order-track-item .thumb {
                width: 65px;
                height: 65px;
                border-width: 3px;
            }
            .order-track-item .thumb i {
                font-size: 1.6rem;
            }
            .order-track-wrapper::before,
            .order-track-progress-bar {
                top: 52px;
            }
        }

        @media (max-width: 767px) {
            .order-track-wrapper {
                flex-direction: column;
                align-items: flex-start;
                padding-left: 30px;
                margin-left: auto;
                margin-right: auto;
                max-width: 250px;
            }
            .order-track-item {
                width: 100%;
                text-align: left;
                display: flex;
                align-items: center;
                margin-bottom: 30px;
            }
            .order-track-item:last-child {
                margin-bottom: 0;
            }
            .order-track-item .thumb {
                margin: 0 20px 0 0;
                width: 60px;
                height: 60px;
            }
            .order-track-item .content .title {
                margin-top: 0;
            }
            /* Vertical progress bar line */
            .order-track-wrapper::before {
                top: 30px;
                left: 30px;
                width: 4px;
                height: calc(100% - 60px);
            }
            .order-track-progress-bar {
                top: 30px;
                left: 30px;
                width: 4px;
                height: 0%;
                background: linear-gradient(180deg, hsl(var(--base)) 0%, #28a745 100%);
                transition: height 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            }
        }
    </style>
@endpush

@push('script')
    <script>
        'use strict';
        (function($) {
            $(document).on('submit', '#order-track', function(e) {
                e.preventDefault();

                let orderNumber = $('input[name=order_number]').val().trim();
                if(!orderNumber) return;

                $('.track-btn').attr('disabled', true).html('<i class="las la-spinner la-spin"></i> Loading...');
                $('#order-details-card').addClass('d-none');
                $('#order-alert-box').addClass('d-none');

                $.get(`{{ route('track.order', '') }}/${orderNumber}`, function(response) {
                    if (response.success) {
                        if (response.status == {{ Status::ORDER_CANCELED }}) {
                            $('.confirm-state, .processing-state, .dispatched-state, .delivered-state').removeClass('active latest-active');
                            $('.status-date').text('');
                            updateProgressBar(0);
                            
                            // Custom cancel banner
                            $('#alert-title').text("@lang('Order Cancelled')");
                            $('#alert-desc').text("@lang('This order has been cancelled by the store administrator.')");
                            $('#order-alert-box').removeClass('d-none').hide().fadeIn(400);
                        } else if (response.status == {{ Status::ORDER_RETURNED }}) {
                            $('.confirm-state, .processing-state, .dispatched-state, .delivered-state').removeClass('active latest-active');
                            $('.status-date').text('');
                            updateProgressBar(0);
                            
                            // Custom return banner
                            $('#alert-title').text("@lang('Order Returned')");
                            $('#alert-desc').text("@lang('This order has been cancelled/returned by the customer.')");
                            $('#order-alert-box').removeClass('d-none').hide().fadeIn(400);
                        } else {
                            // Populate active classes and date/time logs
                            if(response.status >= '{{ Status::ORDER_PENDING }}') {
                                $('.confirm-state').addClass('active').find('.status-date').text(response.pending_time);
                            } else {
                                $('.confirm-state').removeClass('active').find('.status-date').text('');
                            }
                            
                            if(response.status >= '{{ Status::ORDER_PROCESSING }}') {
                                $('.processing-state').addClass('active').find('.status-date').text(response.processing_time);
                            } else {
                                $('.processing-state').removeClass('active').find('.status-date').text(response.processing_time);
                            }
                            
                            if(response.status >= '{{ Status::ORDER_DISPATCHED }}') {
                                $('.dispatched-state').addClass('active').find('.status-date').text(response.dispatched_time);
                            } else {
                                $('.dispatched-state').removeClass('active').find('.status-date').text(response.dispatched_time);
                            }
                            
                            if(response.status >= '{{ Status::ORDER_DELIVERED }}') {
                                $('.delivered-state').addClass('active').find('.status-date').text(response.delivered_time);
                            } else {
                                $('.delivered-state').removeClass('active').find('.status-date').text(response.delivered_time);
                            }
                            
                            // Highlight the latest active item
                            $('.order-track-item').removeClass('latest-active');
                            let activeItems = $('.order-track-item.active');
                            activeItems.last().addClass('latest-active');
                            
                            // Update progress line length
                            let activeCount = activeItems.length;
                            updateProgressBar(activeCount);

                            // Load summary details card
                            $('#detail-order-number').text('#' + response.order_number);
                            $('#detail-order-date').text(response.date);
                            $('#detail-total-amount').text(response.total_amount);
                            
                            let payStatus = $('#detail-payment-status');
                            payStatus.text(response.payment_status_text);
                            if(response.payment_status == 1) { // Paid
                                payStatus.removeClass('bg-danger bg-warning').addClass('bg-success text-white');
                            } else {
                                payStatus.removeClass('bg-success bg-warning').addClass('bg-danger text-white');
                            }

                            // Dynamic items rendering
                            let itemsList = $('#detail-items-list');
                            itemsList.empty();
                            if(response.items && response.items.length > 0) {
                                $.each(response.items, function(i, item) {
                                    let variantHtml = item.variant ? `<span class="badge bg-secondary font-weight-normal ms-2" style="font-size: 0.75rem;">${item.variant}</span>` : '';
                                    itemsList.append(`
                                        <div class="detail-item-card">
                                            <img src="${item.image}" alt="item-image" style="width: 55px; height: 55px; border-radius: 8px; object-fit: cover; background: #fff; border: 1px solid rgba(0,0,0,0.05);">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 text-dark" style="font-size: 0.9rem; font-weight: 600;">${item.name} ${variantHtml}</h6>
                                                <span class="text-muted" style="font-size: 0.82rem;">${item.price} x ${item.quantity}</span>
                                            </div>
                                            <div class="text-end">
                                                <strong class="text-dark" style="font-size: 0.95rem;">${item.total}</strong>
                                            </div>
                                        </div>
                                    `);
                                });
                            }

                            // Dynamic shipping info rendering
                            if(response.shipping_address) {
                                $('#detail-recipient-name').text(response.recipient_name || 'N/A');
                                $('#detail-recipient-mobile').text(response.recipient_mobile || 'N/A');
                                $('#detail-shipping-address').text(response.shipping_address);
                                $('#shipping-details-row').removeClass('d-none');
                            } else {
                                $('#shipping-details-row').addClass('d-none');
                            }

                            // Show the details card with a nice transition
                            $('#order-details-card').removeClass('d-none').hide().fadeIn(500);
                        }
                    } else {
                        $('.confirm-state, .processing-state, .dispatched-state, .delivered-state').removeClass('active latest-active');
                        $('.status-date').text('');
                        updateProgressBar(0);
                        notify('error', response.error);
                    }

                    $('.track-btn').attr('disabled', false).text("@lang('Track Now')");
                });
            });

            function updateProgressBar(activeCount) {
                let progressPercent = 0;
                if (activeCount > 1) {
                    progressPercent = ((activeCount - 1) / 3) * 100;
                }
                
                if ($(window).width() > 767) {
                    $('.order-track-progress-bar').css({
                        'width': progressPercent + '%',
                        'height': '4px'
                    });
                } else {
                    $('.order-track-progress-bar').css({
                        'height': progressPercent + '%',
                        'width': '4px'
                    });
                }
            }
            
            // Adjust progress bar size on resize
            $(window).on('resize', function() {
                let activeCount = $('.order-track-item.active').length;
                updateProgressBar(activeCount);
            });
            
        })(jQuery)
    </script>
@endpush

@push('style-lib')
    <link href="{{ asset($activeTemplateTrue . 'css/order-track.css') }}" rel="stylesheet">
@endpush
