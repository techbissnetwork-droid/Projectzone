"use client";

import { motion } from "framer-motion";
import { Container } from "@/components/ui/container";
import { Button } from "@/components/ui/button";
import { Eyebrow } from "@/components/ui/section";
import {
  Building2,
  Globe,
  Smartphone,
  Server,
  Mail,
  ShieldCheck,
  CreditCard,
  Workflow,
  TrendingUp,
} from "lucide-react";

const stack = [
  { label: "Business", icon: Building2 },
  { label: "Domain", icon: Globe },
  { label: "Website", icon: Globe },
  { label: "App", icon: Smartphone },
  { label: "Hosting", icon: Server },
  { label: "Email", icon: Mail },
  { label: "Security", icon: ShieldCheck },
  { label: "Payments", icon: CreditCard },
  { label: "Automation", icon: Workflow },
  { label: "Growth", icon: TrendingUp },
];

export function Hero() {
  return (
    <section className="relative overflow-hidden border-b border-line-dark pt-40 pb-20 sm:pt-48 sm:pb-28">
      <div
        aria-hidden
        className="pointer-events-none absolute inset-x-0 top-0 h-[560px] bg-[radial-gradient(ellipse_60%_50%_at_50%_0%,rgba(200,161,101,0.14),rgba(0,0,0,0))]"
      />
      <Container wide className="relative grid gap-16 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
        <div>
          <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
          >
            <Eyebrow>Digital Transformation Platform</Eyebrow>
          </motion.div>

          <h1 className="mt-6 max-w-2xl text-[42px] font-medium leading-[1.05] tracking-[-0.02em] text-paper-50 sm:text-[64px] lg:text-[76px]">
            {["Your business.", "Built for the", "digital world."].map((line, i) => (
              <span key={line} className="block overflow-hidden">
                <motion.span
                  initial={{ y: "100%" }}
                  animate={{ y: 0 }}
                  transition={{ duration: 0.8, delay: 0.15 + i * 0.1, ease: [0.16, 1, 0.3, 1] }}
                  className="block text-balance"
                >
                  {line}
                </motion.span>
              </span>
            ))}
          </h1>

          <motion.p
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.55 }}
            className="mt-7 max-w-lg text-[16px] leading-relaxed text-paper-50/60 sm:text-[18px]"
          >
            From offline operations to a complete online ecosystem — TECHBISS
            builds, launches and powers everything your business needs to
            grow online.
          </motion.p>

          <motion.div
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.7 }}
            className="mt-9 flex flex-wrap items-center gap-3"
          >
            <Button href="/contact" size="lg" arrow>
              Start Your Digital Journey
            </Button>
            <Button href="/services" size="lg" variant="ghost">
              Explore What We Build
            </Button>
          </motion.div>

          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.6, delay: 0.85 }}
            className="mt-4"
          >
            <Button href="/marketplace" size="sm" variant="ghost" arrow className="border-0 pl-0 text-gold-400 hover:text-gold-300">
              Browse Ready-Made Themes
            </Button>
          </motion.div>
        </div>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.8, delay: 0.3 }}
          className="relative"
        >
          <div className="relative rounded-3xl border border-line-dark bg-gradient-to-b from-ink-850 to-ink-900 p-3">
            <div className="relative flex flex-col gap-1.5 rounded-2xl border border-line-dark bg-ink-950/60 p-4">
              {stack.map((item, i) => (
                <motion.div
                  key={item.label}
                  initial={{ opacity: 0, x: 16 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ duration: 0.5, delay: 0.5 + i * 0.06 }}
                  className="group flex items-center justify-between rounded-xl border border-line-dark bg-ink-900/60 px-4 py-3 transition-colors hover:border-gold-500/30"
                >
                  <div className="flex items-center gap-3">
                    <span className="flex size-8 items-center justify-center rounded-lg border border-line-dark bg-ink-850 text-gold-400">
                      <item.icon className="size-4" strokeWidth={1.75} />
                    </span>
                    <span className="text-[13px] font-medium text-paper-50/80">
                      {item.label}
                    </span>
                  </div>
                  <span className="size-1.5 rounded-full bg-success-500 opacity-0 transition-opacity group-hover:opacity-100" />
                </motion.div>
              ))}
            </div>
          </div>
          <div
            aria-hidden
            className="absolute -inset-x-6 -bottom-6 -z-10 h-32 bg-gold-500/10 blur-3xl"
          />
        </motion.div>
      </Container>
    </section>
  );
}
