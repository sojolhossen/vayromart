"use client";

import React, { useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useTheme } from "@/components/ThemeProvider";
import { 
  LayoutDashboard, 
  UserSearch, 
  BarChart3, 
  Sun, 
  Moon, 
  Menu, 
  X, 
  TrendingUp, 
  ShoppingBag, 
  DollarSign
} from "lucide-react";

export default function LayoutShell({ children }: { children: React.ReactNode }) {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const pathname = usePathname();
  const { theme, toggleTheme } = useTheme();

  const navigation = [
    { name: "Sales Sheet", href: "/", icon: LayoutDashboard },
    { name: "Customer Lookup", href: "/lookup", icon: UserSearch },
    { name: "Analytics & Pivot", href: "/analytics", icon: BarChart3 },
  ];

  return (
    <div className="min-h-screen flex flex-col md:flex-row bg-[var(--background)] text-[var(--foreground)]">
      {/* Mobile Header Bar */}
      <header className="md:hidden flex items-center justify-between px-4 py-3 bg-[var(--sidebar-bg)] border-b border-[var(--sidebar-border)] z-40">
        <div className="flex items-center gap-2">
          <ShoppingBag className="h-6 w-6 text-[var(--primary)]" />
          <span className="font-display font-bold text-lg tracking-tight">VayroMart Sales</span>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={toggleTheme}
            className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
            aria-label="Toggle theme"
          >
            {theme === "light" ? <Moon className="h-5 w-5" /> : <Sun className="h-5 w-5 text-amber-400" />}
          </button>
          <button
            onClick={() => setSidebarOpen(true)}
            className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
            aria-label="Open sidebar"
          >
            <Menu className="h-6 w-6" />
          </button>
        </div>
      </header>

      {/* Sidebar - Desktop & Drawer on Mobile */}
      <aside
        className={`fixed inset-y-0 left-0 w-64 bg-[var(--sidebar-bg)] border-r border-[var(--sidebar-border)] flex flex-col transition-transform duration-300 ease-in-out z-50 md:translate-x-0 md:static md:h-screen ${
          sidebarOpen ? "translate-x-0" : "-translate-x-full"
        }`}
      >
        {/* Brand / Logo */}
        <div className="h-16 flex items-center justify-between px-6 border-b border-[var(--sidebar-border)]">
          <Link href="/" className="flex items-center gap-3">
            <div className="bg-gradient-to-tr from-[var(--primary)] to-[var(--accent)] p-2 rounded-xl text-white shadow-md shadow-orange-500/20">
              <ShoppingBag className="h-5 w-5" />
            </div>
            <span className="font-display font-extrabold text-lg tracking-tight bg-gradient-to-r from-[var(--primary)] to-[var(--accent)] bg-clip-text text-transparent">
              VayroMart Tracker
            </span>
          </Link>
          <button
            onClick={() => setSidebarOpen(false)}
            className="md:hidden p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
            aria-label="Close sidebar"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Navigation links */}
        <nav className="flex-1 px-4 py-6 space-y-1.5 overflow-y-auto">
          {navigation.map((item) => {
            const isActive = pathname === item.href;
            const Icon = item.icon;
            return (
              <Link
                key={item.name}
                href={item.href}
                onClick={() => setSidebarOpen(false)}
                className={`flex items-center gap-3.5 px-4 py-3 rounded-xl font-medium text-sm transition-all duration-200 group relative ${
                  isActive
                    ? "nav-link-active"
                    : "text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-900 hover:text-[var(--foreground)]"
                }`}
              >
                <Icon className={`h-5 w-5 transition-colors duration-200 ${
                  isActive ? "text-[var(--primary)]" : "text-slate-400 dark:text-slate-500 group-hover:text-[var(--foreground)]"
                }`} />
                {item.name}
              </Link>
            );
          })}
        </nav>

        {/* Footer Settings / Dark mode */}
        <div className="p-4 border-t border-[var(--sidebar-border)] flex items-center justify-between">
          <div className="flex items-center gap-2">
            <div className="h-8 w-8 rounded-full bg-orange-500/10 flex items-center justify-center text-[var(--primary)] font-bold text-xs">
              VM
            </div>
            <div className="flex flex-col">
              <span className="text-xs font-semibold leading-tight">Admin Console</span>
              <span className="text-[10px] text-slate-400 leading-tight">Dropship Mode</span>
            </div>
          </div>
          <button
            onClick={toggleTheme}
            className="hidden md:block p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-900 border border-[var(--sidebar-border)] transition-colors cursor-pointer"
            title="Toggle theme"
          >
            {theme === "light" ? <Moon className="h-4 w-4" /> : <Sun className="h-4 w-4 text-amber-400" />}
          </button>
        </div>
      </aside>

      {/* Backdrop for mobile drawer */}
      {sidebarOpen && (
        <div
          onClick={() => setSidebarOpen(false)}
          className="fixed inset-0 bg-black/40 backdrop-blur-sm z-40 md:hidden"
        />
      )}

      {/* Main Content Area */}
      <main className="flex-1 flex flex-col min-w-0 md:h-screen md:overflow-y-auto">
        <div className="flex-1 p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">
          {children}
        </div>
      </main>
    </div>
  );
}
