"use client";

import { Eyebrow } from "@/components/ui/section";
import { Container } from "@/components/ui/container";
import { Reveal } from "@/components/ui/reveal";
import {
  FileText,
  Phone,
  Store,
  Globe,
  CreditCard,
  Mail,
  Cloud,
  Workflow,
  BarChart3,
} from "lucide-react";

const offline = [
  { label: "Physical store", icon: Store },
  { label: "Paper records", icon: FileText },
  { label: "Phone orders", icon: Phone },
];

const digital = [
  { label: "Website & app", icon: Globe },
  { label: "Online payments", icon: CreditCard },
  { label: "Business email", icon: Mail },
  { label: "Cloud infrastructure", icon: Cloud },
  { label: "Automation", icon: Workflow },
  { label: "Analytics & growth", icon: BarChart3 },
];

export function Transformation() {
  return (
    <section className="border-b border-line-dark bg-ink-900/40 py-24 sm:py-32">
      <Container wide>
        <Reveal>
          <Eyebrow>Digital Transformation</Eyebrow>
          <h2 className="mt-5 max-w-2xl text-[32px] font-medium leading-[1.1] tracking-[-0.02em] text-paper-50 sm:text-[44px]">
            From offline operations to a growing digital business.
          </h2>
        </Reveal>

        <div className="mt-16 grid gap-6 lg:grid-cols-[1fr_auto_1.4fr] lg:items-center">
          <Reveal delay={0.05}>
            <div className="rounded-2xl border border-line-dark bg-ink-900/60 p-6">
              <div className="font-mono-label text-[11px] uppercase text-paper-50/40">
                Before — Offline
              </div>
              <div className="mt-5 flex flex-col gap-3">
                {offline.map((item) => (
                  <div
                    key={item.label}
                    className="flex items-center gap-3 rounded-xl border border-line-dark bg-ink-950/50 px-4 py-3 text-paper-50/50"
                  >
                    <item.icon className="size-4" strokeWidth={1.75} />
                    <span className="text-[13px]">{item.label}</span>
                  </div>
                ))}
              </div>
              <p className="mt-5 text-[13px] leading-relaxed text-paper-50/40">
                Limited reach. Disconnected operations. Growth capped by
                hours in the day.
              </p>
            </div>
          </Reveal>

          <Reveal delay={0.1} className="flex items-center justify-center py-4 lg:rotate-0">
            <div className="flex items-center gap-2 lg:flex-col">
              <span className="h-px w-10 bg-gradient-to-r from-transparent to-gold-500/60 lg:h-10 lg:w-px lg:bg-gradient-to-b" />
              <span className="font-mono-label whitespace-nowrap rounded-full border border-gold-500/30 bg-gold-500/10 px-3 py-1.5 text-[10px] uppercase text-gold-400">
                TECHBISS
              </span>
              <span className="h-px w-10 bg-gradient-to-l from-transparent to-gold-500/60 lg:h-10 lg:w-px lg:bg-gradient-to-t" />
            </div>
          </Reveal>

          <Reveal delay={0.15}>
            <div className="rounded-2xl border border-gold-500/25 bg-gradient-to-br from-ink-850 to-ink-900 p-6">
              <div className="font-mono-label text-[11px] uppercase text-gold-400">
                After — Digital &amp; Growing
              </div>
              <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                {digital.map((item) => (
                  <div
                    key={item.label}
                    className="flex flex-col items-start gap-2.5 rounded-xl border border-line-dark bg-ink-950/40 px-4 py-3.5"
                  >
                    <item.icon className="size-4 text-gold-400" strokeWidth={1.75} />
                    <span className="text-[13px] leading-tight text-paper-50/85">
                      {item.label}
                    </span>
                  </div>
                ))}
              </div>
              <p className="mt-5 text-[13px] leading-relaxed text-paper-50/50">
                Global reach. Connected operations. Systems that keep
                working while you sleep.
              </p>
            </div>
          </Reveal>
        </div>
      </Container>
    </section>
  );
}
