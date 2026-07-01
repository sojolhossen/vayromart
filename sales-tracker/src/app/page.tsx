"use client";

import React, { useState, useEffect } from "react";
import { 
  Plus, 
  Search, 
  Trash2, 
  Edit3, 
  Calendar, 
  X, 
  Filter, 
  RefreshCw, 
  ShoppingBag, 
  DollarSign, 
  TrendingUp, 
  MapPin,
  TrendingDown
} from "lucide-react";

interface Order {
  id: string;
  orderId: string;
  dateTime: string;
  status: string;
  customerName: string;
  customerNumber: string;
  productCode: string;
  productName: string;
  productPrice: number;
  productSellPrice: number;
  otherCost: number;
  profit: number;
  address: string;
}

export default function SalesDashboard() {
  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  // Filters
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("ALL");
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");

  // Modal control
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [modalMode, setModalMode] = useState<"ADD" | "EDIT">("ADD");
  const [editingId, setEditingId] = useState<string | null>(null);

  // Form Fields
  const [formDateTime, setFormDateTime] = useState("");
  const [formStatus, setFormStatus] = useState("Approved");
  const [formCustomerName, setFormCustomerName] = useState("");
  const [formCustomerNumber, setFormCustomerNumber] = useState("");
  const [formProductCode, setFormProductCode] = useState("");
  const [formProductName, setFormProductName] = useState("");
  const [formProductPrice, setFormProductPrice] = useState("");
  const [formProductSellPrice, setFormProductSellPrice] = useState("");
  const [formOtherCost, setFormOtherCost] = useState("");
  const [formAddress, setFormAddress] = useState("");
  const [formError, setFormError] = useState("");

  // Fetch orders
  const fetchOrders = async () => {
    try {
      setLoading(true);
      setError("");
      
      const queryParams = new URLSearchParams();
      if (search) queryParams.append("search", search);
      if (statusFilter && statusFilter !== "ALL") queryParams.append("status", statusFilter);
      if (startDate) queryParams.append("startDate", startDate);
      if (endDate) queryParams.append("endDate", endDate);

      const res = await fetch(`/api/orders?${queryParams.toString()}`);
      const result = await res.json();

      if (result.success) {
        setOrders(result.data);
      } else {
        setError(result.error || "Failed to load orders");
      }
    } catch (err: any) {
      setError("Failed to connect to API");
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    // Debounce search slightly
    const timer = setTimeout(() => {
      fetchOrders();
    }, 300);
    return () => clearTimeout(timer);
  }, [search, statusFilter, startDate, endDate]);

  // Handle Form Submit
  const handleFormSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setFormError("");

    // Form validations
    if (!formCustomerName.trim()) return setFormError("Customer Name is required");
    if (!formCustomerNumber.trim()) return setFormError("Customer Number is required");
    
    // Bangladesh/Generic phone validation
    const phoneRegex = /^[0-9+]{5,15}$/;
    if (!phoneRegex.test(formCustomerNumber.trim())) {
      return setFormError("Enter a valid Customer Number (5-15 digits)");
    }

    if (!formProductCode.trim()) return setFormError("Product Code is required");
    if (!formProductName.trim()) return setFormError("Product Name is required");
    
    const priceVal = parseFloat(formProductPrice);
    if (isNaN(priceVal) || priceVal < 0) return setFormError("Product Price must be a positive number");
    
    const sellVal = parseFloat(formProductSellPrice);
    if (isNaN(sellVal) || sellVal < 0) return setFormError("Sell Price must be a positive number");

    const otherVal = parseFloat(formOtherCost);
    if (isNaN(otherVal) || otherVal < 0) return setFormError("Other Cost must be a positive number");

    if (!formAddress.trim()) return setFormError("Delivery Address is required");

    const payload = {
      dateTime: formDateTime || undefined,
      status: formStatus,
      customerName: formCustomerName.trim(),
      customerNumber: formCustomerNumber.trim(),
      productCode: formProductCode.trim().toUpperCase(),
      productName: formProductName.trim(),
      productPrice: priceVal,
      productSellPrice: sellVal,
      otherCost: otherVal,
      address: formAddress.trim(),
    };

    try {
      let res;
      if (modalMode === "ADD") {
        res = await fetch("/api/orders", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });
      } else {
        res = await fetch(`/api/orders/${editingId}`, {
          method: "PUT",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload),
        });
      }

      const result = await res.json();
      if (result.success) {
        setIsModalOpen(false);
        fetchOrders();
        resetForm();
      } else {
        setFormError(result.error || "Operation failed");
      }
    } catch (err) {
      setFormError("Server error. Please try again.");
    }
  };

  const resetForm = () => {
    setFormDateTime(new Date().toISOString().slice(0, 16));
    setFormStatus("Approved");
    setFormCustomerName("");
    setFormCustomerNumber("");
    setFormProductCode("");
    setFormProductName("");
    setFormProductPrice("");
    setFormProductSellPrice("");
    setFormOtherCost("");
    setFormAddress("");
    setFormError("");
    setEditingId(null);
  };

  const openAddModal = () => {
    setModalMode("ADD");
    resetForm();
    setIsModalOpen(true);
  };

  const openEditModal = (order: Order) => {
    setModalMode("EDIT");
    setEditingId(order.id);
    setFormDateTime(new Date(order.dateTime).toISOString().slice(0, 16));
    setFormStatus(order.status);
    setFormCustomerName(order.customerName);
    setFormCustomerNumber(order.customerNumber);
    setFormProductCode(order.productCode);
    setFormProductName(order.productName);
    setFormProductPrice(order.productPrice.toString());
    setFormProductSellPrice(order.productSellPrice.toString());
    setFormOtherCost(order.otherCost.toString());
    setFormAddress(order.address);
    setIsModalOpen(true);
  };

  const handleDelete = async (id: string) => {
    if (!confirm("Are you sure you want to delete this order? This action is permanent.")) return;
    try {
      const res = await fetch(`/api/orders/${id}`, {
        method: "DELETE",
      });
      const result = await res.json();
      if (result.success) {
        fetchOrders();
      } else {
        alert(result.error || "Failed to delete order");
      }
    } catch (err) {
      alert("Server connection failed");
    }
  };

  const clearFilters = () => {
    setSearch("");
    setStatusFilter("ALL");
    setStartDate("");
    setEndDate("");
  };

  // Group orders by date for daily summaries
  const getGroupedOrdersWithSummaries = () => {
    const grouped: { [key: string]: Order[] } = {};
    
    // Sort orders by datetime desc (API already does this, but we reinforce it)
    const sorted = [...orders].sort((a, b) => new Date(b.dateTime).getTime() - new Date(a.dateTime).getTime());

    sorted.forEach(order => {
      const dateKey = new Date(order.dateTime).toLocaleDateString("en-US", {
        year: "numeric",
        month: "long",
        day: "numeric"
      });
      if (!grouped[dateKey]) {
        grouped[dateKey] = [];
      }
      grouped[dateKey].push(order);
    });

    return grouped;
  };

  // Calculate high-level stats for filtered list
  const getStats = () => {
    let totalRevenue = 0;
    let totalProfit = 0;
    let totalOrdersCount = orders.length;

    orders.forEach(o => {
      // Don't count revenue/profit for cancelled/returned in high-level summaries
      if (o.status !== "Cancelled" && o.status !== "Returned") {
        totalRevenue += o.productSellPrice;
      }
      totalProfit += o.profit;
    });

    const profitMargin = totalRevenue > 0 ? (totalProfit / totalRevenue) * 100 : 0;

    return {
      totalRevenue: totalRevenue.toFixed(2),
      totalProfit: totalProfit.toFixed(2),
      totalOrdersCount,
      profitMargin: profitMargin.toFixed(1)
    };
  };

  const stats = getStats();
  const groupedOrders = getGroupedOrdersWithSummaries();
  const dateKeys = Object.keys(groupedOrders);

  return (
    <div className="space-y-6">
      {/* Title section */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="font-display font-extrabold text-3xl tracking-tight bg-gradient-to-r from-orange-500 to-amber-500 bg-clip-text text-transparent">
            Sales Dashboard
          </h1>
          <p className="text-slate-500 dark:text-slate-400 text-sm mt-1">
            Real-time spreadsheet tracker for your e-commerce and dropshipping orders.
          </p>
        </div>
        <button
          onClick={openAddModal}
          className="flex items-center justify-center gap-2 bg-gradient-to-r from-orange-600 to-orange-700 hover:from-orange-700 hover:to-orange-800 text-white font-semibold px-5 py-3 rounded-xl shadow-lg shadow-orange-500/20 hover:shadow-orange-500/30 transition-all cursor-pointer transform hover:-translate-y-0.5 active:translate-y-0"
        >
          <Plus className="h-5 w-5" />
          Add New Order
        </button>
      </div>

      {/* KPI Stats Overview Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div className="glass-card p-5 flex items-center justify-between shadow-sm">
          <div className="space-y-1">
            <span className="text-slate-400 text-xs font-semibold uppercase tracking-wider">Total Orders</span>
            <h3 className="font-display font-bold text-2xl tracking-tight">{stats.totalOrdersCount}</h3>
          </div>
          <div className="bg-orange-500/10 p-3 rounded-xl text-[var(--primary)]">
            <ShoppingBag className="h-6 w-6" />
          </div>
        </div>

        <div className="glass-card p-5 flex items-center justify-between shadow-sm">
          <div className="space-y-1">
            <span className="text-slate-400 text-xs font-semibold uppercase tracking-wider">Net Revenue</span>
            <h3 className="font-display font-bold text-2xl tracking-tight">৳{stats.totalRevenue}</h3>
          </div>
          <div className="bg-emerald-500/10 p-3 rounded-xl text-emerald-500 font-bold flex items-center justify-center text-lg w-12 h-12">
            ৳
          </div>
        </div>

        <div className="glass-card p-5 flex items-center justify-between shadow-sm">
          <div className="space-y-1">
            <span className="text-slate-400 text-xs font-semibold uppercase tracking-wider">Net Profit</span>
            <h3 className={`font-display font-bold text-2xl tracking-tight ${Number(stats.totalProfit) >= 0 ? "text-emerald-500" : "text-red-500"}`}>
              ৳{stats.totalProfit}
            </h3>
          </div>
          <div className={`${Number(stats.totalProfit) >= 0 ? "bg-emerald-500/10 text-emerald-500" : "bg-red-500/10 text-red-500"} p-3 rounded-xl`}>
            {Number(stats.totalProfit) >= 0 ? <TrendingUp className="h-6 w-6" /> : <TrendingDown className="h-6 w-6" />}
          </div>
        </div>

        <div className="glass-card p-5 flex items-center justify-between shadow-sm">
          <div className="space-y-1">
            <span className="text-slate-400 text-xs font-semibold uppercase tracking-wider">Profit Margin</span>
            <h3 className="font-display font-bold text-2xl tracking-tight">{stats.profitMargin}%</h3>
          </div>
          <div className="bg-cyan-500/10 p-3 rounded-xl text-cyan-500">
            <TrendingUp className="h-6 w-6" />
          </div>
        </div>
      </div>

      {/* Filter Toolbar */}
      <div className="glass-card p-5 shadow-sm space-y-4">
        <div className="flex items-center gap-2 text-sm font-bold text-slate-600 dark:text-slate-300">
          <Filter className="h-4 w-4 text-[var(--primary)]" />
          <span>Filters & Controls</span>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
          {/* Search */}
          <div className="relative">
            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
            <input
              type="text"
              placeholder="Search ID, customer, product..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition-all"
            />
          </div>

          {/* Status */}
          <div>
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition-all"
            >
              <option value="ALL">All Statuses</option>
              <option value="Approved">Approved</option>
              <option value="Processing">Processing</option>
              <option value="Shipment">Shipment</option>
              <option value="Cancelled">Cancelled</option>
              <option value="Returned">Returned</option>
            </select>
          </div>

          {/* Start Date */}
          <div className="relative">
            <Calendar className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none" />
            <input
              type="date"
              value={startDate}
              onChange={(e) => setStartDate(e.target.value)}
              className="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition-all"
              title="Start Date"
            />
          </div>

          {/* End Date */}
          <div className="relative">
            <Calendar className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none" />
            <input
              type="date"
              value={endDate}
              onChange={(e) => setEndDate(e.target.value)}
              className="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition-all"
              title="End Date"
            />
          </div>

          {/* Utility Buttons */}
          <div className="flex gap-2">
            <button
              onClick={clearFilters}
              className="flex-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 font-semibold text-xs py-2.5 rounded-xl transition-all cursor-pointer text-center"
            >
              Clear Filters
            </button>
            <button
              onClick={fetchOrders}
              className="bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-[var(--primary)] p-2.5 rounded-xl transition-all cursor-pointer flex items-center justify-center"
              title="Refresh Data"
            >
              <RefreshCw className="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>

      {/* Main Order Sheets Grid */}
      <div className="glass-card shadow-sm overflow-hidden">
        {loading ? (
          <div className="p-12 text-center text-slate-400 space-y-4">
            <RefreshCw className="h-8 w-8 animate-spin mx-auto text-indigo-500" />
            <p className="text-sm font-medium">Fetching orders database...</p>
          </div>
        ) : error ? (
          <div className="p-12 text-center text-red-500">
            <p className="font-bold">Error loading database</p>
            <p className="text-xs mt-1">{error}</p>
          </div>
        ) : orders.length === 0 ? (
          <div className="p-12 text-center text-slate-400 space-y-2">
            <p className="font-bold text-lg">No orders found</p>
            <p className="text-sm">Try clearing filters or add a new record to start tracking.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse min-w-[1200px]">
              <thead>
                <tr className="bg-slate-50/75 dark:bg-slate-900/50 border-b border-slate-100 dark:border-slate-800 text-slate-400 font-bold text-xs uppercase tracking-wider">
                  <th className="py-4 px-5">Order ID</th>
                  <th className="py-4 px-4">Date & Time</th>
                  <th className="py-4 px-4">Status</th>
                  <th className="py-4 px-4">Customer</th>
                  <th className="py-4 px-4">Phone</th>
                  <th className="py-4 px-4">Product Code / Name</th>
                  <th className="py-4 px-4 text-right">Cost Price</th>
                  <th className="py-4 px-4 text-right">Sell Price</th>
                  <th className="py-4 px-4 text-right">Other Cost</th>
                  <th className="py-4 px-4 text-right">Profit/Loss</th>
                  <th className="py-4 px-4">Delivery Address</th>
                  <th className="py-4 px-5 text-center">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 text-sm">
                {dateKeys.map((dateKey) => {
                  const dayOrders = groupedOrders[dateKey];
                  
                  // Calculate daily summaries
                  let dailyTotalOrders = dayOrders.length;
                  let dailyRevenue = 0;
                  let dailyProfit = 0;
                  
                  dayOrders.forEach(o => {
                    if (o.status !== "Cancelled" && o.status !== "Returned") {
                      dailyRevenue += o.productSellPrice;
                    }
                    dailyProfit += o.profit;
                  });

                  return (
                    <React.Fragment key={dateKey}>
                      {/* Day Orders Rows */}
                      {dayOrders.map((order) => {
                        const statusClass = 
                          order.status === "Approved" ? "status-approved" :
                          order.status === "Processing" ? "status-processing" :
                          order.status === "Shipment" ? "status-shipment" :
                          order.status === "Cancelled" ? "status-cancelled" :
                          "status-returned";

                        return (
                          <tr key={order.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-900/20 transition-colors">
                            <td className="py-3.5 px-5 font-display font-semibold text-xs tracking-tight text-[var(--primary)]">
                              {order.orderId}
                            </td>
                            <td className="py-3.5 px-4 text-slate-500 dark:text-slate-400 text-xs">
                              {new Date(order.dateTime).toLocaleTimeString("en-US", {
                                hour: "2-digit",
                                minute: "2-digit"
                              })}
                            </td>
                            <td className="py-3.5 px-4">
                              <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider ${statusClass}`}>
                                {order.status}
                              </span>
                            </td>
                            <td className="py-3.5 px-4 font-semibold text-slate-700 dark:text-slate-200">
                              {order.customerName}
                            </td>
                            <td className="py-3.5 px-4 text-slate-500 dark:text-slate-400 text-xs font-mono">
                              {order.customerNumber}
                            </td>
                            <td className="py-3.5 px-4 max-w-[200px] truncate" title={`${order.productCode} - ${order.productName}`}>
                              <span className="font-mono text-xs font-bold text-slate-400 mr-1.5 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">
                                {order.productCode}
                              </span>
                              <span className="text-slate-600 dark:text-slate-300 font-medium">
                                {order.productName}
                              </span>
                            </td>
                            <td className="py-3.5 px-4 text-right text-slate-500 dark:text-slate-400 font-mono">
                              ৳{order.productPrice.toFixed(2)}
                            </td>
                            <td className="py-3.5 px-4 text-right font-semibold text-slate-700 dark:text-slate-200 font-mono">
                              ৳{order.productSellPrice.toFixed(2)}
                            </td>
                            <td className="py-3.5 px-4 text-right text-slate-500 dark:text-slate-400 font-mono">
                              ৳{order.otherCost.toFixed(2)}
                            </td>
                            <td className={`py-3.5 px-4 text-right font-bold font-mono ${order.profit >= 0 ? "text-emerald-500" : "text-red-500"}`}>
                              ৳{order.profit.toFixed(2)}
                            </td>
                            <td className="py-3.5 px-4">
                              {/* Ellipsis text with custom CSS tooltip */}
                              <div className="relative group max-w-[160px] truncate cursor-pointer flex items-center gap-1.5 text-slate-500 dark:text-slate-400 text-xs">
                                <MapPin className="h-3.5 w-3.5 shrink-0 text-slate-400" />
                                <span className="truncate">{order.address}</span>
                                
                                {/* Tooltip box */}
                                <div className="hidden group-hover:block absolute bottom-full left-0 mb-2 w-64 bg-slate-900 dark:bg-slate-950 text-white rounded-lg p-2.5 text-xs shadow-xl border border-slate-800 whitespace-normal leading-relaxed z-40 transition-opacity">
                                  <div className="font-bold text-[var(--primary)] mb-0.5">Shipping Address:</div>
                                  {order.address}
                                </div>
                              </div>
                            </td>
                            <td className="py-3.5 px-5">
                              <div className="flex items-center justify-center gap-2">
                                <button
                                  onClick={() => openEditModal(order)}
                                  className="p-1.5 text-slate-400 hover:text-[var(--primary)] hover:bg-orange-500/10 rounded-lg transition-colors cursor-pointer"
                                  title="Edit Order"
                                >
                                  <Edit3 className="h-4 w-4" />
                                </button>
                                <button
                                  onClick={() => handleDelete(order.id)}
                                  className="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-500/10 rounded-lg transition-colors cursor-pointer"
                                  title="Delete Order"
                                >
                                  <Trash2 className="h-4 w-4" />
                                </button>
                              </div>
                            </td>
                          </tr>
                        );
                      })}
                      
                      {/* Daily Summary Row */}
                      <tr className="daily-summary-row border-y border-slate-200 dark:border-slate-800 text-xs text-indigo-900 dark:text-indigo-200">
                        <td colSpan={2} className="py-3 px-5 font-bold uppercase tracking-wider text-[10px]">
                          Daily Summary
                        </td>
                        <td colSpan={3} className="py-3 px-4 font-semibold text-slate-500 dark:text-slate-400">
                          {dateKey}
                        </td>
                        <td className="py-3 px-4 font-bold">
                          Orders: {dailyTotalOrders}
                        </td>
                        <td colSpan={2} className="py-3 px-4 text-right font-bold font-mono">
                          Rev: ৳{dailyRevenue.toFixed(2)}
                        </td>
                        <td className="py-3 px-4 text-right text-[10px] text-slate-400">
                          Net Profit
                        </td>
                        <td className={`py-3 px-4 text-right font-black font-mono ${dailyProfit >= 0 ? "text-emerald-500" : "text-red-500"}`}>
                          ৳{dailyProfit.toFixed(2)}
                        </td>
                        <td colSpan={2} className="py-3 px-4"></td>
                      </tr>
                    </React.Fragment>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Add / Edit Popup Modal Form */}
      {isModalOpen && (
        <div className="fixed inset-0 flex items-center justify-center z-50 p-4">
          {/* Modal Backdrop */}
          <div onClick={() => setIsModalOpen(false)} className="absolute inset-0 bg-black/60 backdrop-blur-sm" />
          
          {/* Modal Content container */}
          <div className="glass-card w-full max-w-2xl shadow-2xl relative z-10 border border-slate-200 dark:border-slate-800 flex flex-col max-h-[90vh]">
            {/* Header */}
            <div className="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-800">
              <h2 className="font-display font-bold text-xl bg-gradient-to-r from-indigo-500 to-cyan-500 bg-clip-text text-transparent">
                {modalMode === "ADD" ? "Add New Sales Order" : "Modify Sales Order"}
              </h2>
              <button
                onClick={() => setIsModalOpen(false)}
                className="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 transition-colors cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Form */}
            <form onSubmit={handleFormSubmit} className="flex-1 overflow-y-auto p-6 space-y-4">
              {formError && (
                <div className="bg-red-500/10 border border-red-500/20 text-red-500 rounded-xl p-3.5 text-xs font-semibold">
                  {formError}
                </div>
              )}

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {/* Datepicker */}
                <div>
                  <label className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Date & Time</label>
                  <input
                    type="datetime-local"
                    value={formDateTime}
                    onChange={(e) => setFormDateTime(e.target.value)}
                    className="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>

                {/* Status */}
                <div>
                  <label className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Order Status</label>
                  <select
                    value={formStatus}
                    onChange={(e) => setFormStatus(e.target.value)}
                    className="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  >
                    <option value="Approved">Approved</option>
                    <option value="Processing">Processing</option>
                    <option value="Shipment">Shipment</option>
                    <option value="Cancelled">Cancelled</option>
                    <option value="Returned">Returned</option>
                  </select>
                </div>

                {/* Customer Name */}
                <div>
                  <label className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Customer Name</label>
                  <input
                    type="text"
                    value={formCustomerName}
                    onChange={(e) => setFormCustomerName(e.target.value)}
                    placeholder="e.g. John Doe"
                    className="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>

                {/* Customer Phone */}
                <div>
                  <label className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Customer Phone (Number)</label>
                  <input
                    type="tel"
                    value={formCustomerNumber}
                    onChange={(e) => setFormCustomerNumber(e.target.value)}
                    placeholder="e.g. 01711223344"
                    className="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>

                {/* Product Code */}
                <div>
                  <label className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Product Code</label>
                  <input
                    type="text"
                    value={formProductCode}
                    onChange={(e) => setFormProductCode(e.target.value)}
                    placeholder="e.g. PRD-A101"
                    className="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>

                {/* Product Name */}
                <div>
                  <label className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Product Name</label>
                  <input
                    type="text"
                    value={formProductName}
                    onChange={(e) => setFormProductName(e.target.value)}
                    placeholder="e.g. Memory Foam Pillow"
                    className="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>

                {/* Product Price Cost */}
                <div>
                  <label className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Product Price (Cost Price ৳)</label>
                  <input
                    type="number"
                    step="0.01"
                    value={formProductPrice}
                    onChange={(e) => setFormProductPrice(e.target.value)}
                    placeholder="0.00"
                    className="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--primary)] font-mono"
                  />
                </div>

                {/* Product Sell Price */}
                <div>
                  <label className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Product Sell Price (৳)</label>
                  <input
                    type="number"
                    step="0.01"
                    value={formProductSellPrice}
                    onChange={(e) => setFormProductSellPrice(e.target.value)}
                    placeholder="0.00"
                    className="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--primary)] font-mono"
                  />
                </div>

                {/* Other Cost */}
                <div className="md:col-span-2">
                  <label className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Other Cost (Delivery/Packaging ৳)</label>
                  <input
                    type="number"
                    step="0.01"
                    value={formOtherCost}
                    onChange={(e) => setFormOtherCost(e.target.value)}
                    placeholder="0.00"
                    className="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--primary)] font-mono"
                  />
                </div>

                {/* Delivery Address */}
                <div className="md:col-span-2">
                  <label className="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Delivery Address</label>
                  <textarea
                    value={formAddress}
                    onChange={(e) => setFormAddress(e.target.value)}
                    placeholder="Enter full shipping/delivery address..."
                    rows={3}
                    className="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
              </div>

              {/* Dynamic Profit preview */}
              <div className="bg-orange-500/5 border border-orange-500/10 rounded-xl p-4 flex items-center justify-between text-sm">
                <span className="font-semibold text-slate-600 dark:text-slate-300">Estimated Profit Preview:</span>
                <span className={`font-mono font-bold text-lg ${
                  (parseFloat(formProductSellPrice) || 0) - (parseFloat(formProductPrice) || 0) - (parseFloat(formOtherCost) || 0) >= 0 
                  ? "text-emerald-500" 
                  : "text-red-500"
                }`}>
                  ${((parseFloat(formProductSellPrice) || 0) - (parseFloat(formProductPrice) || 0) - (parseFloat(formOtherCost) || 0)).toFixed(2)}
                </span>
              </div>

              {/* Footer */}
              <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold px-5 py-2.5 rounded-xl text-sm transition-all cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="bg-gradient-to-r from-orange-600 to-orange-700 hover:from-orange-700 hover:to-orange-800 text-white font-semibold px-6 py-2.5 rounded-xl text-sm shadow-md transition-all cursor-pointer"
                >
                  {modalMode === "ADD" ? "Create Order" : "Save Changes"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
