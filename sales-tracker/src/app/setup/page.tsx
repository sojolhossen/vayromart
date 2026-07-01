"use client";

import React, { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Database, RefreshCw, CheckCircle, AlertCircle, Sparkles } from "lucide-react";

export default function SetupDatabasePage() {
  const router = useRouter();
  const [loading, setLoading] = useState(true);
  const [status, setStatus] = useState<"idle" | "running" | "success" | "error">("idle");
  const [log, setLog] = useState<string[]>([]);
  const [errorMessage, setErrorMessage] = useState("");

  const startSetup = async () => {
    try {
      setStatus("running");
      setLoading(true);
      setErrorMessage("");
      setLog(["[1/3] Contacting Next.js backend environment...", "[2/3] Synching database schemas and generating tables..."]);

      const res = await fetch("/api/setup", {
        method: "POST",
      });
      const result = await res.json();

      if (result.success) {
        setLog(prev => [
          ...prev,
          "[3/3] Tables created successfully!",
          "✓ Pre-populating 20+ realistic sales orders...",
          "🎉 Database Tracker initialized successfully!"
        ]);
        setStatus("success");
        
        // Redirect to dashboard after 3 seconds
        setTimeout(() => {
          router.push("/");
        }, 3000);
      } else {
        setErrorMessage(result.error || "Schema migration failed");
        setLog(prev => [...prev, "❌ Error setting up database schema."]);
        setStatus("error");
      }
    } catch (err: any) {
      setErrorMessage(err.message || "Connection timed out");
      setLog(prev => [...prev, "❌ Failed to connect to setup service."]);
      setStatus("error");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    // Run setup automatically when visiting page
    startSetup();
  }, []);

  return (
    <div className="min-h-[70vh] flex flex-col items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
      <div className="w-full max-w-md space-y-8 text-center">
        {/* Brand Logo Header */}
        <div className="flex flex-col items-center">
          <div className="h-16 w-16 rounded-2xl bg-gradient-to-tr from-indigo-500 to-cyan-500 flex items-center justify-center text-white shadow-xl shadow-indigo-500/20 mb-4 animate-bounce">
            <Database className="h-8 w-8" />
          </div>
          <h2 className="font-display font-extrabold text-3xl tracking-tight bg-gradient-to-r from-indigo-500 to-cyan-500 bg-clip-text text-transparent">
            Database Setup Console
          </h2>
          <p className="text-slate-500 dark:text-slate-400 text-sm mt-1">
            Setting up your local SQLite environment and seeding database.
          </p>
        </div>

        {/* Status card */}
        <div className="glass-card p-6 shadow-xl space-y-6">
          {/* Animated Spinner or Icons */}
          <div className="flex items-center justify-center">
            {status === "running" && (
              <RefreshCw className="h-12 w-12 animate-spin text-indigo-500" />
            )}
            {status === "success" && (
              <div className="relative">
                <CheckCircle className="h-14 w-14 text-emerald-500" />
                <Sparkles className="h-6 w-6 text-amber-400 absolute -top-1.5 -right-1.5 animate-pulse" />
              </div>
            )}
            {status === "error" && (
              <AlertCircle className="h-12 w-12 text-red-500" />
            )}
          </div>

          {/* Logs */}
          <div className="bg-slate-900 text-slate-300 font-mono text-left rounded-xl p-4 text-xs space-y-2 leading-relaxed max-h-48 overflow-y-auto shadow-inner border border-slate-800">
            {log.map((line, idx) => (
              <div key={idx} className={line.startsWith("✓") || line.startsWith("🎉") ? "text-emerald-400" : line.startsWith("❌") ? "text-red-400" : "text-slate-300"}>
                {line}
              </div>
            ))}
          </div>

          {/* Messages */}
          {status === "success" && (
            <div className="text-sm font-semibold text-emerald-500 animate-pulse">
              Success! Redirecting you to the Sales Dashboard in 3 seconds...
            </div>
          )}

          {status === "error" && (
            <div className="space-y-4">
              <div className="text-xs text-red-400 bg-red-500/10 border border-red-500/20 rounded-lg p-3 font-medium">
                {errorMessage}
              </div>
              <button
                onClick={startSetup}
                className="w-full bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white font-semibold py-3 rounded-xl shadow-md transition-all cursor-pointer"
              >
                Retry Database Setup
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
