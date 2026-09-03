"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { ArrowRight, Phone, Mail, MapPin, Clock, Sparkles } from "lucide-react";
import { cn } from "@/lib/utils";
import { Card, PageHeader } from "@/components/dashboard/page-header";
import type { MySite } from "@/lib/data/dashboard";

const FONTS = [
  { value: "sans", label: "Modern Sans", stack: "ui-sans-serif, system-ui, sans-serif" },
  { value: "serif", label: "Classic Serif", stack: "Georgia, 'Times New Roman', serif" },
  { value: "mono", label: "Technical Mono", stack: "ui-monospace, monospace" },
] as const;

const INDUSTRIES = [
  "Restaurant & Food",
  "Corporate & Enterprise",
  "Creative Agency",
  "Personal Brand",
  "Retail & Fashion",
  "Hospitality",
  "Other",
];

const COLOR_SWATCHES = ["#5170ff", "#c9a463", "#3ecf8e", "#f2b84b", "#e05252", "#8f9bb3"];

interface BrandState {
  businessName: string;
  industry: string;
  primaryColor: string;
  secondaryColor: string;
  logoColor: string;
  font: (typeof FONTS)[number]["value"];
  phone: string;
  email: string;
  address: string;
  hours: string;
  headline: string;
  description: string;
  domain: string;
}

function initialState(site: MySite): BrandState {
  return {
    businessName: site.name,
    industry: "Restaurant & Food",
    primaryColor: "#5170ff",
    secondaryColor: "#c9a463",
    logoColor: "#5170ff",
    font: "sans",
    phone: "(555) 010-2947",
    email: `hello@${(site.domain ?? site.name.toLowerCase().replace(/[^a-z0-9]+/g, "")) || "yourbusiness"}.com`,
    address: "148 Market Street, Suite 4, Austin, TX",
    hours: "Mon–Sat, 9am – 9pm",
    headline: `${site.name} — built to grow with you.`,
    description:
      "A modern, trustworthy digital presence that turns visitors into customers from the very first click.",
    domain: site.domain ?? "",
  };
}

type Tab = "quick" | "full";

export function BrandStudio({ site, themeName }: { site: MySite; themeName: string }) {
  const [tab, setTab] = useState<Tab>("quick");
  const [brand, setBrand] = useState<BrandState>(() => initialState(site));

  function set<K extends keyof BrandState>(key: K, value: BrandState[K]) {
    setBrand((prev) => ({ ...prev, [key]: value }));
    // Real implementation: PATCH /api/sites/{siteId}/brand with the updated field.
  }

  const fontStack = useMemo(() => FONTS.find((f) => f.value === brand.font)?.stack ?? "sans-serif", [
    brand.font,
  ]);

  return (
    <div className="flex flex-col gap-8">
      <PageHeader
        eyebrow={`Brand Studio · ${themeName}`}
        title={`Customize ${site.name}`}
        description="Every change updates the live preview instantly. Nothing is saved to your real site until you continue to launch."
        actions={
          <>
            <Link
              href="/services/website-development"
              className="inline-flex items-center gap-1.5 rounded-full border border-[var(--color-border-strong)] px-4 py-2.5 text-[13px] font-medium text-[var(--color-ink)] transition-colors hover:border-[var(--color-ink)]"
            >
              Need help? Customize My Theme
              <ArrowRight className="size-3.5" strokeWidth={2} />
            </Link>
            <Link
              href={`/dashboard/launch/${site.id}`}
              className="inline-flex items-center gap-1.5 rounded-full bg-[var(--color-ink)] px-4 py-2.5 text-[13px] font-medium text-[var(--color-bg)] transition-colors hover:bg-white"
            >
              Continue to Launch
              <ArrowRight className="size-3.5" strokeWidth={2} />
            </Link>
          </>
        }
      />

      <div className="grid gap-6 lg:grid-cols-[minmax(0,420px)_1fr] lg:items-start">
        {/* EDITOR */}
        <Card className="flex flex-col gap-6">
          <div className="flex items-center gap-1 rounded-full border border-[var(--color-border)] bg-white/[0.03] p-1">
            {(["quick", "full"] as Tab[]).map((t) => (
              <button
                key={t}
                onClick={() => setTab(t)}
                className={cn(
                  "flex-1 rounded-full px-3 py-2 text-[12.5px] font-medium transition-colors",
                  tab === t
                    ? "bg-[var(--color-ink)] text-[var(--color-bg)]"
                    : "text-[var(--color-ink-muted)] hover:text-[var(--color-ink)]",
                )}
              >
                {t === "quick" ? "Quick Brand Setup" : "Full Editor"}
              </button>
            ))}
          </div>

          {tab === "quick" ? (
            <div className="flex flex-col gap-5">
              <Field label="Business name">
                <TextInput
                  value={brand.businessName}
                  onChange={(v) => set("businessName", v)}
                  placeholder="Your business name"
                />
              </Field>
              <Field label="Industry">
                <Select
                  value={brand.industry}
                  onChange={(v) => set("industry", v)}
                  options={INDUSTRIES}
                />
              </Field>
              <Field label="Logo color">
                <ColorSwatchPicker value={brand.logoColor} onChange={(v) => set("logoColor", v)} />
              </Field>
              <Field label="Primary color">
                <ColorSwatchPicker value={brand.primaryColor} onChange={(v) => set("primaryColor", v)} />
              </Field>
              <Field label="Phone">
                <TextInput value={brand.phone} onChange={(v) => set("phone", v)} placeholder="(555) 000-0000" />
              </Field>
              <Field label="Email">
                <TextInput
                  value={brand.email}
                  onChange={(v) => set("email", v)}
                  placeholder="hello@yourbusiness.com"
                  type="email"
                />
              </Field>
              <Field label="Domain">
                <TextInput
                  value={brand.domain}
                  onChange={(v) => set("domain", v)}
                  placeholder="yourbusiness.com"
                />
              </Field>
            </div>
          ) : (
            <div className="flex flex-col gap-7">
              <Group title="Brand">
                <Field label="Business name">
                  <TextInput value={brand.businessName} onChange={(v) => set("businessName", v)} />
                </Field>
                <Field label="Primary color">
                  <ColorSwatchPicker value={brand.primaryColor} onChange={(v) => set("primaryColor", v)} />
                </Field>
                <Field label="Secondary color">
                  <ColorSwatchPicker value={brand.secondaryColor} onChange={(v) => set("secondaryColor", v)} />
                </Field>
                <Field label="Font style">
                  <Select
                    value={brand.font}
                    onChange={(v) => set("font", v as BrandState["font"])}
                    options={FONTS.map((f) => f.value)}
                    labels={Object.fromEntries(FONTS.map((f) => [f.value, f.label]))}
                  />
                </Field>
              </Group>

              <Group title="Business">
                <Field label="Phone">
                  <TextInput value={brand.phone} onChange={(v) => set("phone", v)} />
                </Field>
                <Field label="Email">
                  <TextInput value={brand.email} onChange={(v) => set("email", v)} type="email" />
                </Field>
                <Field label="Address">
                  <TextInput value={brand.address} onChange={(v) => set("address", v)} />
                </Field>
                <Field label="Hours">
                  <TextInput value={brand.hours} onChange={(v) => set("hours", v)} />
                </Field>
              </Group>

              <Group title="Content">
                <Field label="Headline">
                  <TextInput value={brand.headline} onChange={(v) => set("headline", v)} />
                </Field>
                <Field label="Short description">
                  <TextArea value={brand.description} onChange={(v) => set("description", v)} />
                </Field>
              </Group>
            </div>
          )}
        </Card>

        {/* LIVE PREVIEW */}
        <div className="lg:sticky lg:top-8">
          <div className="mb-3 flex items-center gap-2 text-[12px] font-mono-label uppercase text-[var(--color-ink-faint)]">
            <Sparkles className="size-3.5 text-[var(--color-accent)]" strokeWidth={1.75} />
            Live Preview
          </div>
          <div
            className="overflow-hidden rounded-2xl border border-[var(--color-border-strong)] shadow-[0_20px_60px_rgba(0,0,0,0.4)]"
            style={{ fontFamily: fontStack }}
          >
            {/* browser chrome */}
            <div className="flex items-center gap-1.5 border-b border-white/10 bg-black/40 px-4 py-2.5">
              <span className="size-2 rounded-full bg-white/25" />
              <span className="size-2 rounded-full bg-white/25" />
              <span className="size-2 rounded-full bg-white/25" />
              <span className="ml-3 truncate rounded-full bg-white/10 px-3 py-1 text-[11px] text-white/50">
                {brand.domain || "yourbusiness.com"}
              </span>
            </div>

            <div style={{ background: "#0c0d10" }}>
              {/* nav */}
              <div
                className="flex items-center justify-between px-6 py-4"
                style={{ borderBottom: "1px solid rgba(255,255,255,0.08)" }}
              >
                <div className="flex items-center gap-2.5">
                  <span
                    className="flex size-7 items-center justify-center rounded-full text-[11px] font-semibold text-white"
                    style={{ background: brand.logoColor }}
                  >
                    {brand.businessName.charAt(0) || "T"}
                  </span>
                  <span className="text-[14px] font-medium text-white">{brand.businessName || "Your Business"}</span>
                </div>
                <span
                  className="rounded-full px-4 py-1.5 text-[12px] font-medium text-white"
                  style={{ background: brand.primaryColor }}
                >
                  Get Started
                </span>
              </div>

              {/* hero */}
              <div className="px-6 py-14 sm:py-20" style={{ background: `linear-gradient(160deg, ${brand.primaryColor}33, transparent 60%)` }}>
                <p
                  className="text-[11px] font-medium uppercase tracking-[0.14em]"
                  style={{ color: brand.secondaryColor }}
                >
                  {brand.industry}
                </p>
                <h2 className="mt-3 max-w-[420px] text-[26px] font-medium leading-tight text-white sm:text-[32px]">
                  {brand.headline || "Your headline goes here"}
                </h2>
                <p className="mt-4 max-w-[380px] text-[13.5px] leading-relaxed text-white/60">
                  {brand.description || "A short description of your business goes here."}
                </p>
                <span
                  className="mt-6 inline-block rounded-full px-5 py-2.5 text-[13px] font-medium text-white"
                  style={{ background: brand.primaryColor }}
                >
                  Learn More
                </span>
              </div>

              {/* contact strip */}
              <div className="grid grid-cols-1 gap-3 border-t border-white/10 px-6 py-6 sm:grid-cols-2">
                <div className="flex items-center gap-2.5 text-[12.5px] text-white/60">
                  <Phone className="size-3.5" strokeWidth={1.75} style={{ color: brand.secondaryColor }} />
                  {brand.phone || "(000) 000-0000"}
                </div>
                <div className="flex items-center gap-2.5 text-[12.5px] text-white/60">
                  <Mail className="size-3.5" strokeWidth={1.75} style={{ color: brand.secondaryColor }} />
                  {brand.email || "hello@yourbusiness.com"}
                </div>
                <div className="flex items-center gap-2.5 text-[12.5px] text-white/60">
                  <MapPin className="size-3.5" strokeWidth={1.75} style={{ color: brand.secondaryColor }} />
                  {brand.address || "Your business address"}
                </div>
                <div className="flex items-center gap-2.5 text-[12.5px] text-white/60">
                  <Clock className="size-3.5" strokeWidth={1.75} style={{ color: brand.secondaryColor }} />
                  {brand.hours || "Business hours"}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function Group({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-4">
      <h3 className="font-mono-label text-[11px] uppercase text-[var(--color-ink-faint)]">{title}</h3>
      {children}
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="flex flex-col gap-1.5">
      <span className="text-[12.5px] font-medium text-[var(--color-ink-muted)]">{label}</span>
      {children}
    </label>
  );
}

function TextInput({
  value,
  onChange,
  placeholder,
  type = "text",
}: {
  value: string;
  onChange: (v: string) => void;
  placeholder?: string;
  type?: string;
}) {
  return (
    <input
      type={type}
      value={value}
      onChange={(e) => onChange(e.target.value)}
      placeholder={placeholder}
      className="w-full rounded-lg border border-[var(--color-border-strong)] bg-white/[0.03] px-3.5 py-2.5 text-[13.5px] text-[var(--color-ink)] outline-none transition-colors placeholder:text-[var(--color-ink-faint)] focus:border-[var(--color-accent)]"
    />
  );
}

function TextArea({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  return (
    <textarea
      value={value}
      onChange={(e) => onChange(e.target.value)}
      rows={3}
      className="w-full resize-none rounded-lg border border-[var(--color-border-strong)] bg-white/[0.03] px-3.5 py-2.5 text-[13.5px] leading-relaxed text-[var(--color-ink)] outline-none transition-colors focus:border-[var(--color-accent)]"
    />
  );
}

function Select({
  value,
  onChange,
  options,
  labels,
}: {
  value: string;
  onChange: (v: string) => void;
  options: string[];
  labels?: Record<string, string>;
}) {
  return (
    <select
      value={value}
      onChange={(e) => onChange(e.target.value)}
      className="w-full rounded-lg border border-[var(--color-border-strong)] bg-white/[0.03] px-3.5 py-2.5 text-[13.5px] text-[var(--color-ink)] outline-none transition-colors focus:border-[var(--color-accent)]"
    >
      {options.map((opt) => (
        <option key={opt} value={opt} className="bg-[var(--color-surface)]">
          {labels?.[opt] ?? opt}
        </option>
      ))}
    </select>
  );
}

function ColorSwatchPicker({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  return (
    <div className="flex flex-wrap items-center gap-2">
      {COLOR_SWATCHES.map((c) => (
        <button
          key={c}
          type="button"
          aria-label={`Choose color ${c}`}
          onClick={() => onChange(c)}
          className={cn(
            "size-7 rounded-full border-2 transition-transform",
            value === c ? "scale-110 border-white" : "border-transparent hover:scale-105",
          )}
          style={{ background: c }}
        />
      ))}
      <input
        type="color"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        aria-label="Custom color"
        className="size-7 cursor-pointer rounded-full border border-[var(--color-border-strong)] bg-transparent p-0"
      />
    </div>
  );
}
