"use client";

import { motion } from "framer-motion";
import { ArrowRight, CheckCircle2, TrendingUp, Users } from "lucide-react";
import { Container } from "@/components/ui/Container";
import { Button } from "@/components/ui/Button";
import { Badge } from "@/components/ui/Badge";

export function Hero() {
  return (
    <section className="relative overflow-hidden pt-16 pb-24 sm:pt-20 sm:pb-32 lg:pt-28 lg:pb-40">
      <div className="bg-grid pointer-events-none absolute inset-0 [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_60%,transparent_100%)]" />
      <div className="pointer-events-none absolute left-1/2 top-[-12rem] h-[36rem] w-[64rem] -translate-x-1/2 rounded-full bg-[radial-gradient(closest-side,rgba(75,91,255,0.25),transparent)] blur-2xl" />
      <div className="bg-noise pointer-events-none absolute inset-0" />

      <Container size="wide" className="relative">
        <div className="flex flex-col items-center text-center">
          <motion.div
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
          >
            <Badge variant="accent" className="mb-6">
              <span className="h-1.5 w-1.5 rounded-full bg-(--color-accent-2)" />
              Trusted by 400+ teams worldwide
            </Badge>
          </motion.div>

          <motion.h1
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.7, delay: 0.06, ease: [0.16, 1, 0.3, 1] }}
            className="max-w-4xl text-balance text-4xl font-medium leading-[1.08] tracking-tight text-(--color-ink) sm:text-5xl lg:text-6xl lg:leading-[1.05]"
          >
            Digital transformation, <span className="text-gradient-brand">engineered</span> for scale.
          </motion.h1>

          <motion.p
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.7, delay: 0.14, ease: [0.16, 1, 0.3, 1] }}
            className="mt-6 max-w-2xl text-balance text-base leading-relaxed text-(--color-ink-muted) sm:text-lg"
          >
            TECHBISS builds product, platform and infrastructure for the world&apos;s most ambitious companies —
            plus a premium marketplace of ready-made platforms you can launch today with our Advanced Installer.
          </motion.p>

          <motion.div
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.7, delay: 0.22, ease: [0.16, 1, 0.3, 1] }}
            className="mt-9 flex flex-col items-center gap-3 sm:flex-row"
          >
            <Button href="/contact" variant="secondary" size="lg" icon={<ArrowRight className="h-4 w-4" />}>
              Start a Project
            </Button>
            <Button href="/marketplace" variant="outline" size="lg">
              Explore Marketplace
            </Button>
          </motion.div>

          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.7, delay: 0.32 }}
            className="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs text-(--color-ink-faint)"
          >
            <span className="inline-flex items-center gap-1.5">
              <CheckCircle2 className="h-3.5 w-3.5 text-(--color-accent-2)" /> SOC 2 &amp; ISO 27001 ready
            </span>
            <span className="inline-flex items-center gap-1.5">
              <Users className="h-3.5 w-3.5 text-(--color-accent-2)" /> 120+ engineers &amp; strategists
            </span>
            <span className="inline-flex items-center gap-1.5">
              <TrendingUp className="h-3.5 w-3.5 text-(--color-accent-2)" /> $2.1B+ client revenue enabled
            </span>
          </motion.div>
        </div>

        <motion.div
          initial={{ opacity: 0, y: 32 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.8, delay: 0.4, ease: [0.16, 1, 0.3, 1] }}
          className="relative mx-auto mt-16 max-w-5xl sm:mt-20"
        >
          <HeroPreview />
        </motion.div>
      </Container>
    </section>
  );
}

function HeroPreview() {
  return (
    <div className="relative rounded-(--radius-xl) border border-(--color-border) bg-(--color-surface) p-2 shadow-2xl shadow-black/40 sm:p-3">
      <div className="flex items-center gap-1.5 px-3 py-2.5">
        <span className="h-2.5 w-2.5 rounded-full bg-red-500/70" />
        <span className="h-2.5 w-2.5 rounded-full bg-amber-500/70" />
        <span className="h-2.5 w-2.5 rounded-full bg-emerald-500/70" />
        <span className="ml-3 text-xs text-(--color-ink-faint)">app.techbiss.com/dashboard</span>
      </div>
      <div className="grid grid-cols-1 gap-3 rounded-(--radius-lg) bg-(--color-canvas) p-4 sm:grid-cols-3 sm:p-6">
        <div className="rounded-(--radius-md) border border-(--color-border) bg-(--color-surface) p-4 sm:col-span-2">
          <div className="mb-4 flex items-center justify-between">
            <span className="text-sm font-medium text-(--color-ink)">Revenue Growth</span>
            <Badge variant="success">+34.2%</Badge>
          </div>
          <svg viewBox="0 0 300 100" className="h-24 w-full">
            <defs>
              <linearGradient id="hero-chart" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor="#4b5bff" stopOpacity="0.35" />
                <stop offset="100%" stopColor="#4b5bff" stopOpacity="0" />
              </linearGradient>
            </defs>
            <path
              d="M0 80 L30 68 L60 72 L90 50 L120 55 L150 34 L180 40 L210 22 L240 28 L270 10 L300 16"
              fill="none"
              stroke="url(#hero-line)"
              strokeWidth="2.5"
              strokeLinecap="round"
            />
            <path
              d="M0 80 L30 68 L60 72 L90 50 L120 55 L150 34 L180 40 L210 22 L240 28 L270 10 L300 16 L300 100 L0 100 Z"
              fill="url(#hero-chart)"
            />
            <linearGradient id="hero-line" x1="0" y1="0" x2="1" y2="0">
              <stop offset="0%" stopColor="#4b5bff" />
              <stop offset="100%" stopColor="#17c3ff" />
            </linearGradient>
          </svg>
        </div>
        <div className="flex flex-col gap-3">
          <div className="rounded-(--radius-md) border border-(--color-border) bg-(--color-surface) p-4">
            <span className="text-xs text-(--color-ink-faint)">Active Deployments</span>
            <p className="mt-1 text-2xl font-medium text-(--color-ink)">248</p>
          </div>
          <div className="rounded-(--radius-md) border border-(--color-border) bg-(--color-surface) p-4">
            <span className="text-xs text-(--color-ink-faint)">Uptime</span>
            <p className="mt-1 text-2xl font-medium text-(--color-ink)">99.99%</p>
          </div>
        </div>
      </div>
    </div>
  );
}
