"use client";

import React, { useState } from "react";
import { 
  Search, 
  User, 
  MapPin, 
  Phone, 
  ShoppingBag, 
  DollarSign, 
  TrendingUp, 
  Percent, 
  ArrowRight, 
  RefreshCw,
  Clock,
  CheckCircle,
  Truck,
  XCircle,
  AlertTriangle
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

interface CustomerData {
  exists: boolean;
  customerName?: string;
  customerNumber?: string;
  latestAddress?: string;
  orders: Order[];
  stats: {
    totalOrders: number;
    totalRevenue: number;
    totalProfit: number;
    successRate: number;
    statusCounts: Record<string, number>;
  };
}

export default function CustomerLookup() {
  const [phoneNumber, setPhoneNumber] = useState("");
  const [loading, setLoading] = useState(false);
  const [searched, setSearched] = useState(false);
  const [customerData, setCustomerData] = useState<CustomerData | null>(null);
  const [error, setError] = useState("");

  const handleSearch = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!phoneNumber.trim()) return;

    try {
      setLoading(true);
      setError("");
      setCustomerData(null);

      const res = await fetch(`/api/customers/${phoneNumber.trim()}`);
      const result = await res.json();

      if (result.success) {
        setCustomerData(result.data);
        setSearched(true);
      } else {
        setError(result.error || "Failed to look up customer");
      }
    } catch (err) {
      setError("Server connection failed. Please try again.");
    } finally {
      setLoading(false);
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case "Approved":
        return <CheckCircle className="h-4 w-4 text-emerald-500" />;
      case "Processing":
        return <Clock className="h-4 w-4 text-blue-500" />;
      case "Shipment":
        return <Truck className="h-4 w-4 text-purple-500" />;
      case "Cancelled":
        return <XCircle className="h-4 w-4 text-red-500" />;
      case "Returned":
        return <AlertTriangle className="h-4 w-4 text-amber-500" />;
      default:
        return <Clock className="h-4 w-4 text-slate-500" />;
    }
  };

  const getStatusClass = (status: string) => {
    switch (status) {
      case "Approved": return "status-approved";
      case "Processing": return "status-processing";
      case "Shipment": return "status-shipment";
      case "Cancelled": return "status-cancelled";
      case "Returned": return "status-returned";
      default: return "";
    }
  };

  return (
    <div className="space-y-6">
      {/* Title */}
      <div>
        <h1 className="font-display font-extrabold text-3xl tracking-tight bg-gradient-to-r from-indigo-500 to-cyan-500 bg-clip-text text-transparent">
          Customer Summary Lookup
        </h1>
        <p className="text-slate-500 dark:text-slate-400 text-sm mt-1">
          Instantly look up order histories, success ratios, and lifetime values by customer phone number.
        </p>
      </div>

      {/* Search Bar Panel */}
      <div className="glass-card p-6 shadow-sm">
        <form onSubmit={handleSearch} className="flex flex-col sm:flex-row gap-4 items-end">
          <div className="flex-1 space-y-1.5">
            <label className="block text-xs font-bold text-slate-400 uppercase tracking-wider">
              Customer Phone Number
            </label>
            <div className="relative">
              <Phone className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
              <input
                type="text"
                placeholder="Enter customer number (e.g. 01711223344)"
                value={phoneNumber}
                onChange={(e) => setPhoneNumber(e.target.value)}
                className="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl pl-10 pr-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </div>
          <button
            type="submit"
            disabled={loading}
            className="w-full sm:w-auto flex items-center justify-center gap-2 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white font-semibold px-6 py-3 rounded-xl shadow-md transition-all cursor-pointer disabled:opacity-50"
          >
            {loading ? <RefreshCw className="h-5 w-5 animate-spin" /> : <Search className="h-5 w-5" />}
            Lookup Customer
          </button>
        </form>
      </div>

      {/* Error State */}
      {error && (
        <div className="bg-red-500/10 border border-red-500/20 text-red-500 rounded-xl p-4 text-sm font-semibold">
          {error}
        </div>
      )}

      {/* Results Section */}
      {searched && customerData && (
        <>
          {!customerData.exists ? (
            <div className="glass-card p-12 text-center text-slate-400 space-y-2">
              <p className="font-bold text-lg">No records found</p>
              <p className="text-sm">There are no sales entries associated with the phone number "{phoneNumber}".</p>
            </div>
          ) : (
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              {/* Left Column: Customer Profile Card */}
              <div className="lg:col-span-1 space-y-6">
                <div className="glass-card p-6 shadow-sm space-y-5">
                  <div className="flex items-center gap-4">
                    <div className="h-14 w-14 rounded-2xl bg-gradient-to-tr from-indigo-500 to-cyan-500 flex items-center justify-center text-white font-bold text-lg shadow-md shadow-indigo-500/10">
                      <User className="h-6 w-6" />
                    </div>
                    <div>
                      <h3 className="font-display font-bold text-lg leading-tight">{customerData.customerName}</h3>
                      <p className="text-slate-400 text-xs mt-0.5 flex items-center gap-1">
                        <Phone className="h-3 w-3" /> {customerData.customerNumber}
                      </p>
                    </div>
                  </div>

                  <div className="border-t border-slate-100 dark:border-slate-800/80 pt-4 space-y-3.5">
                    <div className="space-y-1">
                      <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Latest Shipping Address</span>
                      <div className="text-xs text-slate-600 dark:text-slate-300 flex items-start gap-1.5 leading-relaxed">
                        <MapPin className="h-4 w-4 shrink-0 text-indigo-500 mt-0.5" />
                        <span>{customerData.latestAddress}</span>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Status distribution breakups */}
                <div className="glass-card p-6 shadow-sm space-y-4">
                  <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider">Order Status Distribution</h4>
                  <div className="space-y-2.5">
                    {Object.entries(customerData.stats.statusCounts).map(([status, count]) => (
                      <div key={status} className="flex items-center justify-between text-xs">
                        <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full font-semibold uppercase tracking-wider text-[10px] ${getStatusClass(status)}`}>
                          {status}
                        </span>
                        <span className="font-semibold">{count} order{count !== 1 ? "s" : ""}</span>
                      </div>
                    ))}
                  </div>
                </div>
              </div>

              {/* Right Column: Customer metrics & timeline */}
              <div className="lg:col-span-2 space-y-6">
                {/* Micro metrics grid */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                  <div className="glass-card p-4 shadow-sm space-y-1">
                    <span className="text-slate-400 text-[10px] font-bold uppercase tracking-wider">Total Orders</span>
                    <div className="font-display font-bold text-xl">{customerData.stats.totalOrders}</div>
                  </div>
                  
                  <div className="glass-card p-4 shadow-sm space-y-1">
                    <span className="text-slate-400 text-[10px] font-bold uppercase tracking-wider">LTV Revenue</span>
                    <div className="font-display font-bold text-xl text-emerald-500">${customerData.stats.totalRevenue}</div>
                  </div>

                  <div className="glass-card p-4 shadow-sm space-y-1">
                    <span className="text-slate-400 text-[10px] font-bold uppercase tracking-wider">Total Profit</span>
                    <div className={`font-display font-bold text-xl ${customerData.stats.totalProfit >= 0 ? "text-emerald-500" : "text-red-500"}`}>
                      ${customerData.stats.totalProfit}
                    </div>
                  </div>

                  <div className="glass-card p-4 shadow-sm space-y-1">
                    <span className="text-slate-400 text-[10px] font-bold uppercase tracking-wider">Success Rate</span>
                    <div className="font-display font-bold text-xl text-indigo-500">{customerData.stats.successRate}%</div>
                  </div>
                </div>

                {/* Timeline order history */}
                <div className="glass-card p-6 shadow-sm space-y-5">
                  <h3 className="font-display font-bold text-base border-b border-slate-100 dark:border-slate-800/80 pb-3">
                    Order History Timeline
                  </h3>

                  {/* Vertical Timeline container */}
                  <div className="relative border-l border-slate-200 dark:border-slate-800 pl-6 ml-4 space-y-8 py-2">
                    {customerData.orders.map((order, idx) => (
                      <div key={order.id} className="relative">
                        {/* Timeline dot */}
                        <div className="absolute -left-[34px] top-0 bg-[var(--background)] p-1 rounded-full border border-slate-200 dark:border-slate-800 flex items-center justify-center">
                          <div className="bg-slate-100 dark:bg-slate-900 p-1 rounded-full">
                            {getStatusIcon(order.status)}
                          </div>
                        </div>

                        {/* Order timeline details card */}
                        <div className="space-y-1.5">
                          <div className="flex flex-wrap items-center gap-2">
                            <span className="font-mono text-xs font-semibold text-indigo-500 bg-indigo-500/5 px-2 py-0.5 rounded">
                              {order.orderId}
                            </span>
                            <span className="text-slate-400 text-[10px] font-medium">
                              {new Date(order.dateTime).toLocaleString("en-US", {
                                year: "numeric",
                                month: "short",
                                day: "numeric",
                                hour: "2-digit",
                                minute: "2-digit"
                              })}
                            </span>
                            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${getStatusClass(order.status)}`}>
                              {order.status}
                            </span>
                          </div>

                          <h4 className="font-semibold text-slate-800 dark:text-slate-100">
                            {order.productName}
                          </h4>

                          <div className="grid grid-cols-3 gap-2 max-w-sm text-xs font-mono text-slate-500 pt-0.5">
                            <div>Cost: <span className="font-semibold text-slate-700 dark:text-slate-300">${order.productPrice.toFixed(2)}</span></div>
                            <div>Sell: <span className="font-semibold text-slate-700 dark:text-slate-300">${order.productSellPrice.toFixed(2)}</span></div>
                            <div>Profit: <span className={`font-semibold ${order.profit >= 0 ? "text-emerald-500" : "text-red-500"}`}>${order.profit.toFixed(2)}</span></div>
                          </div>
                          
                          <div className="text-[11px] text-slate-400 flex items-center gap-1.5 leading-relaxed pt-1.5">
                            <MapPin className="h-3 w-3 shrink-0" />
                            <span>Shipped to: {order.address}</span>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}
