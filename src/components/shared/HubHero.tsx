"use client";

import Link from "next/link";
import { motion } from "framer-motion";
import { ArrowUpRight } from "lucide-react";
import { concepts, company } from "@/lib/site-data";

const conceptDetails: Record<
  string,
  { blurb: string; accent: string; ring: string; bg: string }
> = {
  "concept-1": {
    blurb:
      "Dark glass surfaces, cinematic typography, and intelligent motion — a $100B enterprise-technology feel.",
    accent: "from-cyan-400 via-indigo-400 to-fuchsia-400",
    ring: "hover:ring-cyan-400/40",
    bg: "bg-neutral-950",
  },
  "concept-2": {
    blurb:
      "Extreme whitespace and massive typography. Expensive because of restraint, not effects.",
    accent: "from-neutral-400 via-neutral-300 to-neutral-500",
    ring: "hover:ring-neutral-400/50",
    bg: "bg-white",
  },
  "concept-3": {
    blurb:
      "Layered, scroll-driven, dashboard-inspired interactivity. A next-generation product feel.",
    accent: "from-violet-400 via-blue-400 to-emerald-400",
    ring: "hover:ring-violet-400/40",
    bg: "bg-[#0b0c14]",
  },
};

export function HubHero() {
  return (
    <main className="min-h-screen bg-neutral-50 text-neutral-900">
      <div className="mx-auto max-w-6xl px-6 py-20 sm:py-28">
        <motion.p
          initial={{ opacity: 0, y: 8 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
          className="text-sm font-medium tracking-[0.2em] text-neutral-500 uppercase"
        >
          {company.name} · Design Concepts
        </motion.p>

        <motion.h1
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6, delay: 0.05 }}
          className="mt-4 max-w-3xl text-4xl font-semibold tracking-tight sm:text-6xl"
        >
          We Build What Moves Business Forward.
        </motion.h1>

        <motion.p
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6, delay: 0.1 }}
          className="mt-6 max-w-2xl text-lg text-neutral-600"
        >
          {company.description} Below are three independent, complete design
          concepts for the TECHBISS website — each with its own visual
          identity, typography, motion language, and full 16-page site.
        </motion.p>

        <div className="mt-14 grid gap-6 sm:grid-cols-3">
          {concepts.map((c, i) => {
            const d = conceptDetails[c.slug];
            return (
              <motion.div
                key={c.slug}
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, delay: 0.15 + i * 0.08 }}
              >
                <Link
                  href={`/${c.slug}`}
                  className={`group flex h-full flex-col justify-between rounded-2xl border border-neutral-200 p-6 shadow-sm ring-1 ring-transparent transition-all duration-300 hover:-translate-y-1 hover:shadow-lg ${d.ring}`}
                >
                  <div>
                    <div
                      className={`h-1.5 w-12 rounded-full bg-gradient-to-r ${d.accent}`}
                    />
                    <p className="mt-5 text-xs font-medium tracking-[0.15em] text-neutral-400 uppercase">
                      Concept 0{i + 1}
                    </p>
                    <h2 className="mt-1 text-2xl font-semibold">{c.name}</h2>
                    <p className="mt-3 text-sm leading-relaxed text-neutral-600">
                      {d.blurb}
                    </p>
                  </div>
                  <div className="mt-8 flex items-center gap-1.5 text-sm font-medium text-neutral-900">
                    Explore the design
                    <ArrowUpRight className="h-4 w-4 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                  </div>
                </Link>
              </motion.div>
            );
          })}
        </div>

        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ duration: 0.6, delay: 0.5 }}
          className="mt-20 border-t border-neutral-200 pt-8 text-sm text-neutral-500"
        >
          Each concept implements the full TECHBISS site — Home, About,
          Services, all service deep-dives, Portfolio, Pricing, Process,
          Technology, Contact, and Get Started — as one coherent, responsive
          design system.
        </motion.div>
      </div>
    </main>
  );
}
