"use client";

import { useEffect, useState } from "react";
import { motion } from "motion/react";
import {
  Sparkles,
  Globe,
  Smartphone,
  Cpu,
  Database,
  CreditCard,
  Server,
  ShieldCheck,
  LineChart,
  type LucideProps,
} from "lucide-react";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { Reveal } from "@/components/shared/Reveal";
import { cn } from "@/lib/utils/cn";

const layers: {
  label: string;
  desc: string;
  icon: React.ComponentType<LucideProps>;
}[] = [
  { label: "Brand", desc: "Identity, tone and visual system", icon: Sparkles },
  { label: "Website", desc: "Marketing site & digital storefront", icon: Globe },
  { label: "App", desc: "Mobile & customer experience layer", icon: Smartphone },
  { label: "Backend", desc: "Business logic, services & APIs", icon: Cpu },
  { label: "Database", desc: "Structured, reliable data storage", icon: Database },
  { label: "Payments", desc: "Secure transaction processing", icon: CreditCard },
  { label: "Hosting", desc: "Scalable cloud infrastructure", icon: Server },
  { label: "Security", desc: "Encryption, monitoring, defense", icon: ShieldCheck },
  { label: "Analytics", desc: "Insight into real usage & growth", icon: LineChart },
];

const logLines = [
  "POST /api/checkout → 200 OK · 118ms",
  "SSL certificate renewed · A+ rating",
  "CDN cache hit ratio · 99.2%",
  "Automated backup completed · 03:00 UTC",
  "New deploy → production · verified",
  "Uptime · 99.99% (30d)",
  "DB replica sync · 0 lag",
  "Firewall · 0 threats today",
];

function SystemTerminal() {
  const [tick, setTick] = useState(0);

  useEffect(() => {
    const id = setInterval(() => setTick((t) => t + 1), 2200);
    return () => clearInterval(id);
  }, []);

  const visible = Array.from({ length: 5 }, (_, i) => logLines[(tick + i) % logLines.length]);

  return (
    <div className="rounded-2xl border border-line bg-ink-raised-2 p-6">
      <div className="flex items-center justify-between border-b border-line pb-4">
        <span className="text-eyebrow text-paper-faint">System Status</span>
        <span className="flex items-center gap-2 text-eyebrow text-success">
          <motion.span
            animate={{ opacity: [1, 0.3, 1] }}
            transition={{ duration: 1.6, repeat: Infinity }}
            className="size-1.5 rounded-full bg-success"
          />
          Operational
        </span>
      </div>
      <div className="mt-4 flex flex-col gap-2.5 font-mono text-[0.72rem] leading-relaxed text-paper-dim">
        {visible.map((line, i) => (
          <motion.div
            key={`${tick}-${i}`}
            initial={{ opacity: 0, x: -6 }}
            animate={{ opacity: 1 - i * 0.16, x: 0 }}
            transition={{ duration: 0.5 }}
            className="truncate"
          >
            <span className="text-signal-bright">$</span> {line}
          </motion.div>
        ))}
      </div>
      <div className="mt-6 grid grid-cols-3 gap-3 border-t border-line pt-5">
        {[
          { label: "Uptime", value: "99.99%" },
          { label: "Latency", value: "<50ms" },
          { label: "Threats", value: "0" },
        ].map((stat) => (
          <div key={stat.label}>
            <p className="text-lg font-medium text-paper">{stat.value}</p>
            <p className="text-eyebrow mt-1 text-paper-faint">{stat.label}</p>
          </div>
        ))}
      </div>
    </div>
  );
}

export function ArchitectureDiagram() {
  return (
    <section className="border-y border-line bg-ink-raised py-24 md:py-32">
      <Container>
        <SectionHeading
          eyebrow="Beyond the Website"
          tone="signal"
          title="We build digital systems, not just websites."
          lead="Every layer of your business — brand, frontend, backend, data, payments and security — engineered to work as one connected system."
        />

        <div className="mt-14 grid gap-10 lg:grid-cols-[minmax(0,1fr)_380px] lg:gap-14">
          <Reveal delay={0.1}>
            <div className="relative flex flex-col">
              {layers.map((layer, i) => {
                const Icon = layer.icon;
                return (
                  <div key={layer.label}>
                    <div className="group flex items-center gap-5 rounded-xl border border-transparent px-3 py-4 transition-colors duration-300 hover:border-line hover:bg-ink-raised-2 sm:gap-6 sm:px-5">
                      <span className="text-eyebrow w-6 shrink-0 text-paper-faint">
                        0{i + 1}
                      </span>
                      <span className="flex size-11 shrink-0 items-center justify-center rounded-full border border-line-strong text-signal-bright transition-colors duration-300 group-hover:border-signal/50 group-hover:bg-signal-dim">
                        <Icon className="size-[1.1rem]" aria-hidden />
                      </span>
                      <span className="flex min-w-0 flex-1 flex-col gap-0.5 sm:flex-row sm:items-baseline sm:justify-between">
                        <span className="text-base font-medium text-paper sm:text-lg">
                          {layer.label}
                        </span>
                        <span className="truncate text-sm text-paper-dim">
                          {layer.desc}
                        </span>
                      </span>
                      <span className="hidden shrink-0 items-center gap-1.5 text-eyebrow text-paper-faint sm:flex">
                        <span className="size-1.5 rounded-full bg-success/80" />
                        Live
                      </span>
                    </div>
                    {i < layers.length - 1 && (
                      <div className="relative ml-[3.65rem] h-5 w-px overflow-hidden bg-line-strong sm:ml-[4.15rem]">
                        <motion.span
                          className="absolute inset-x-0 top-0 h-1/2 bg-gradient-to-b from-transparent via-signal-bright to-transparent"
                          animate={{ y: ["-100%", "220%"] }}
                          transition={{
                            duration: 2,
                            repeat: Infinity,
                            ease: "linear",
                            delay: i * 0.15,
                          }}
                        />
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          </Reveal>

          <Reveal delay={0.2} className="lg:sticky lg:top-28 lg:self-start">
            <SystemTerminal />
          </Reveal>
        </div>
      </Container>
    </section>
  );
}
