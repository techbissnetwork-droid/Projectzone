"use client";

import { useState, type FormEvent } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { CheckCircle2, Loader2, Lock } from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import type { Product } from "@/lib/data/marketplace";

type Errors = Partial<Record<"name" | "cardNumber" | "expiry" | "cvc", string>>;

function formatCardNumber(value: string) {
  const digits = value.replace(/\D/g, "").slice(0, 16);
  return digits.replace(/(.{4})/g, "$1 ").trim();
}

function formatExpiry(value: string) {
  const digits = value.replace(/\D/g, "").slice(0, 4);
  if (digits.length <= 2) return digits;
  return `${digits.slice(0, 2)}/${digits.slice(2)}`;
}

export function CheckoutForm({ product }: { product: Product }) {
  const [name, setName] = useState("");
  const [cardNumber, setCardNumber] = useState("");
  const [expiry, setExpiry] = useState("");
  const [cvc, setCvc] = useState("");
  const [errors, setErrors] = useState<Errors>({});
  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState(false);

  function validate(): Errors {
    const next: Errors = {};
    if (name.trim().length < 2) next.name = "Enter the name on the card.";

    const digits = cardNumber.replace(/\D/g, "");
    if (digits.length !== 16) next.cardNumber = "Enter a 16-digit card number.";

    const expMatch = expiry.match(/^(\d{2})\/(\d{2})$/);
    if (!expMatch) {
      next.expiry = "Use MM/YY format.";
    } else {
      const month = Number(expMatch[1]);
      const year = 2000 + Number(expMatch[2]);
      const now = new Date();
      const expDate = new Date(year, month);
      if (month < 1 || month > 12) next.expiry = "Enter a valid month.";
      else if (expDate < now) next.expiry = "Card has expired.";
    }

    if (!/^\d{3,4}$/.test(cvc)) next.cvc = "Enter a valid CVC.";

    return next;
  }

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    const next = validate();
    setErrors(next);
    if (Object.keys(next).length > 0) return;

    setSubmitting(true);
    // NOTE: this is a simulated checkout for demonstration purposes only.
    // A real integration would create a PaymentIntent / charge via a gateway
    // such as Stripe here, server-side, before confirming the order.
    window.setTimeout(() => {
      setSubmitting(false);
      setSuccess(true);
    }, 1400);
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
        <h2 className="mt-6 text-[26px] font-medium tracking-[-0.01em]">Purchase Complete</h2>
        <p className="mt-3 max-w-[46ch] text-[14.5px] leading-relaxed text-[var(--color-ink-muted)]">
          {product.name} has been added to your account. You can start customizing it in
          Brand Studio right away, or find it later in My Products.
        </p>
        <div className="mt-8 flex flex-col gap-3 sm:flex-row">
          <Button href="/dashboard/products" variant="secondary">
            View in My Products
          </Button>
          <Button href="/dashboard/brand-studio/new" variant="primary">
            Start Customizing
          </Button>
        </div>
      </motion.div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="rounded-3xl border border-[var(--color-border-strong)] bg-[var(--color-surface)] p-6 sm:p-8">
      <h2 className="text-[18px] font-medium tracking-[-0.01em]">Payment Details</h2>
      <p className="mt-1.5 text-[13px] text-[var(--color-ink-faint)]">
        This is a simulated payment form for demonstration — no real payment is processed.
      </p>

      <div className="mt-7 flex flex-col gap-5">
        <Field label="Cardholder Name" error={errors.name}>
          <input
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Jordan Avery"
            className={inputClass(!!errors.name)}
          />
        </Field>

        <Field label="Card Number" error={errors.cardNumber}>
          <input
            type="text"
            inputMode="numeric"
            value={cardNumber}
            onChange={(e) => setCardNumber(formatCardNumber(e.target.value))}
            placeholder="4242 4242 4242 4242"
            className={inputClass(!!errors.cardNumber)}
          />
        </Field>

        <div className="grid grid-cols-2 gap-4">
          <Field label="Expiry" error={errors.expiry}>
            <input
              type="text"
              inputMode="numeric"
              value={expiry}
              onChange={(e) => setExpiry(formatExpiry(e.target.value))}
              placeholder="MM/YY"
              className={inputClass(!!errors.expiry)}
            />
          </Field>
          <Field label="CVC" error={errors.cvc}>
            <input
              type="text"
              inputMode="numeric"
              value={cvc}
              onChange={(e) => setCvc(e.target.value.replace(/\D/g, "").slice(0, 4))}
              placeholder="123"
              className={inputClass(!!errors.cvc)}
            />
          </Field>
        </div>
      </div>

      <Button
        type="submit"
        variant="primary"
        className="mt-8 w-full justify-center"
        icon={false}
        disabled={submitting}
      >
        <AnimatePresence mode="wait" initial={false}>
          {submitting ? (
            <motion.span
              key="loading"
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              className="inline-flex items-center gap-2"
            >
              <Loader2 className="size-4 animate-spin" />
              Processing…
            </motion.span>
          ) : (
            <motion.span key="idle" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
              Complete Purchase — {product.free ? "Free" : `$${product.price}`}
            </motion.span>
          )}
        </AnimatePresence>
      </Button>

      <p className="mt-4 flex items-center justify-center gap-1.5 text-[12px] text-[var(--color-ink-faint)]">
        <Lock className="size-3" />
        Secure simulated checkout
      </p>
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
