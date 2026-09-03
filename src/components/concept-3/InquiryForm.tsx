"use client";

import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { CheckCircle2, Rocket, Check } from "lucide-react";
import { services } from "@/lib/site-data";
import { cn } from "@/lib/cn";

const inputClass =
  "w-full min-h-[44px] rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3 text-sm text-white placeholder:text-slate-500 transition-all focus:border-violet-400/50 focus:bg-white/[0.06] focus:outline-none focus:ring-2 focus:ring-violet-400/40";

const labelClass = "mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-400";

const budgetOptions = [
  "Under $5,000",
  "$5,000–$15,000",
  "$15,000–$50,000",
  "$50,000+",
  "Not sure yet",
];

export function InquiryForm() {
  const [submitted, setSubmitted] = useState(false);
  const [selectedServices, setSelectedServices] = useState<string[]>([]);

  function toggleService(title: string) {
    setSelectedServices((prev) => (prev.includes(title) ? prev.filter((t) => t !== title) : [...prev, title]));
  }

  function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setSubmitted(true);
  }

  if (submitted) {
    return (
      <motion.div
        initial={{ opacity: 0, y: 8 }}
        animate={{ opacity: 1, y: 0 }}
        className="flex flex-col items-center gap-4 rounded-2xl border border-white/10 bg-white/[0.03] p-10 text-center shadow-xl shadow-black/20"
      >
        <span className="flex h-14 w-14 items-center justify-center rounded-full bg-emerald-400/15 text-emerald-300">
          <CheckCircle2 className="h-7 w-7" aria-hidden="true" />
        </span>
        <h3 className="font-display text-xl font-semibold text-white">Your project brief is in</h3>
        <p className="max-w-md text-sm text-slate-400">
          We respond within [Add response time — e.g. 2 business days] with next steps and, if it's a fit, a
          time to talk through scope and budget in more detail.
        </p>
        <button
          type="button"
          onClick={() => {
            setSubmitted(false);
            setSelectedServices([]);
          }}
          className="mt-2 text-sm font-semibold text-violet-300 hover:text-violet-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 rounded"
        >
          Submit another inquiry
        </button>
      </motion.div>
    );
  }

  return (
    <form
      onSubmit={handleSubmit}
      noValidate
      className="flex flex-col gap-8 rounded-2xl border border-white/10 bg-white/[0.03] p-6 shadow-xl shadow-black/20 sm:p-8"
    >
      <div>
        <p className="text-xs font-semibold uppercase tracking-widest text-violet-300">Step 1</p>
        <h2 className="font-display mt-1 text-lg font-semibold text-white">Your details</h2>
        <div className="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2">
          <div>
            <label htmlFor="inquiry-name" className={labelClass}>
              Full name
            </label>
            <input id="inquiry-name" name="name" type="text" required className={inputClass} placeholder="Jordan Lee" />
          </div>
          <div>
            <label htmlFor="inquiry-email" className={labelClass}>
              Email
            </label>
            <input id="inquiry-email" name="email" type="email" required className={inputClass} placeholder="you@company.com" />
          </div>
          <div className="sm:col-span-2">
            <label htmlFor="inquiry-company" className={labelClass}>
              Company
            </label>
            <input id="inquiry-company" name="company" type="text" className={inputClass} placeholder="Your business name" />
          </div>
        </div>
      </div>

      <div className="border-t border-white/10 pt-8">
        <p className="text-xs font-semibold uppercase tracking-widest text-violet-300">Step 2</p>
        <h2 className="font-display mt-1 text-lg font-semibold text-white">Project scope</h2>

        <div className="mt-4">
          <label htmlFor="inquiry-budget" className={labelClass}>
            Budget range
          </label>
          <select id="inquiry-budget" name="budget" defaultValue="" className={cn(inputClass, "appearance-none")}>
            <option value="" disabled>
              Select a budget range
            </option>
            {budgetOptions.map((b) => (
              <option key={b} value={b} className="bg-[#0b0c14]">
                {b}
              </option>
            ))}
          </select>
        </div>

        <fieldset className="mt-5">
          <legend className={labelClass}>Services you&apos;re interested in</legend>
          <div className="flex flex-wrap gap-2">
            {services.map((s) => {
              const active = selectedServices.includes(s.title);
              return (
                <button
                  key={s.slug}
                  type="button"
                  aria-pressed={active}
                  onClick={() => toggleService(s.title)}
                  className={cn(
                    "flex min-h-[44px] items-center gap-1.5 rounded-full border px-3.5 py-2 text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400",
                    active
                      ? "border-transparent bg-gradient-to-r from-violet-500 to-blue-500 text-white"
                      : "border-white/15 bg-white/[0.02] text-slate-400 hover:text-white"
                  )}
                >
                  {active ? <Check className="h-3.5 w-3.5" aria-hidden="true" /> : null}
                  {s.title}
                </button>
              );
            })}
          </div>
        </fieldset>

        <div className="mt-5">
          <label htmlFor="inquiry-description" className={labelClass}>
            Project description
          </label>
          <textarea
            id="inquiry-description"
            name="description"
            required
            rows={5}
            className={cn(inputClass, "resize-none")}
            placeholder="What are you trying to build, and what does success look like?"
          />
        </div>
      </div>

      <button
        type="submit"
        className="group inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-violet-500 via-fuchsia-500 to-blue-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-violet-500/25 transition-all hover:-translate-y-0.5 hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 focus-visible:ring-offset-2 focus-visible:ring-offset-[#0b0c14]"
      >
        <Rocket className="h-4 w-4 transition-transform group-hover:-translate-y-0.5" aria-hidden="true" />
        Submit Project Inquiry
      </button>
    </form>
  );
}
