"use client";

import { Eyebrow } from "@/components/ui/section";
import { Container } from "@/components/ui/container";
import { Reveal } from "@/components/ui/reveal";
import {
  Palette,
  Globe,
  Smartphone,
  Cpu,
  Database,
  CreditCard,
  Server,
  ShieldCheck,
  BarChart3,
} from "lucide-react";
import { motion } from "framer-motion";

const layers = [
  { label: "Brand", icon: Palette, desc: "Identity, voice, visual system" },
  { label: "Website", icon: Globe, desc: "Customer-facing experience" },
  { label: "App", icon: Smartphone, desc: "Mobile & customer tools" },
  { label: "Backend", icon: Cpu, desc: "Business logic & APIs" },
  { label: "Database", icon: Database, desc: "Customers, orders, content" },
  { label: "Payments", icon: CreditCard, desc: "Transactions & payouts" },
  { label: "Hosting", icon: Server, desc: "Infrastructure & scaling" },
  { label: "Security", icon: ShieldCheck, desc: "SSL, monitoring, backups" },
  { label: "Analytics", icon: BarChart3, desc: "Insight & optimization" },
];

export function Architecture() {
  return (
    <section className="border-b border-line-dark py-24 sm:py-32">
      <Container>
        <Reveal className="text-center">
          <Eyebrow className="justify-center">Digital Architecture</Eyebrow>
          <h2 className="mx-auto mt-5 max-w-2xl text-[32px] font-medium leading-[1.1] tracking-[-0.02em] text-paper-50 sm:text-[44px]">
            We can build the technology behind your entire business.
          </h2>
        </Reveal>

        <div className="relative mx-auto mt-16 max-w-md">
          <div
            aria-hidden
            className="absolute left-6 top-6 bottom-6 w-px bg-gradient-to-b from-gold-500/50 via-line-dark-strong to-transparent"
          />
          <div className="flex flex-col gap-3">
            {layers.map((layer, i) => (
              <motion.div
                key={layer.label}
                initial={{ opacity: 0, x: -12 }}
                whileInView={{ opacity: 1, x: 0 }}
                viewport={{ once: true, margin: "-60px" }}
                transition={{ duration: 0.5, delay: i * 0.06 }}
                className="relative flex items-center gap-4 pl-0"
              >
                <span className="relative z-10 flex size-12 shrink-0 items-center justify-center rounded-xl border border-line-dark-strong bg-ink-900 text-gold-400">
                  <layer.icon className="size-5" strokeWidth={1.75} />
                </span>
                <div className="flex flex-1 items-center justify-between rounded-xl border border-line-dark bg-ink-900/50 px-4 py-3">
                  <div>
                    <div className="text-[14px] font-medium text-paper-50">{layer.label}</div>
                    <div className="text-[12px] text-paper-50/45">{layer.desc}</div>
                  </div>
                  <span className="size-1.5 shrink-0 rounded-full bg-success-500" />
                </div>
              </motion.div>
            ))}
          </div>
        </div>
      </Container>
    </section>
  );
}
