"use client";

import { motion } from "motion/react";
import { ServiceIcon } from "@/components/shared/ServiceIcon";
import type { Service } from "@/lib/data/services";
import { cn } from "@/lib/utils/cn";

function DeviceFrame({ service }: { service: Service }) {
  const tone = service.color === "gold" ? "gold" : "signal";
  return (
    <div className="overflow-hidden rounded-2xl border border-line bg-ink-raised-2">
      <div className="flex items-center gap-4 border-b border-line px-5 py-3.5">
        <div className="flex gap-1.5">
          {["bg-paper-faint/40", "bg-paper-faint/40", "bg-paper-faint/40"].map((c, i) => (
            <span key={i} className={cn("size-2.5 rounded-full", c)} />
          ))}
        </div>
        <div className="flex-1 rounded-full bg-ink-raised-3 px-3 py-1.5 text-center font-mono text-[0.68rem] text-paper-faint">
          techbiss.com/{service.slug}
        </div>
      </div>
      <div className="flex flex-col gap-3 p-6">
        <motion.div
          initial={{ opacity: 0, y: 8 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6 }}
          className="h-4 w-2/3 rounded bg-ink-raised-3"
        />
        <motion.div
          initial={{ opacity: 0, y: 8 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6, delay: 0.1 }}
          className="h-3 w-1/2 rounded bg-ink-raised-3"
        />
        <div className="mt-3 grid grid-cols-3 gap-3">
          {[0, 1, 2].map((i) => (
            <motion.div
              key={i}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, delay: 0.2 + i * 0.1 }}
              className="aspect-square rounded-lg bg-ink-raised-3"
            />
          ))}
        </div>
        <motion.div
          initial={{ opacity: 0, scale: 0.9 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ duration: 0.5, delay: 0.55 }}
          className={cn(
            "mt-3 h-9 w-32 rounded-full",
            tone === "gold" ? "bg-gold/70" : "bg-signal/70",
          )}
        />
      </div>
    </div>
  );
}

function SystemPanel({ service }: { service: Service }) {
  const rows = [
    { label: "SSL / TLS", value: "Active" },
    { label: "Firewall", value: "Enforced" },
    { label: "Backups", value: "Daily" },
    { label: "Monitoring", value: "Live" },
  ];
  return (
    <div className="overflow-hidden rounded-2xl border border-line bg-ink-raised-2 p-6">
      <div className="flex items-center justify-between border-b border-line pb-4">
        <span className="text-eyebrow text-paper-faint">{service.name}</span>
        <span className="flex items-center gap-2 text-eyebrow text-success">
          <motion.span
            animate={{ opacity: [1, 0.3, 1] }}
            transition={{ duration: 1.6, repeat: Infinity }}
            className="size-1.5 rounded-full bg-success"
          />
          Secure
        </span>
      </div>
      <div className="mt-4 flex flex-col gap-3">
        {rows.map((row, i) => (
          <motion.div
            key={row.label}
            initial={{ opacity: 0, x: -8 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ duration: 0.5, delay: i * 0.1 }}
            className="flex items-center justify-between rounded-lg bg-ink-raised-3/60 px-4 py-3"
          >
            <span className="text-sm text-paper-dim">{row.label}</span>
            <span className="text-sm font-medium text-signal-bright">{row.value}</span>
          </motion.div>
        ))}
      </div>
    </div>
  );
}

function FlowPanel({ service }: { service: Service }) {
  const isGold = service.color === "gold";
  const steps = ["Trigger", "Process", "Deliver"];
  return (
    <div className="flex flex-col items-center justify-center gap-8 rounded-2xl border border-line bg-ink-raised-2 p-10">
      <div className="flex w-full items-center justify-between">
        {steps.map((s, i) => (
          <div key={s} className="flex flex-1 items-center">
            <div className="flex flex-col items-center gap-3">
              <span
                className={cn(
                  "flex size-14 items-center justify-center rounded-full border",
                  isGold
                    ? "border-gold/40 bg-gold-dim text-gold-bright"
                    : "border-signal/40 bg-signal-dim text-signal-bright",
                )}
              >
                {i === 1 ? (
                  <ServiceIcon name={service.icon} className="size-6" aria-hidden />
                ) : (
                  <span className="size-2.5 rounded-full bg-current" />
                )}
              </span>
              <span className="text-eyebrow text-paper-faint">{s}</span>
            </div>
            {i < steps.length - 1 && (
              <div className="relative mx-2 h-px flex-1 overflow-hidden bg-line-strong">
                <motion.span
                  className={cn(
                    "absolute inset-y-0 left-0 w-1/3",
                    isGold
                      ? "bg-gradient-to-r from-transparent via-gold-bright to-transparent"
                      : "bg-gradient-to-r from-transparent via-signal-bright to-transparent",
                  )}
                  animate={{ x: ["-100%", "300%"] }}
                  transition={{ duration: 2, repeat: Infinity, ease: "linear", delay: i * 0.3 }}
                />
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}

export function ServiceHeroVisual({ service }: { service: Service }) {
  if (service.category === "Infrastructure") return <SystemPanel service={service} />;
  if (service.category === "Growth & Operations") return <FlowPanel service={service} />;
  return <DeviceFrame service={service} />;
}
