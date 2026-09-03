"use client";

import { useState, type FormEvent } from "react";
import { motion, AnimatePresence } from "motion/react";
import { CheckCircle2, Loader2, TriangleAlert } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { solutions } from "@/lib/data/solutions";
import { cn } from "@/lib/utils/cn";

const budgets = [
  "Under $5,000",
  "$5,000 – $15,000",
  "$15,000 – $50,000",
  "$50,000+",
  "Not sure yet",
];

type Status = "idle" | "submitting" | "success" | "error";

const inputClass =
  "w-full rounded-lg border border-line-strong bg-ink-raised-2 px-4 py-3 text-[0.95rem] text-paper placeholder:text-paper-faint transition-colors duration-300 focus:border-gold focus:outline-none";

const labelClass = "text-eyebrow text-paper-faint";

export function ContactForm() {
  const [status, setStatus] = useState<Status>("idle");
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  async function handleSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setStatus("submitting");
    setErrorMessage(null);
    setFieldErrors({});

    const form = e.currentTarget;
    const data = Object.fromEntries(new FormData(form).entries());

    try {
      const res = await fetch("/api/contact", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });
      const json = await res.json();

      if (!res.ok || !json.ok) {
        setStatus("error");
        setErrorMessage(json.message ?? "Something went wrong. Please try again.");
        setFieldErrors(json.fieldErrors ?? {});
        return;
      }

      setStatus("success");
      form.reset();
    } catch {
      setStatus("error");
      setErrorMessage("Network error — please check your connection and try again.");
    }
  }

  if (status === "success") {
    return (
      <motion.div
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        className="flex flex-col items-center gap-4 rounded-2xl border border-gold/30 bg-gold-dim px-8 py-16 text-center"
      >
        <CheckCircle2 className="size-10 text-gold-bright" aria-hidden />
        <h3 className="text-h3 font-medium text-paper">Message received.</h3>
        <p className="max-w-sm text-[0.95rem] text-paper-dim">
          Thanks for reaching out. A member of the TECHBISS team will get back
          to you within one business day.
        </p>
        <Button variant="secondary" onClick={() => setStatus("idle")}>
          Send Another Message
        </Button>
      </motion.div>
    );
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-6">
      <input
        type="text"
        name="website"
        tabIndex={-1}
        autoComplete="off"
        className="hidden"
        aria-hidden="true"
      />

      <div className="grid gap-6 sm:grid-cols-2">
        <div className="flex flex-col gap-2">
          <label htmlFor="name" className={labelClass}>
            Full Name
          </label>
          <input id="name" name="name" type="text" required className={inputClass} placeholder="Jordan Lee" />
          {fieldErrors.name && (
            <p className="text-xs text-danger">{fieldErrors.name[0]}</p>
          )}
        </div>
        <div className="flex flex-col gap-2">
          <label htmlFor="email" className={labelClass}>
            Email
          </label>
          <input
            id="email"
            name="email"
            type="email"
            required
            className={inputClass}
            placeholder="jordan@business.com"
          />
          {fieldErrors.email && (
            <p className="text-xs text-danger">{fieldErrors.email[0]}</p>
          )}
        </div>
      </div>

      <div className="grid gap-6 sm:grid-cols-2">
        <div className="flex flex-col gap-2">
          <label htmlFor="company" className={labelClass}>
            Business Name
          </label>
          <input id="company" name="company" type="text" className={inputClass} placeholder="Your business" />
        </div>
        <div className="flex flex-col gap-2">
          <label htmlFor="businessType" className={labelClass}>
            Business Type
          </label>
          <select id="businessType" name="businessType" className={cn(inputClass, "appearance-none")} defaultValue="">
            <option value="" disabled>
              Select one
            </option>
            {solutions.map((s) => (
              <option key={s.slug} value={s.business}>
                {s.business}
              </option>
            ))}
            <option value="Other">Other</option>
          </select>
        </div>
      </div>

      <div className="flex flex-col gap-2">
        <label htmlFor="budget" className={labelClass}>
          Estimated Budget
        </label>
        <select id="budget" name="budget" className={cn(inputClass, "appearance-none")} defaultValue="">
          <option value="" disabled>
            Select a range
          </option>
          {budgets.map((b) => (
            <option key={b} value={b}>
              {b}
            </option>
          ))}
        </select>
      </div>

      <div className="flex flex-col gap-2">
        <label htmlFor="message" className={labelClass}>
          Tell Us About Your Project
        </label>
        <textarea
          id="message"
          name="message"
          required
          rows={5}
          className={cn(inputClass, "resize-none")}
          placeholder="What does your business do today, and where do you want your digital presence to be?"
        />
        {fieldErrors.message && (
          <p className="text-xs text-danger">{fieldErrors.message[0]}</p>
        )}
      </div>

      <AnimatePresence>
        {status === "error" && errorMessage && (
          <motion.div
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: "auto" }}
            exit={{ opacity: 0, height: 0 }}
            className="flex items-center gap-2.5 overflow-hidden rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger"
          >
            <TriangleAlert className="size-4 shrink-0" aria-hidden />
            {errorMessage}
          </motion.div>
        )}
      </AnimatePresence>

      <Button
        type="submit"
        size="lg"
        icon={false}
        className="w-full sm:w-fit"
        disabled={status === "submitting"}
      >
        {status === "submitting" ? (
          <span className="flex items-center gap-2">
            <Loader2 className="size-4 animate-spin" aria-hidden />
            Sending...
          </span>
        ) : (
          "Send Message"
        )}
      </Button>
    </form>
  );
}
