@extends('admin.layouts.app')

@section('panel')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
    body, .page-wrapper, .navbar, .sidebar {
        font-family: 'Poppins', sans-serif !important;
    }
    :root {
        --vm-primary: #ff5b00;
        --vm-primary-hover: #e04f00;
        --vm-accent: #fbbf24;
    }
    .vm-gradient-text {
        background: linear-gradient(135deg, var(--vm-primary) 0%, var(--vm-accent) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .vm-card {
        background: #ffffff;
        border: 1px solid #eaeaea;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
        transition: all 0.3s ease;
    }
    .vm-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 25px rgba(255, 91, 0, 0.05);
    }
    .vm-btn-primary {
        background: linear-gradient(135deg, var(--vm-primary) 0%, #ff7f24 100%);
        color: #ffffff !important;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        padding: 10px 20px;
        box-shadow: 0 4px 15px rgba(255, 91, 0, 0.15);
        transition: all 0.3s ease;
    }
    .vm-btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(255, 91, 0, 0.25);
    }
    .status-badge {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        padding: 4px 10px;
        border-radius: 100px;
        display: inline-block;
    }
    .status-Approved { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .status-Processing { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .status-Shipment { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
    .status-Cancelled { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .status-Returned { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

    .daily-summary-header {
        background-color: rgba(255, 91, 0, 0.03);
        border-bottom: 2px solid rgba(255, 91, 0, 0.1);
        font-weight: 700;
    }
    .table td, .table th {
        vertical-align: middle;
        border-top: 1px solid #f1f1f1;
        padding: 12px 15px;
    }
    .form-control, .form-select {
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        padding: 10px 14px;
        transition: all 0.3s ease;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--vm-primary);
        box-shadow: 0 0 0 3px rgba(255, 91, 0, 0.15);
    }
</style>

<div class="row gy-4">
    <!-- Header Section -->
    <div class="col-12 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
        <div>
            <h3 class="vm-gradient-text fw-bold mb-1">VayroMart Sales Tracker</h3>
            <p class="text-muted text-sm mb-0">Manage and track your orders profit metrics in real-time spreadsheet style.</p>
        </div>
        <button class="vm-btn-primary" onclick="openAddModal()">
            <i class="la la-plus me-1"></i> Add New Order
        </button>
    </div>

    <!-- Stats Overview Widgets -->
    <div class="col-12">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="vm-card p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted text-xs text-uppercase fw-bold">Total Orders</span>
                        <h3 class="fw-bold mt-1 mb-0">{{ $stats['totalOrdersCount'] }}</h3>
                    </div>
                    <div class="bg-light p-3 rounded-3 text-dark">
                        <i class="la la-shopping-bag fs-3"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="vm-card p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted text-xs text-uppercase fw-bold">Net Revenue</span>
                        <h3 class="fw-bold mt-1 mb-0 text-success">৳{{ $stats['totalRevenue'] }}</h3>
                    </div>
                    <div class="bg-success-light p-3 rounded-3 text-success" style="background: rgba(16, 185, 129, 0.1);">
                        <span class="fw-bold fs-4">৳</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="vm-card p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted text-xs text-uppercase fw-bold">Net Profit</span>
                        <h3 class="fw-bold mt-1 mb-0 @if($stats['totalProfit'] >= 0) text-success @else text-danger @endif">
                            ৳{{ $stats['totalProfit'] }}
                        </h3>
                    </div>
                    <div class="p-3 rounded-3 @if($stats['totalProfit'] >= 0) text-success @else text-danger @endif" style="background: @if($stats['totalProfit'] >= 0) rgba(16, 185, 129, 0.1) @else rgba(239, 68, 68, 0.1) @endif">
                        <i class="la @if($stats['totalProfit'] >= 0) la-arrow-up @else la-arrow-down @endif fs-3"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="vm-card p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted text-xs text-uppercase fw-bold">Profit Margin</span>
                        <h3 class="fw-bold mt-1 mb-0">{{ $stats['profitMargin'] }}%</h3>
                    </div>
                    <div class="bg-warning-light p-3 rounded-3 text-warning" style="background: rgba(245, 158, 11, 0.1);">
                        <i class="la la-pie-chart fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Toolbar -->
    <div class="col-12">
        <div class="vm-card p-4">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search ID, Customer, Code..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="ALL" @selected(request('status') == 'ALL')>All Statuses</option>
                        <option value="Approved" @selected(request('status') == 'Approved')>Approved</option>
                        <option value="Processing" @selected(request('status') == 'Processing')>Processing</option>
                        <option value="Shipment" @selected(request('status') == 'Shipment')>Shipment</option>
                        <option value="Cancelled" @selected(request('status') == 'Cancelled')>Cancelled</option>
                        <option value="Returned" @selected(request('status') == 'Returned')>Returned</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="startDate" class="form-control" value="{{ request('startDate') }}">
                </div>
                <div class="col-md-2">
                    <input type="date" name="endDate" class="form-control" value="{{ request('endDate') }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-dark w-100 rounded-3">
                        <i class="la la-filter me-1"></i> Filter
                    </button>
                    <a href="{{ route('admin.sales_tracker.index') }}" class="btn btn-outline-secondary w-100 rounded-3 d-flex align-items-center justify-content-center">
                        Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders Spreadsheet Table -->
    <div class="col-12">
        <div class="vm-card overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr class="text-uppercase text-muted" style="font-size: 11px;">
                            <th class="ps-4">Order ID</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Product Code/Name</th>
                            <th class="text-end">Cost</th>
                            <th class="text-end">Sell</th>
                            <th class="text-end">Other</th>
                            <th class="text-end">Profit/Loss</th>
                            <th>Address</th>
                            <th class="text-center pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($grouped as $dateKey => $orders)
                            @php
                                $dailyRevenue = 0;
                                $dailyProfit = 0;
                                foreach($orders as $o) {
                                    if ($o['status'] !== 'Cancelled' && $o['status'] !== 'Returned') {
                                        $dailyRevenue += $o['productSellPrice'];
                                    }
                                    $dailyProfit += $o['profit'];
                                }
                            @endphp

                            @foreach($orders as $o)
                                <tr>
                                    <td class="ps-4 fw-bold text-primary">{{ $o['orderId'] }}</td>
                                    <td class="text-muted text-sm">{{ date('h:i A', strtotime($o['dateTime'])) }}</td>
                                    <td>
                                        <span class="status-badge status-{{ $o['status'] }}">{{ $o['status'] }}</span>
                                    </td>
                                    <td class="fw-semibold">{{ $o['customerName'] }}</td>
                                    <td class="text-mono text-muted text-sm">{{ $o['customerNumber'] }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark border me-1">{{ $o['productCode'] }}</span>
                                        <span class="text-sm fw-medium text-dark">{{ $o['productName'] }}</span>
                                    </td>
                                    <td class="text-end text-muted font-mono">৳{{ number_format($o['productPrice'], 2) }}</td>
                                    <td class="text-end fw-semibold font-mono">৳{{ number_format($o['productSellPrice'], 2) }}</td>
                                    <td class="text-end text-muted font-mono">৳{{ number_format($o['otherCost'], 2) }}</td>
                                    <td class="text-end font-mono fw-bold @if($o['profit'] >= 0) text-success @else text-danger @endif">
                                        ৳{{ number_format($o['profit'], 2) }}
                                    </td>
                                    <td class="text-sm text-muted max-width-150 text-truncate" title="{{ $o['address'] }}">
                                        {{ $o['address'] }}
                                    </td>
                                    <td class="text-center pe-4">
                                        <div class="d-flex justify-content-center gap-2">
                                            <button class="btn btn-sm btn-outline-primary" onclick="openEditModal({{ json_encode($o) }})">
                                                <i class="la la-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteOrder('{{ $o['id'] }}')">
                                                <i class="la la-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach

                            <!-- Daily Summary Separator Row -->
                            <tr class="daily-summary-header text-dark">
                                <td colspan="2" class="ps-4 fw-bold text-uppercase" style="font-size: 10px;">Daily Summary</td>
                                <td colspan="3" class="text-muted">{{ $dateKey }}</td>
                                <td class="fw-bold">Orders: {{ count($orders) }}</td>
                                <td colspan="2" class="text-end fw-bold text-success font-mono">Rev: ৳{{ number_format($dailyRevenue, 2) }}</td>
                                <td class="text-end text-muted">Net:</td>
                                <td class="text-end fw-bold font-mono @if($dailyProfit >= 0) text-success @else text-danger @endif">
                                    ৳{{ number_format($dailyProfit, 2) }}
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center py-5 text-muted">
                                    <i class="la la-search fs-1 mb-2 d-block"></i> No sales orders found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Form Dialog -->
<div class="modal fade" id="orderModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold vm-gradient-text" id="modalTitle">Add New Order</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="orderForm" onsubmit="handleFormSubmit(event)">
                @csrf
                <input type="hidden" id="orderId" name="orderId">
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- AI Auto-Fill Textarea -->
                        <div class="col-12 border-bottom pb-3 mb-2">
                            <label class="text-primary fw-bold text-xs d-flex align-items-center gap-1">
                                <i class="la la-magic fs-5"></i> <strong>AI Auto-Fill / Paste Chat Details</strong>
                            </label>
                            <textarea id="aiRawText" class="form-control border-primary" rows="4" placeholder="মেসেঞ্জার বা ফেসবুকের পুরো কপি করা টেক্সট এখানে পেস্ট করুন (যেমন: Shaharul Islam, 01949280123, Mirpur 11, Q10 HiFi Ster.. - 3126, Due :1000 etc.)..." oninput="parseRawText(this.value)"></textarea>
                            <small class="text-muted text-xs d-block mt-1">এখানে মেসেজ পেস্ট করলেই নিচের ফিল্ডগুলো ম্যাজিক্যালি অটো-ফিলাপ হয়ে যাবে!</small>
                        </div>

                        <div class="col-md-6 mt-3">
                            <label class="text-muted fw-bold text-xs">Date & Time</label>
                            <input type="datetime-local" id="formDateTime" name="dateTime" class="form-control">
                        </div>
                        <div class="col-md-6 mt-3">
                            <label class="text-muted fw-bold text-xs">Order Status</label>
                            <select id="formStatus" name="status" class="form-select">
                                <option value="Approved">Approved</option>
                                <option value="Processing">Processing</option>
                                <option value="Shipment">Shipment</option>
                                <option value="Cancelled">Cancelled</option>
                                <option value="Returned">Returned</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted fw-bold text-xs">Customer Name</label>
                            <input type="text" id="formCustomerName" name="customerName" class="form-control" required placeholder="John Doe">
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted fw-bold text-xs">Customer Phone</label>
                            <input type="text" id="formCustomerNumber" name="customerNumber" class="form-control" required placeholder="01711223344">
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted fw-bold text-xs">Product Code</label>
                            <input type="text" id="formProductCode" name="productCode" class="form-control" required placeholder="e.g. PRD-A101">
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted fw-bold text-xs">Product Name</label>
                            <input type="text" id="formProductName" name="productName" class="form-control" required placeholder="Memory Foam Pillow">
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted fw-bold text-xs">Cost Price (৳)</label>
                            <input type="number" step="0.01" id="formProductPrice" name="productPrice" class="form-control font-mono" required placeholder="0.00" oninput="calculateProfit()">
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted fw-bold text-xs">Sell Price (৳)</label>
                            <input type="number" step="0.01" id="formProductSellPrice" name="productSellPrice" class="form-control font-mono" required placeholder="0.00" oninput="calculateProfit()">
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted fw-bold text-xs">Other Cost (৳)</label>
                            <input type="number" step="0.01" id="formOtherCost" name="otherCost" class="form-control font-mono" required placeholder="0.00" oninput="calculateProfit()">
                        </div>
                        <div class="col-12">
                            <label class="text-muted fw-bold text-xs">Delivery Address</label>
                            <textarea id="formAddress" name="address" class="form-control" rows="2" required placeholder="Enter full shipping address..."></textarea>
                        </div>
                    </div>

                    <!-- Profit Preview -->
                    <div class="bg-light rounded-3 p-3 d-flex justify-content-between align-items-center mt-3" style="border: 1px dashed rgba(255, 91, 0, 0.2);">
                        <span class="fw-semibold text-muted text-sm">Estimated Profit:</span>
                        <span class="fw-bold font-mono text-lg text-success" id="profitPreview">৳0.00</span>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark rounded-3" id="submitBtn">Save Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let mode = 'ADD';

    function parseRawText(text) {
        if (!text.trim()) return;

        // 1. Parse Phone Number
        let phoneMatch = text.match(/(01\d{9})/);
        if (phoneMatch) {
            document.getElementById('formCustomerNumber').value = phoneMatch[0];
        }

        // 2. Parse Name
        // Find lines that are non-empty and don't contain order patterns, phone, or price structures
        let lines = text.split('\n').map(l => l.trim()).filter(l => l.length > 0);
        let nameFound = '';
        for (let i = 0; i < lines.length; i++) {
            let line = lines[i];
            if (line.match(/^[D|O]\d{4,8}/) || line.includes('01') || line.match(/all:/) || line.match(/:/) || line.match(/total/i)) {
                continue;
            }
            if (line.length > 2 && line.length < 35 && isNaN(line)) {
                nameFound = line;
                break;
            }
        }
        if (nameFound) {
            document.getElementById('formCustomerName').value = nameFound;
        }

        // 3. Parse Address (Lines after phone number, or line containing common address terms)
        let addressTerms = ['road', 'road', 'block', 'block', 'line', 'line', 'house', 'house', 'dhaka', 'dhaka', 'mirpur', 'chittagong', 'sylhet', 'sector', 'flat', 'floor'];
        let addressFound = '';
        for (let line of lines) {
            let lower = line.toLowerCase();
            let hasAddressTerm = addressTerms.some(term => lower.includes(term));
            if (hasAddressTerm && !line.includes('01') && !line.includes(':')) {
                addressFound = line;
                break;
            }
        }
        if (addressFound) {
            document.getElementById('formAddress').value = addressFound;
        }

        // 4. Parse Product Code and Name
        // Example: Q10 HiFi Ster.. - 3126
        for (let line of lines) {
            if (line.includes(' - ') && line.match(/\d+$/)) {
                let parts = line.split(' - ');
                document.getElementById('formProductCode').value = parts[1].trim();
                document.getElementById('formProductName').value = parts[0].trim();
                break;
            }
        }

        // 5. Parse Pricing
        // Cost Price (Buy Price)
        let buyMatch = text.match(/buy\s*price\s*:\s*(\d+)/i) || text.match(/buy\s*:\s*(\d+)/i) || text.match(/cost\s*:\s*(\d+)/i) || text.match(/buy\s*price\s*(\d+)/i);
        let costPriceField = document.getElementById('formProductPrice');
        if (buyMatch) {
            costPriceField.value = buyMatch[1];
        } else {
            if (!costPriceField.value) {
                costPriceField.value = 0;
            }
        }

        // Shipping / Delivery
        let shippingMatch = text.match(/shipping\s*:\s*(\d+)/i) || text.match(/delivery\s*:\s*(\d+)/i);
        if (shippingMatch) {
            document.getElementById('formOtherCost').value = shippingMatch[1];
        }

        // Sell Price (Due or Total, preferring Due for dropship tracking)
        let dueMatch = text.match(/due\s*:\s*(\d+)/i) || text.match(/total\s*:\s*(\d+)/i);
        if (dueMatch) {
            document.getElementById('formProductSellPrice').value = dueMatch[1];
        }

        // 6. Parse Date & Time
        let dateMatch = text.match(/order date\s*:\s*([\d\-\s\:]+)/i);
        if (dateMatch) {
            let rawDate = dateMatch[1].trim();
            // Convert 'YYYY-MM-DD HH:MM:SS' to 'YYYY-MM-DDTHH:MM'
            if (rawDate.length >= 16) {
                let formattedDate = rawDate.substring(0, 10) + 'T' + rawDate.substring(11, 16);
                document.getElementById('formDateTime').value = formattedDate;
            }
        }

        // 7. Parse Status
        let statusMatch = text.match(/status\s*:\s*([a-zA-Z]+)/i);
        if (statusMatch) {
            let statusVal = statusMatch[1].trim();
            let select = document.getElementById('formStatus');
            for (let option of select.options) {
                if (option.value.toLowerCase() === statusVal.toLowerCase()) {
                    select.value = option.value;
                    break;
                }
            }
        }

        calculateProfit();
    }

    function calculateProfit() {
        const cost = parseFloat(document.getElementById('formProductPrice').value) || 0;
        const sell = parseFloat(document.getElementById('formProductSellPrice').value) || 0;
        const other = parseFloat(document.getElementById('formOtherCost').value) || 0;
        const profit = sell - cost - other;
        
        const preview = document.getElementById('profitPreview');
        preview.innerText = '৳' + profit.toFixed(2);
        
        if (profit >= 0) {
            preview.className = 'fw-bold font-mono text-lg text-success';
        } else {
            preview.className = 'fw-bold font-mono text-lg text-danger';
        }
    }

    function openAddModal() {
        mode = 'ADD';
        document.getElementById('modalTitle').innerText = 'Add New Sales Order';
        document.getElementById('orderForm').reset();
        document.getElementById('aiRawText').value = '';
        document.getElementById('orderId').value = '';
        document.getElementById('formDateTime').value = new Date().toISOString().slice(0, 16);
        document.getElementById('profitPreview').innerText = '৳0.00';
        $('#orderModal').modal('show');
    }

    function openEditModal(order) {
        mode = 'EDIT';
        document.getElementById('modalTitle').innerText = 'Modify Sales Order';
        document.getElementById('aiRawText').value = '';
        document.getElementById('orderId').value = order.id;
        document.getElementById('formDateTime').value = new Date(order.dateTime).toISOString().slice(0, 16);
        document.getElementById('formStatus').value = order.status;
        document.getElementById('formCustomerName').value = order.customerName;
        document.getElementById('formCustomerNumber').value = order.customerNumber;
        document.getElementById('formProductCode').value = order.productCode;
        document.getElementById('formProductName').value = order.productName;
        document.getElementById('formProductPrice').value = order.productPrice;
        document.getElementById('formProductSellPrice').value = order.productSellPrice;
        document.getElementById('formOtherCost').value = order.otherCost;
        document.getElementById('formAddress').value = order.address;
        calculateProfit();
        $('#orderModal').modal('show');
    }

    function handleFormSubmit(e) {
        e.preventDefault();
        
        const form = document.getElementById('orderForm');
        const formData = new FormData(form);
        const id = document.getElementById('orderId').value;
        
        let url = "{{ route('admin.sales_tracker.store') }}";
        if (mode === 'EDIT') {
            url = "{{ route('admin.sales_tracker.update', '') }}/" + id;
        }

        $.ajax({
            url: url,
            method: 'POST',
            data: $(form).serialize(),
            success: function(res) {
                if (res.success) {
                    notify('success', mode === 'ADD' ? 'Order created successfully' : 'Order updated successfully');
                    $('#orderModal').modal('hide');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    notify('error', res.error || 'Operation failed');
                }
            },
            error: function(xhr) {
                notify('error', 'Validation failed or connection lost');
            }
        });
    }

    function deleteOrder(id) {
        if (!confirm('Are you sure you want to delete this order?')) return;

        $.ajax({
            url: "{{ route('admin.sales_tracker.destroy', '') }}/" + id,
            method: 'POST',
            data: {
                _token: "{{ csrf_token() }}"
            },
            success: function(res) {
                if (res.success) {
                    notify('success', 'Order deleted successfully');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    notify('error', res.error || 'Failed to delete');
                }
            }
        });
    }
</script>
@endsection
