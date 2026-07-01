"use client";

import React, { useState, useEffect } from "react";
import { 
  BarChart3, 
  TrendingUp, 
  Calendar, 
  ShoppingBag, 
  Filter, 
  RefreshCw, 
  DollarSign, 
  Briefcase,
  PieChart,
  ArrowRight
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

interface ProductSummary {
  productCode: string;
  productName: string;
  unitsSold: number;
  revenue: number;
  cost: number;
  otherCost: number;
  profit: number;
  margin: number;
}

export default function AnalyticsDashboard() {
  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  // Filters
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");
  const [productFilter, setProductFilter] = useState("ALL");

  const fetchOrders = async () => {
    try {
      setLoading(true);
      setError("");

      const queryParams = new URLSearchParams();
      if (startDate) queryParams.append("startDate", startDate);
      if (endDate) queryParams.append("endDate", endDate);

      const res = await fetch(`/api/orders?${queryParams.toString()}`);
      const result = await res.json();

      if (result.success) {
        setOrders(result.data);
      } else {
        setError(result.error || "Failed to load analytics data");
      }
    } catch (err) {
      setError("Failed to fetch analytics");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchOrders();
  }, [startDate, endDate]);

  const clearFilters = () => {
    setStartDate("");
    setEndDate("");
    setProductFilter("ALL");
  };

  // Get distinct products for filters
  const getProductOptions = () => {
    const productsMap = new Map<string, string>();
    orders.forEach(o => {
      productsMap.set(o.productCode, o.productName);
    });
    return Array.from(productsMap.entries()).map(([code, name]) => ({ code, name }));
  };

  // Apply frontend filters
  const getFilteredOrders = () => {
    return orders.filter(o => {
      if (productFilter !== "ALL" && o.productCode !== productFilter) return false;
      return true;
    });
  };

  const filteredOrders = getFilteredOrders();

  // 1. Pivot Table Data (Grouped by Product)
  const getProductPivotData = (): ProductSummary[] => {
    const pivot: Record<string, ProductSummary> = {};

    filteredOrders.forEach(o => {
      const code = o.productCode;
      
      if (!pivot[code]) {
        pivot[code] = {
          productCode: code,
          productName: o.productName,
          unitsSold: 0,
          revenue: 0,
          cost: 0,
          otherCost: 0,
          profit: 0,
          margin: 0
        };
      }

      pivot[code].unitsSold += 1;
      
      // Calculate revenue (only non-cancelled, non-returned)
      if (o.status !== "Cancelled" && o.status !== "Returned") {
        pivot[code].revenue += o.productSellPrice;
      }
      
      pivot[code].cost += o.productPrice;
      pivot[code].otherCost += o.otherCost;
      pivot[code].profit += o.profit;
    });

    // Calculate margins
    return Object.values(pivot).map(p => {
      p.margin = p.revenue > 0 ? (p.profit / p.revenue) * 100 : 0;
      
      // Fixed decimal spaces
      p.revenue = Number(p.revenue.toFixed(2));
      p.cost = Number(p.cost.toFixed(2));
      p.otherCost = Number(p.otherCost.toFixed(2));
      p.profit = Number(p.profit.toFixed(2));
      p.margin = Number(p.margin.toFixed(1));
      
      return p;
    }).sort((a, b) => b.unitsSold - a.unitsSold); // Sort by popularity
  };

  // 2. Trend Data Over Time (Grouped by Date)
  const getTrendData = () => {
    const dailyMap: Record<string, { date: string, rawDate: Date, revenue: number, profit: number, count: number }> = {};

    filteredOrders.forEach(o => {
      const dateKey = new Date(o.dateTime).toISOString().split("T")[0]; // YYYY-MM-DD
      
      if (!dailyMap[dateKey]) {
        dailyMap[dateKey] = {
          date: new Date(o.dateTime).toLocaleDateString("en-US", { month: "short", day: "numeric" }),
          rawDate: new Date(dateKey),
          revenue: 0,
          profit: 0,
          count: 0
        };
      }

      dailyMap[dateKey].count++;
      if (o.status !== "Cancelled" && o.status !== "Returned") {
        dailyMap[dateKey].revenue += o.productSellPrice;
      }
      dailyMap[dateKey].profit += o.profit;
    });

    // Sort chronologically
    return Object.values(dailyMap).sort((a, b) => a.rawDate.getTime() - b.rawDate.getTime());
  };

  // 3. Status Breakdown counts
  const getStatusCounts = () => {
    const counts: Record<string, number> = {
      Approved: 0,
      Processing: 0,
      Shipment: 0,
      Cancelled: 0,
      Returned: 0
    };

    filteredOrders.forEach(o => {
      if (counts[o.status] !== undefined) {
        counts[o.status]++;
      }
    });

    const total = filteredOrders.length || 1;
    return Object.entries(counts).map(([name, val]) => ({
      name,
      count: val,
      percentage: Number(((val / total) * 100).toFixed(1))
    }));
  };

  // High level cards values
  const getCardStats = () => {
    let rev = 0;
    let prof = 0;
    let cost = 0;

    filteredOrders.forEach(o => {
      if (o.status !== "Cancelled" && o.status !== "Returned") {
        rev += o.productSellPrice;
      }
      prof += o.profit;
      cost += o.productPrice + o.otherCost;
    });

    const margin = rev > 0 ? (prof / rev) * 100 : 0;

    return {
      revenue: rev.toFixed(2),
      profit: prof.toFixed(2),
      cost: cost.toFixed(2),
      margin: margin.toFixed(1)
    };
  };

  const productOptions = getProductOptions();
  const cardStats = getCardStats();
  const pivotData = getProductPivotData();
  const trendData = getTrendData();
  const statusData = getStatusCounts();

  // SVG Chart Dimensions & Computations
  const renderTrendChart = () => {
    if (trendData.length === 0) {
      return (
        <div className="h-64 flex items-center justify-center text-slate-400 text-sm">
          No trend data available for selected filter.
        </div>
      );
    }

    const width = 600;
    const height = 240;
    const paddingLeft = 50;
    const paddingRight = 20;
    const paddingTop = 20;
    const paddingBottom = 40;

    // Find min/max for scale
    const revenues = trendData.map(d => d.revenue);
    const profits = trendData.map(d => d.profit);
    const allVals = [...revenues, ...profits];
    
    const maxVal = Math.max(...allVals, 50); // Fallback limit min
    const minVal = Math.min(...allVals, 0);

    const valRange = maxVal - minVal;
    
    // Mapping coords functions
    const getX = (index: number) => {
      if (trendData.length <= 1) return paddingLeft + (width - paddingLeft - paddingRight) / 2;
      return paddingLeft + (index / (trendData.length - 1)) * (width - paddingLeft - paddingRight);
    };

    const getY = (val: number) => {
      const scale = (val - minVal) / valRange;
      return height - paddingBottom - scale * (height - paddingTop - paddingBottom);
    };

    // Build SVG paths
    let revPath = "";
    let profPath = "";
    let revArea = "";
    
    trendData.forEach((d, idx) => {
      const x = getX(idx);
      const yRev = getY(d.revenue);
      const yProf = getY(d.profit);

      if (idx === 0) {
        revPath = `M ${x} ${yRev}`;
        profPath = `M ${x} ${yProf}`;
        revArea = `M ${x} ${height - paddingBottom} L ${x} ${yRev}`;
      } else {
        revPath += ` L ${x} ${yRev}`;
        profPath += ` L ${x} ${yProf}`;
        revArea += ` L ${x} ${yRev}`;
      }

      if (idx === trendData.length - 1) {
        revArea += ` L ${x} ${height - paddingBottom} Z`;
      }
    });

    // Grid lines count
    const gridCount = 4;
    const yGridVals = Array.from({ length: gridCount + 1 }).map((_, i) => minVal + (valRange / gridCount) * i);

    return (
      <div className="relative w-full overflow-hidden">
        <svg viewBox={`0 0 ${width} ${height}`} className="w-full h-auto">
          {/* Gradients */}
          <defs>
            <linearGradient id="revGrad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="#4f46e5" stopOpacity="0.2"/>
              <stop offset="100%" stopColor="#4f46e5" stopOpacity="0.0"/>
            </linearGradient>
          </defs>

          {/* Grid lines */}
          {yGridVals.map((val, idx) => {
            const y = getY(val);
            return (
              <g key={idx}>
                <line 
                  x1={paddingLeft} 
                  y1={y} 
                  x2={width - paddingRight} 
                  y2={y} 
                  stroke="rgba(100, 116, 139, 0.1)" 
                  strokeDasharray="4 4" 
                />
                <text 
                  x={paddingLeft - 10} 
                  y={y + 4} 
                  textAnchor="end" 
                  className="fill-slate-400 text-[10px] font-mono"
                >
                  ${Math.round(val)}
                </text>
              </g>
            );
          })}

          {/* Revenue Area (Gradient fill) */}
          {trendData.length > 1 && (
            <path d={revArea} fill="url(#revGrad)" />
          )}

          {/* Revenue Line */}
          <path 
            d={revPath} 
            fill="none" 
            stroke="#4f46e5" 
            strokeWidth="2.5" 
            strokeLinecap="round"
            strokeLinejoin="round"
          />

          {/* Profit Line */}
          <path 
            d={profPath} 
            fill="none" 
            stroke="#10b981" 
            strokeWidth="2.5" 
            strokeLinecap="round"
            strokeLinejoin="round"
          />

          {/* Data Points */}
          {trendData.map((d, idx) => {
            const x = getX(idx);
            const yRev = getY(d.revenue);
            const yProf = getY(d.profit);
            
            return (
              <g key={idx} className="group">
                <circle cx={x} cy={yRev} r="4" className="fill-indigo-600 stroke-[var(--background)] stroke-2 cursor-pointer hover:r-5 transition-all" />
                <circle cx={x} cy={yProf} r="4" className="fill-emerald-500 stroke-[var(--background)] stroke-2 cursor-pointer hover:r-5 transition-all" />
              </g>
            );
          })}

          {/* X Axis Labels */}
          {trendData.map((d, idx) => {
            // Show every label if small dataset, or skip to fit
            const skipStep = Math.max(1, Math.ceil(trendData.length / 8));
            if (idx % skipStep !== 0 && idx !== trendData.length - 1) return null;
            
            const x = getX(idx);
            return (
              <text 
                key={idx} 
                x={x} 
                y={height - 15} 
                textAnchor="middle" 
                className="fill-slate-400 text-[10px] font-semibold"
              >
                {d.date}
              </text>
            );
          })}
        </svg>
      </div>
    );
  };

  return (
    <div className="space-y-6">
      {/* Title */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="font-display font-extrabold text-3xl tracking-tight bg-gradient-to-r from-indigo-500 to-cyan-500 bg-clip-text text-transparent">
            Analytics & Pivot Summary
          </h1>
          <p className="text-slate-500 dark:text-slate-400 text-sm mt-1">
            Pivot analyses of products and visual charting of revenue/profit curves.
          </p>
        </div>
        <button
          onClick={fetchOrders}
          className="flex items-center justify-center gap-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 transition-all cursor-pointer"
        >
          <RefreshCw className="h-4 w-4" />
          Refresh Stats
        </button>
      </div>

      {/* Filter toolbar */}
      <div className="glass-card p-5 shadow-sm space-y-4">
        <div className="flex items-center gap-2 text-sm font-bold text-slate-600 dark:text-slate-300">
          <Filter className="h-4 w-4 text-indigo-500" />
          <span>Scope Analysis</span>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {/* Start Date */}
          <div className="relative">
            <Calendar className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 pointer-events-none" />
            <input
              type="date"
              value={startDate}
              onChange={(e) => setStartDate(e.target.value)}
              className="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
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
              className="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              title="End Date"
            />
          </div>

          {/* Product Filter */}
          <div>
            <select
              value={productFilter}
              onChange={(e) => setProductFilter(e.target.value)}
              className="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
              <option value="ALL">All Products</option>
              {productOptions.map(p => (
                <option key={p.code} value={p.code}>{p.code} - {p.name}</option>
              ))}
            </select>
          </div>

          {/* Clear Filters */}
          <button
            onClick={clearFilters}
            className="w-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 font-semibold text-xs py-2.5 rounded-xl transition-all cursor-pointer"
          >
            Reset Scope
          </button>
        </div>
      </div>

      {/* Filtered summaries cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="glass-card p-4 shadow-sm">
          <span className="text-slate-400 text-[10px] font-bold uppercase tracking-wider">Filtered Revenue</span>
          <div className="font-display font-bold text-xl text-indigo-500">${cardStats.revenue}</div>
        </div>
        <div className="glass-card p-4 shadow-sm">
          <span className="text-slate-400 text-[10px] font-bold uppercase tracking-wider">Filtered Cost</span>
          <div className="font-display font-bold text-xl">${cardStats.cost}</div>
        </div>
        <div className="glass-card p-4 shadow-sm">
          <span className="text-slate-400 text-[10px] font-bold uppercase tracking-wider">Filtered Profit</span>
          <div className={`font-display font-bold text-xl ${Number(cardStats.profit) >= 0 ? "text-emerald-500" : "text-red-500"}`}>
            ${cardStats.profit}
          </div>
        </div>
        <div className="glass-card p-4 shadow-sm">
          <span className="text-slate-400 text-[10px] font-bold uppercase tracking-wider">Filtered Margin</span>
          <div className="font-display font-bold text-xl text-cyan-500">{cardStats.margin}%</div>
        </div>
      </div>

      {/* Visual Charting & Status cards */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Trend chart */}
        <div className="lg:col-span-2 glass-card p-6 shadow-sm flex flex-col justify-between">
          <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800/80 pb-3.5 mb-4">
            <h3 className="font-display font-bold text-base flex items-center gap-2">
              <TrendingUp className="h-5 w-5 text-indigo-500" />
              Sales & Profit Curves
            </h3>
            <div className="flex gap-4 text-xs font-semibold">
              <span className="flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-full bg-indigo-500" /> Revenue</span>
              <span className="flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-full bg-emerald-500" /> Net Profit</span>
            </div>
          </div>
          {loading ? (
            <div className="h-64 flex items-center justify-center text-slate-400">
              <RefreshCw className="h-7 w-7 animate-spin text-indigo-500" />
            </div>
          ) : (
            renderTrendChart()
          )}
        </div>

        {/* Status Distribution */}
        <div className="lg:col-span-1 glass-card p-6 shadow-sm flex flex-col justify-between">
          <div className="border-b border-slate-100 dark:border-slate-800/80 pb-3.5 mb-4">
            <h3 className="font-display font-bold text-base flex items-center gap-2">
              <PieChart className="h-5 w-5 text-indigo-500" />
              Order Status Ratios
            </h3>
          </div>
          <div className="space-y-4 flex-1 flex flex-col justify-center">
            {statusData.map(st => {
              let barColor = "bg-indigo-500";
              if (st.name === "Approved") barColor = "bg-emerald-500";
              if (st.name === "Processing") barColor = "bg-blue-500";
              if (st.name === "Shipment") barColor = "bg-purple-500";
              if (st.name === "Cancelled") barColor = "bg-red-500";
              if (st.name === "Returned") barColor = "bg-amber-500";

              return (
                <div key={st.name} className="space-y-1">
                  <div className="flex items-center justify-between text-xs font-semibold">
                    <span className="text-slate-500 dark:text-slate-400">{st.name}</span>
                    <span>{st.count} ({st.percentage}%)</span>
                  </div>
                  {/* Custom progress bar */}
                  <div className="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2">
                    <div 
                      className={`${barColor} h-2 rounded-full transition-all duration-500`} 
                      style={{ width: `${st.percentage}%` }}
                    />
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </div>

      {/* Pivot Summary Table */}
      <div className="glass-card shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
          <h3 className="font-display font-bold text-base flex items-center gap-2">
            <Briefcase className="h-5 w-5 text-indigo-500" />
            Product Sales Performance (Pivot Table)
          </h3>
          <span className="text-xs text-slate-400 font-semibold">{pivotData.length} active products</span>
        </div>

        {pivotData.length === 0 ? (
          <div className="p-12 text-center text-slate-400">
            No sales records meet the current scoped filter criteria.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse min-w-[800px]">
              <thead>
                <tr className="bg-slate-50/75 dark:bg-slate-900/50 border-b border-slate-100 dark:border-slate-800 text-slate-400 font-bold text-xs uppercase tracking-wider">
                  <th className="py-4 px-6">Product Code</th>
                  <th className="py-4 px-4">Product Name</th>
                  <th className="py-4 px-4 text-center">Units Sold</th>
                  <th className="py-4 px-4 text-right">Aggregated Cost</th>
                  <th className="py-4 px-4 text-right">Aggregated Revenue</th>
                  <th className="py-4 px-4 text-right">Net Profit</th>
                  <th className="py-4 px-6 text-right">Avg Profit Margin</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800/80 text-sm">
                {pivotData.map(row => (
                  <tr key={row.productCode} className="hover:bg-slate-50/50 dark:hover:bg-slate-900/10 transition-colors">
                    <td className="py-3.5 px-6 font-mono text-xs font-bold text-indigo-500">
                      {row.productCode}
                    </td>
                    <td className="py-3.5 px-4 text-slate-700 dark:text-slate-200 font-medium">
                      {row.productName}
                    </td>
                    <td className="py-3.5 px-4 text-center font-bold text-slate-900 dark:text-white">
                      {row.unitsSold}
                    </td>
                    <td className="py-3.5 px-4 text-right text-slate-500 dark:text-slate-400 font-mono">
                      ${row.cost.toFixed(2)}
                    </td>
                    <td className="py-3.5 px-4 text-right font-semibold text-slate-700 dark:text-slate-200 font-mono">
                      ${row.revenue.toFixed(2)}
                    </td>
                    <td className={`py-3.5 px-4 text-right font-bold font-mono ${row.profit >= 0 ? "text-emerald-500" : "text-red-500"}`}>
                      ${row.profit.toFixed(2)}
                    </td>
                    <td className={`py-3.5 px-6 text-right font-bold ${row.margin >= 0 ? "text-emerald-500" : "text-red-500"}`}>
                      {row.margin}%
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
