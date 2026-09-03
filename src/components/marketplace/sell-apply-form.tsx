"use client";

import { useState, type FormEvent } from "react";
import { motion } from "framer-motion";
import { CheckCircle2, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const PRODUCT_TYPES = [
  "Website Theme",
  "App Template",
  "UI Kit",
  "Admin Dashboard",
  "SaaS Template",
  "Digital System",
  "Component Library",
];

type Errors = Partial<Record<"name" | "email" | "portfolio" | "productType" | "message", string>>;

export function SellApplyForm() {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [portfolio, setPortfolio] = useState("");
  const [productType, setProductType] = useState("");
  const [message, setMessage] = useState("");
  const [errors, setErrors] = useState<Errors>({});
  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState(false);

  function validate(): Errors {
    const next: Errors = {};
    if (name.trim().length < 2) next.name = "Enter your full name.";
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) next.email = "Enter a valid email address.";
    if (!/^https?:\/\/.+/.test(portfolio.trim())) next.portfolio = "Enter a full URL, starting with https://.";
    if (!productType) next.productType = "Select a product type.";
    if (message.trim().length < 20) next.message = "Tell us a little more (20+ characters).";
    return next;
  }

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    const next = validate();
    setErrors(next);
    if (Object.keys(next).length > 0) return;

    setSubmitting(true);
    window.setTimeout(() => {
      setSubmitting(false);
      setSuccess(true);
    }, 1200);
  }

  if (success) {
    return (
      <motion.div
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5, ease: [0.16, 1, 0.3, 1] }}
        className="flex flex-col items-center rounded-3xl border border-[var(--color-border-strong)] bg-[var(--color-surface)] px-8 py-16 text-center"
      >
        <div className="flex size-14 items-center justify-center rounded-full bg-[var(--color-live-soft)]">
          <CheckCircle2 className="size-7 text-[var(--color-live)]" strokeWidth={1.75} />
        </div>
        <h2 className="mt-6 text-[24px] font-medium tracking-[-0.01em]">Application Received</h2>
        <p className="mt-3 max-w-[48ch] text-[14.5px] leading-relaxed text-[var(--color-ink-muted)]">
          Thanks, {name.split(" ")[0]}. Our creator team reviews every submission and typically
          responds within 5 business days at {email}.
        </p>
      </motion.div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="rounded-3xl border border-[var(--color-border-strong)] bg-[var(--color-surface)] p-6 sm:p-8">
      <div className="grid gap-5 sm:grid-cols-2">
        <Field label="Full Name" error={errors.name}>
          <input
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Jordan Avery"
            className={inputClass(!!errors.name)}
          />
        </Field>
        <Field label="Email" error={errors.email}>
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="you@studio.com"
            className={inputClass(!!errors.email)}
          />
        </Field>
        <Field label="Portfolio Link" error={errors.portfolio}>
          <input
            type="text"
            value={portfolio}
            onChange={(e) => setPortfolio(e.target.value)}
            placeholder="https://your-portfolio.com"
            className={inputClass(!!errors.portfolio)}
          />
        </Field>
        <Field label="Product Type" error={errors.productType}>
          <select
            value={productType}
            onChange={(e) => setProductType(e.target.value)}
            className={cn(inputClass(!!errors.productType), "appearance-none")}
          >
            <option value="" className="bg-[var(--color-surface)]">
              Select a type…
            </option>
            {PRODUCT_TYPES.map((t) => (
              <option key={t} value={t} className="bg-[var(--color-surface)]">
                {t}
              </option>
            ))}
          </select>
        </Field>
      </div>

      <div className="mt-5">
        <Field label="Tell us about what you'd like to sell" error={errors.message}>
          <textarea
            value={message}
            onChange={(e) => setMessage(e.target.value)}
            rows={4}
            placeholder="A few sentences about your product, experience and who it's built for…"
            className={cn(inputClass(!!errors.message), "resize-none")}
          />
        </Field>
      </div>

      <Button type="submit" variant="primary" className="mt-7 w-full justify-center" icon={false} disabled={submitting}>
        {submitting ? (
          <span className="inline-flex items-center gap-2">
            <Loader2 className="size-4 animate-spin" />
            Submitting…
          </span>
        ) : (
          "Apply to Sell"
        )}
      </Button>
    </form>
  );
}

function Field({
  label,
  error,
  children,
}: {
  label: string;
  error?: string;
  children: React.ReactNode;
}) {
  return (
    <label className="flex flex-col gap-2">
      <span className="text-[12.5px] font-medium text-[var(--color-ink-muted)]">{label}</span>
      {children}
      {error && <span className="text-[12px] text-[#ff8a8a]">{error}</span>}
    </label>
  );
}

function inputClass(hasError: boolean) {
  return cn(
    "w-full rounded-xl border bg-[var(--color-bg-soft)] px-4 py-3 text-[14px] text-[var(--color-ink)] outline-none transition-colors placeholder:text-[var(--color-ink-faint)] focus-visible:border-[var(--color-accent)]",
    hasError ? "border-[#ff8a8a]/60" : "border-[var(--color-border-strong)]",
  );
}
