"use client";

import { useEffect, useState } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { Mail, ShieldCheck, Smartphone, Server, Gauge, ListChecks, Bell } from "lucide-react";
import { cn } from "@/lib/cn";

function WidgetFrame({
  label,
  children,
}: {
  label: string;
  children: React.ReactNode;
}) {
  return (
    <div className="relative">
      <div
        className="pointer-events-none absolute -inset-6 -z-10 rounded-[2rem] bg-gradient-to-br from-violet-500/20 via-fuchsia-500/10 to-blue-500/20 blur-2xl"
        aria-hidden="true"
      />
      <div className="relative rounded-2xl border border-white/10 bg-white/[0.04] p-5 shadow-2xl shadow-black/40 backdrop-blur-sm">
        <div className="flex items-center justify-between border-b border-white/10 pb-3">
          <div className="flex items-center gap-1.5" aria-hidden="true">
            <span className="h-2.5 w-2.5 rounded-full bg-rose-400/70" />
            <span className="h-2.5 w-2.5 rounded-full bg-amber-300/70" />
            <span className="h-2.5 w-2.5 rounded-full bg-emerald-400/70" />
          </div>
          <span className="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[11px] font-semibold text-slate-400">
            {label}
          </span>
        </div>
        <div className="mt-5">{children}</div>
      </div>
    </div>
  );
}

function useCountTo(target: number, active: boolean, duration = 1000) {
  const [value, setValue] = useState(0);
  const reduceMotion = useReducedMotion();
  useEffect(() => {
    if (!active) return;
    if (reduceMotion) {
      setValue(target);
      return;
    }
    const start = performance.now();
    let raf = 0;
    const tick = (now: number) => {
      const progress = Math.min(1, (now - start) / duration);
      setValue(Math.round((1 - Math.pow(1 - progress, 3)) * target));
      if (progress < 1) raf = requestAnimationFrame(tick);
    };
    raf = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(raf);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [active]);
  return value;
}

function WebsiteVitalsWidget() {
  const metrics = [
    { label: "LCP", pct: 92 },
    { label: "CLS", pct: 97 },
    { label: "INP", pct: 88 },
  ];
  return (
    <WidgetFrame label="Illustrative preview">
      <p className="text-xs text-slate-500">Core Web Vitals target (illustrative)</p>
      <div className="mt-4 flex flex-col gap-4">
        {metrics.map((m, i) => (
          <div key={m.label}>
            <div className="flex justify-between text-xs text-slate-400">
              <span className="font-semibold text-slate-200">{m.label}</span>
              <span>{m.pct}</span>
            </div>
            <div className="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-white/10">
              <motion.div
                className="h-full rounded-full bg-gradient-to-r from-emerald-400 to-blue-400"
                initial={{ width: 0 }}
                animate={{ width: `${m.pct}%` }}
                transition={{ duration: 0.8, delay: i * 0.15, ease: "easeOut" }}
              />
            </div>
          </div>
        ))}
      </div>
    </WidgetFrame>
  );
}

function WebAppWidget() {
  const rows = [
    { name: "Users online", value: 128 },
    { name: "Requests / min", value: 940 },
  ];
  return (
    <WidgetFrame label="Illustrative preview">
      <p className="text-xs text-slate-500">Live operations panel (illustrative)</p>
      <div className="mt-4 grid grid-cols-2 gap-3">
        {rows.map((r) => (
          <div key={r.name} className="rounded-xl border border-white/10 bg-white/[0.02] p-4">
            <p className="text-2xl font-bold text-white">
              <AnimatedCount target={r.value} />
            </p>
            <p className="mt-1 text-xs text-slate-500">{r.name}</p>
          </div>
        ))}
      </div>
      <div className="mt-4 flex items-center gap-2 rounded-xl border border-emerald-400/20 bg-emerald-400/5 px-3 py-2 text-xs text-emerald-300">
        <Gauge className="h-3.5 w-3.5" aria-hidden="true" />
        Dashboard responsive across roles &amp; permissions
      </div>
    </WidgetFrame>
  );
}

function AnimatedCount({ target }: { target: number }) {
  const value = useCountTo(target, true, 1200);
  return <>{value.toLocaleString()}</>;
}

function MobileAppWidget() {
  return (
    <WidgetFrame label="Illustrative preview">
      <div className="flex items-center gap-6">
        <div className="relative mx-auto h-48 w-24 shrink-0 rounded-[1.4rem] border-4 border-white/15 bg-[#0d0e19] p-1.5 shadow-xl">
          <div className="h-full w-full overflow-hidden rounded-[1rem] bg-gradient-to-b from-violet-950/60 to-[#0d0e19] p-2">
            <div className="mx-auto h-1 w-8 rounded-full bg-white/20" />
            <motion.div
              initial={{ y: -20, opacity: 0 }}
              animate={{ y: 0, opacity: 1 }}
              transition={{ delay: 0.4, duration: 0.5 }}
              className="mt-4 flex items-center gap-1.5 rounded-lg bg-white/10 p-1.5"
            >
              <Bell className="h-2.5 w-2.5 text-violet-300" aria-hidden="true" />
              <span className="text-[6px] font-semibold text-white">New order received</span>
            </motion.div>
            <div className="mt-2 h-1.5 w-full rounded-full bg-white/10 overflow-hidden">
              <motion.div
                className="h-full rounded-full bg-gradient-to-r from-violet-400 to-blue-400"
                initial={{ width: "0%" }}
                animate={{ width: "72%" }}
                transition={{ duration: 1, delay: 0.6 }}
              />
            </div>
          </div>
        </div>
        <div className="flex flex-col gap-3 text-xs text-slate-400">
          <p className="flex items-center gap-2">
            <Smartphone className="h-4 w-4 text-violet-300" aria-hidden="true" />
            One codebase, iOS &amp; Android
          </p>
          <p>Push notifications, offline support, and secure accounts — from a single build.</p>
        </div>
      </div>
    </WidgetFrame>
  );
}

function DigitizationWidget() {
  const phases = ["Audit", "Roadmap", "Rollout"];
  const [step, setStep] = useState(0);
  useEffect(() => {
    const id = setInterval(() => setStep((s) => (s + 1) % (phases.length + 1)), 1200);
    return () => clearInterval(id);
  }, [phases.length]);
  return (
    <WidgetFrame label="Illustrative preview">
      <p className="text-xs text-slate-500">Digitization rollout (illustrative)</p>
      <div className="mt-4 flex flex-col gap-3">
        {phases.map((phase, i) => (
          <div key={phase} className="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.02] px-4 py-3">
            <span
              className={cn(
                "flex h-6 w-6 items-center justify-center rounded-full border text-[10px] font-bold transition-colors",
                i < step ? "border-transparent bg-emerald-400 text-[#0b0c14]" : "border-white/20 text-slate-500"
              )}
            >
              {i + 1}
            </span>
            <span className={cn("text-sm", i < step ? "text-white" : "text-slate-500")}>{phase}</span>
            <ListChecks className={cn("ml-auto h-4 w-4", i < step ? "text-emerald-400" : "text-slate-700")} aria-hidden="true" />
          </div>
        ))}
      </div>
    </WidgetFrame>
  );
}

function HostingWidget() {
  return (
    <WidgetFrame label="Illustrative preview">
      <div className="flex items-center justify-between">
        <span className="inline-flex items-center gap-2 text-sm font-semibold text-white">
          <span className="relative flex h-2.5 w-2.5">
            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
            <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-400" />
          </span>
          Operational
        </span>
        <Server className="h-4 w-4 text-slate-500" aria-hidden="true" />
      </div>
      <div className="mt-5 grid grid-cols-2 gap-3">
        <div className="rounded-xl border border-white/10 bg-white/[0.02] p-4">
          <p className="text-2xl font-bold text-white">
            <AnimatedCount target={999} />
            <span className="text-sm text-slate-500">/1000</span>
          </p>
          <p className="mt-1 text-xs text-slate-500">Illustrative uptime index</p>
        </div>
        <div className="rounded-xl border border-white/10 bg-white/[0.02] p-4">
          <p className="text-2xl font-bold text-white">
            <AnimatedCount target={42} />
            <span className="text-sm text-slate-500">ms</span>
          </p>
          <p className="mt-1 text-xs text-slate-500">Illustrative response time</p>
        </div>
      </div>
    </WidgetFrame>
  );
}

function SecurityWidget() {
  const pct = 96;
  const circumference = 2 * Math.PI * 40;
  const reduceMotion = useReducedMotion();
  return (
    <WidgetFrame label="Illustrative preview">
      <div className="flex items-center gap-6">
        <svg viewBox="0 0 100 100" className="h-28 w-28 shrink-0 -rotate-90">
          <circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,0.08)" strokeWidth="10" />
          <motion.circle
            cx="50"
            cy="50"
            r="40"
            fill="none"
            stroke="url(#security-gradient)"
            strokeWidth="10"
            strokeLinecap="round"
            strokeDasharray={circumference}
            initial={{ strokeDashoffset: circumference }}
            animate={{ strokeDashoffset: circumference * (1 - pct / 100) }}
            transition={{ duration: reduceMotion ? 0 : 1.1, ease: "easeOut" }}
          />
          <defs>
            <linearGradient id="security-gradient" x1="0" x2="1" y1="0" y2="1">
              <stop offset="0%" stopColor="#a78bfa" />
              <stop offset="100%" stopColor="#34d399" />
            </linearGradient>
          </defs>
        </svg>
        <div>
          <p className="text-3xl font-bold text-white">{pct}<span className="text-lg text-slate-500">/100</span></p>
          <p className="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
            <ShieldCheck className="h-3.5 w-3.5 text-emerald-400" aria-hidden="true" />
            Illustrative security score
          </p>
        </div>
      </div>
    </WidgetFrame>
  );
}

function EmailWidget() {
  const items = [
    { from: "hello@yourbusiness.com", subject: "Welcome aboard" },
    { from: "team@yourbusiness.com", subject: "Weekly summary" },
  ];
  return (
    <WidgetFrame label="Illustrative preview">
      <p className="text-xs text-slate-500">Inbox preview (illustrative)</p>
      <div className="mt-4 flex flex-col gap-2">
        {items.map((item, i) => (
          <motion.div
            key={item.from}
            initial={{ opacity: 0, x: -12 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: i * 0.15, duration: 0.4 }}
            className="flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.02] px-4 py-3"
          >
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-violet-500/20 to-blue-500/20 text-violet-300">
              <Mail className="h-4 w-4" aria-hidden="true" />
            </span>
            <div className="min-w-0">
              <p className="truncate text-xs font-semibold text-white">{item.subject}</p>
              <p className="truncate text-[11px] text-slate-500">{item.from}</p>
            </div>
          </motion.div>
        ))}
      </div>
    </WidgetFrame>
  );
}

const widgetBySlug: Record<string, React.ComponentType> = {
  "website-development": WebsiteVitalsWidget,
  "web-application-development": WebAppWidget,
  "mobile-app-development": MobileAppWidget,
  "business-digitization": DigitizationWidget,
  "domain-hosting": HostingWidget,
  "ssl-security": SecurityWidget,
  "business-email": EmailWidget,
};

export function ServiceHeroWidget({ slug }: { slug: string }) {
  const Widget = widgetBySlug[slug] ?? WebsiteVitalsWidget;
  return <Widget />;
}
