"use client";

import { useState, type FormEvent } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { CheckCircle2, Loader2 } from "lucide-react";
import { cn } from "@/lib/utils";

const PROJECT_TYPES = ["Custom Build", "Marketplace Theme", "Both", "Not Sure"] as const;
const BUDGET_BANDS = [
  "Under $5,000",
  "$5,000 – $15,000",
  "$15,000 – $50,000",
  "$50,000+",
  "Not sure yet",
] as const;

interface FormState {
  name: string;
  email: string;
  company: string;
  projectType: (typeof PROJECT_TYPES)[number] | "";
  budget: (typeof BUDGET_BANDS)[number] | "";
  message: string;
}

const INITIAL_STATE: FormState = {
  name: "",
  email: "",
  company: "",
  projectType: "",
  budget: "",
  message: "",
};

type Errors = Partial<Record<keyof FormState, string>>;
type Status = "idle" | "submitting" | "success";

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function validate(state: FormState): Errors {
  const errors: Errors = {};
  if (!state.name.trim()) errors.name = "Please tell us your name.";
  if (!state.email.trim()) {
    errors.email = "Please enter your email.";
  } else if (!EMAIL_RE.test(state.email.trim())) {
    errors.email = "That email address doesn't look right.";
  }
  if (!state.projectType) errors.projectType = "Choose the closest fit.";
  if (!state.budget) errors.budget = "Select a rough budget range.";
  if (!state.message.trim()) {
    errors.message = "Tell us a little about the project.";
  } else if (state.message.trim().length < 12) {
    errors.message = "A few more details would help us prepare.";
  }
  return errors;
}

// Simulated submission — in production this posts `state` to the intake API.
async function submitInquiry(state: FormState): Promise<{ ok: true; ref: string }> {
  await new Promise((resolve) => setTimeout(resolve, 800));
  const ref = `${state.email.split("@")[0]}-${Date.now().toString(36)}`;
  return { ok: true, ref };
}

const fieldBase =
  "w-full rounded-xl border bg-[var(--color-surface)] px-4 py-3 text-[14.5px] text-[var(--color-ink)] outline-none transition-colors duration-200 placeholder:text-[var(--color-ink-faint)] focus:border-[var(--color-accent)]";

export function ContactForm() {
  const [state, setState] = useState<FormState>(INITIAL_STATE);
  const [errors, setErrors] = useState<Errors>({});
  const [status, setStatus] = useState<Status>("idle");
  const [touched, setTouched] = useState<Partial<Record<keyof FormState, boolean>>>({});

  const setField = <K extends keyof FormState>(key: K, value: FormState[K]) => {
    setState((s) => ({ ...s, [key]: value }));
    if (touched[key]) {
      setErrors(validate({ ...state, [key]: value }));
    }
  };

  const markTouched = (key: keyof FormState) => {
    setTouched((t) => ({ ...t, [key]: true }));
  };

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    const nextErrors = validate(state);
    setErrors(nextErrors);
    setTouched({
      name: true,
      email: true,
      company: true,
      projectType: true,
      budget: true,
      message: true,
    });
    if (Object.keys(nextErrors).length > 0) return;

    setStatus("submitting");
    await submitInquiry(state);
    setStatus("success");
  };

  if (status === "success") {
    return (
      <motion.div
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5, ease: [0.16, 1, 0.3, 1] }}
        className="flex flex-col items-start rounded-2xl border border-[var(--color-border-strong)] bg-[var(--color-surface)] p-8 sm:p-10"
      >
        <span className="flex size-12 items-center justify-center rounded-full bg-[var(--color-live-soft)]">
          <CheckCircle2 className="size-6 text-[var(--color-live)]" strokeWidth={2} />
        </span>
        <h3 className="mt-6 text-[22px] font-medium tracking-[-0.01em]">
          Thanks, {state.name.split(" ")[0]}. We&apos;ve got it.
        </h3>
        <p className="mt-3 max-w-[46ch] text-pretty text-[14.5px] leading-relaxed text-[var(--color-ink-muted)]">
          Your project details are in front of our team. Expect a reply at{" "}
          <span className="text-[var(--color-ink)]">{state.email}</span> within one
          business day with next steps.
        </p>
        <button
          type="button"
          onClick={() => {
            setState(INITIAL_STATE);
            setErrors({});
            setTouched({});
            setStatus("idle");
          }}
          className="mt-7 text-[13px] font-medium text-[var(--color-ink-muted)] underline decoration-[var(--color-border-strong)] underline-offset-4 transition-colors hover:text-[var(--color-ink)]"
        >
          Send another inquiry
        </button>
      </motion.div>
    );
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-6">
      <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <Field label="Name" error={touched.name ? errors.name : undefined}>
          <input
            className={cn(fieldBase, touched.name && errors.name ? "border-red-400/50" : "border-[var(--color-border)]")}
            value={state.name}
            onChange={(e) => setField("name", e.target.value)}
            onBlur={() => markTouched("name")}
            placeholder="Jordan Lee"
            autoComplete="name"
          />
        </Field>
        <Field label="Email" error={touched.email ? errors.email : undefined}>
          <input
            type="email"
            className={cn(fieldBase, touched.email && errors.email ? "border-red-400/50" : "border-[var(--color-border)]")}
            value={state.email}
            onChange={(e) => setField("email", e.target.value)}
            onBlur={() => markTouched("email")}
            placeholder="jordan@company.com"
            autoComplete="email"
          />
        </Field>
      </div>

      <Field label="Company" hint="Optional">
        <input
          className={cn(fieldBase, "border-[var(--color-border)]")}
          value={state.company}
          onChange={(e) => setField("company", e.target.value)}
          placeholder="Your company name"
          autoComplete="organization"
        />
      </Field>

      <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <Field label="Project Type" error={touched.projectType ? errors.projectType : undefined}>
          <select
            className={cn(
              fieldBase,
              "appearance-none",
              touched.projectType && errors.projectType ? "border-red-400/50" : "border-[var(--color-border)]",
              !state.projectType && "text-[var(--color-ink-faint)]",
            )}
            value={state.projectType}
            onChange={(e) => setField("projectType", e.target.value as FormState["projectType"])}
            onBlur={() => markTouched("projectType")}
          >
            <option value="" disabled>
              Select one
            </option>
            {PROJECT_TYPES.map((t) => (
              <option key={t} value={t} className="text-[var(--color-ink)]">
                {t}
              </option>
            ))}
          </select>
        </Field>

        <Field label="Budget Range" error={touched.budget ? errors.budget : undefined}>
          <select
            className={cn(
              fieldBase,
              "appearance-none",
              touched.budget && errors.budget ? "border-red-400/50" : "border-[var(--color-border)]",
              !state.budget && "text-[var(--color-ink-faint)]",
            )}
            value={state.budget}
            onChange={(e) => setField("budget", e.target.value as FormState["budget"])}
            onBlur={() => markTouched("budget")}
          >
            <option value="" disabled>
              Select a range
            </option>
            {BUDGET_BANDS.map((b) => (
              <option key={b} value={b} className="text-[var(--color-ink)]">
                {b}
              </option>
            ))}
          </select>
        </Field>
      </div>

      <Field label="Project Details" error={touched.message ? errors.message : undefined}>
        <textarea
          rows={5}
          className={cn(fieldBase, "resize-none", touched.message && errors.message ? "border-red-400/50" : "border-[var(--color-border)]")}
          value={state.message}
          onChange={(e) => setField("message", e.target.value)}
          onBlur={() => markTouched("message")}
          placeholder="Tell us about your business, what you're trying to build, and any timelines that matter."
        />
      </Field>

      <button
        type="submit"
        disabled={status === "submitting"}
        className="group relative mt-2 inline-flex items-center justify-center gap-2 rounded-full bg-[var(--color-ink)] px-7 py-4 text-[15px] font-medium text-[var(--color-bg)] transition-all duration-300 hover:bg-white disabled:cursor-not-allowed disabled:opacity-70"
      >
        <AnimatePresence mode="wait" initial={false}>
          {status === "submitting" ? (
            <motion.span
              key="submitting"
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              className="inline-flex items-center gap-2"
            >
              <Loader2 className="size-4 animate-spin" />
              Sending your inquiry…
            </motion.span>
          ) : (
            <motion.span key="idle" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
              Send Inquiry
            </motion.span>
          )}
        </AnimatePresence>
      </button>
    </form>
  );
}

function Field({
  label,
  hint,
  error,
  children,
}: {
  label: string;
  hint?: string;
  error?: string;
  children: React.ReactNode;
}) {
  return (
    <label className="flex flex-col gap-2">
      <span className="flex items-baseline justify-between font-mono-label text-[11px] uppercase text-[var(--color-ink-faint)]">
        {label}
        {hint && <span className="normal-case tracking-normal text-[var(--color-ink-faint)]/70">{hint}</span>}
      </span>
      {children}
      <AnimatePresence>
        {error && (
          <motion.span
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: "auto" }}
            exit={{ opacity: 0, height: 0 }}
            className="text-[12.5px] text-red-400"
          >
            {error}
          </motion.span>
        )}
      </AnimatePresence>
    </label>
  );
}
