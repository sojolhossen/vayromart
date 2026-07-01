<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SalesTrackerController extends Controller
{
    private $jsonPath = 'sales_orders.json';

    private function getOrders()
    {
        if (!Storage::disk('local')->exists($this->jsonPath)) {
            // Seed default orders if not exists
            $defaultOrders = $this->getSeedData();
            Storage::disk('local')->put($this->jsonPath, json_encode($defaultOrders, JSON_PRETTY_PRINT));
            return $defaultOrders;
        }
        $data = Storage::disk('local')->get($this->jsonPath);
        return json_decode($data, true) ?: [];
    }

    private function saveOrders($orders)
    {
        Storage::disk('local')->put($this->jsonPath, json_encode($orders, JSON_PRETTY_PRINT));
    }

    public function index(Request $request)
    {
        $orders = $this->getOrders();

        // Search Filter
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $orders = array_filter($orders, function ($o) use ($search) {
                return strpos(strtolower($o['orderId']), $search) !== false ||
                       strpos(strtolower($o['customerName']), $search) !== false ||
                       strpos(strtolower($o['customerNumber']), $search) !== false ||
                       strpos(strtolower($o['productName']), $search) !== false ||
                       strpos(strtolower($o['productCode']), $search) !== false;
            });
        }

        // Status Filter
        if ($request->filled('status') && $request->status !== 'ALL') {
            $status = $request->status;
            $orders = array_filter($orders, function ($o) use ($status) {
                return $o['status'] === $status;
            });
        }

        // Start Date Filter
        if ($request->filled('startDate')) {
            $startDate = $request->startDate;
            $orders = array_filter($orders, function ($o) use ($startDate) {
                return date('Y-m-d', strtotime($o['dateTime'])) >= $startDate;
            });
        }

        // End Date Filter
        if ($request->filled('endDate')) {
            $endDate = $request->endDate;
            $orders = array_filter($orders, function ($o) use ($endDate) {
                return date('Y-m-d', strtotime($o['dateTime'])) <= $endDate;
            });
        }

        // Sort orders desc
        usort($orders, function ($a, $b) {
            return strtotime($b['dateTime']) - strtotime($a['dateTime']);
        });

        // Calculate Stats
        $totalOrdersCount = count($orders);
        $totalRevenue = 0;
        $totalProfit = 0;

        foreach ($orders as $o) {
            if ($o['status'] !== 'Cancelled' && $o['status'] !== 'Returned') {
                $totalRevenue += $o['productSellPrice'];
            }
            $totalProfit += $o['profit'];
        }
        $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

        $stats = [
            'totalOrdersCount' => $totalOrdersCount,
            'totalRevenue' => number_format($totalRevenue, 2, '.', ''),
            'totalProfit' => number_format($totalProfit, 2, '.', ''),
            'profitMargin' => number_format($profitMargin, 1, '.', '')
        ];

        // Group by Date
        $grouped = [];
        foreach ($orders as $o) {
            $dateKey = date('F d, Y', strtotime($o['dateTime']));
            $grouped[$dateKey][] = $o;
        }

        $pageTitle = "Sales Tracker Dashboard";
        return view('admin.sales_tracker.index', compact('grouped', 'stats', 'pageTitle'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customerName' => 'required',
            'customerNumber' => 'required',
            'productCode' => 'required',
            'productName' => 'required',
            'productPrice' => 'required|numeric|min:0',
            'productSellPrice' => 'required|numeric|min:0',
            'otherCost' => 'required|numeric|min:0',
            'address' => 'required'
        ]);

        $orders = $this->getOrders();

        $profit = $request->productSellPrice - $request->productPrice - $request->otherCost;
        $orderId = 'ORD-' . (1000 + count($orders) + 1);

        $newOrder = [
            'id' => uniqid(),
            'orderId' => $orderId,
            'dateTime' => $request->dateTime ?: date('Y-m-d\TH:i'),
            'status' => $request->status ?: 'Approved',
            'customerName' => $request->customerName,
            'customerNumber' => $request->customerNumber,
            'productCode' => strtoupper($request->productCode),
            'productName' => $request->productName,
            'productPrice' => (float)$request->productPrice,
            'productSellPrice' => (float)$request->productSellPrice,
            'otherCost' => (float)$request->otherCost,
            'profit' => (float)$profit,
            'address' => $request->address
        ];

        $orders[] = $newOrder;
        $this->saveOrders($orders);

        return response()->json(['success' => true]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'customerName' => 'required',
            'customerNumber' => 'required',
            'productCode' => 'required',
            'productName' => 'required',
            'productPrice' => 'required|numeric|min:0',
            'productSellPrice' => 'required|numeric|min:0',
            'otherCost' => 'required|numeric|min:0',
            'address' => 'required'
        ]);

        $orders = $this->getOrders();
        $updated = false;

        foreach ($orders as &$o) {
            if ($o['id'] === $id) {
                $profit = $request->productSellPrice - $request->productPrice - $request->otherCost;
                
                $o['dateTime'] = $request->dateTime ?: $o['dateTime'];
                $o['status'] = $request->status ?: $o['status'];
                $o['customerName'] = $request->customerName;
                $o['customerNumber'] = $request->customerNumber;
                $o['productCode'] = strtoupper($request->productCode);
                $o['productName'] = $request->productName;
                $o['productPrice'] = (float)$request->productPrice;
                $o['productSellPrice'] = (float)$request->productSellPrice;
                $o['otherCost'] = (float)$request->otherCost;
                $o['profit'] = (float)$profit;
                $o['address'] = $request->address;
                
                $updated = true;
                break;
            }
        }

        if ($updated) {
            $this->saveOrders($orders);
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'error' => 'Order not found'], 404);
    }

    public function destroy($id)
    {
        $orders = $this->getOrders();
        $filtered = array_filter($orders, function ($o) use ($id) {
            return $o['id'] !== $id;
        });

        if (count($filtered) < count($orders)) {
            $this->saveOrders(array_values($filtered));
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'error' => 'Order not found'], 404);
    }

    private function getSeedData()
    {
        return [
            [
                'id' => '1',
                'orderId' => 'ORD-1001',
                'dateTime' => date('Y-m-d\T10:30', strtotime('-1 day')),
                'status' => 'Approved',
                'customerName' => 'Sojol Hossen',
                'customerNumber' => '01711223344',
                'productCode' => 'COLMI-P73',
                'productName' => 'Colmi P73 Smartwatch Waterproof',
                'productPrice' => 1200.00,
                'productSellPrice' => 1850.00,
                'otherCost' => 120.00,
                'profit' => 530.00,
                'address' => 'Mirpur-10, Dhaka, Bangladesh'
            ],
            [
                'id' => '2',
                'orderId' => 'ORD-1002',
                'dateTime' => date('Y-m-d\T14:15', strtotime('-1 day')),
                'status' => 'Processing',
                'customerName' => 'Tanvir Rahman',
                'customerNumber' => '01899887766',
                'productCode' => 'M10-EARBUD',
                'productName' => 'M10 TWS Earbuds Premium Sound',
                'productPrice' => 350.00,
                'productSellPrice' => 650.00,
                'otherCost' => 80.00,
                'profit' => 220.00,
                'address' => 'Chittagong Sadar, Chittagong'
            ],
            [
                'id' => '3',
                'orderId' => 'ORD-1003',
                'dateTime' => date('Y-m-d\T09:00'),
                'status' => 'Shipment',
                'customerName' => 'Sabbir Ahmed',
                'customerNumber' => '01511445566',
                'productCode' => 'POWERBANK-20K',
                'productName' => 'Romoss 20000mAh Power Bank Fast Charge',
                'productPrice' => 1400.00,
                'productSellPrice' => 2100.00,
                'otherCost' => 150.00,
                'profit' => 550.00,
                'address' => 'Zindabazar, Sylhet'
            ]
        ];
    }
}
