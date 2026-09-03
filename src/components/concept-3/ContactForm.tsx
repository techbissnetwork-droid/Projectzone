"use client";

import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { CheckCircle2, Send } from "lucide-react";
import { services } from "@/lib/site-data";
import { cn } from "@/lib/cn";

const inputClass =
  "w-full min-h-[44px] rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3 text-sm text-white placeholder:text-slate-500 transition-all focus:border-violet-400/50 focus:bg-white/[0.06] focus:outline-none focus:ring-2 focus:ring-violet-400/40";

const labelClass = "mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-400";

export function ContactForm() {
  const [submitted, setSubmitted] = useState(false);

  function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setSubmitted(true);
  }

  return (
    <div className="relative rounded-2xl border border-white/10 bg-white/[0.03] p-6 shadow-xl shadow-black/20 sm:p-8">
      <AnimatePresence mode="wait">
        {submitted ? (
          <motion.div
            key="success"
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0 }}
            className="flex flex-col items-center gap-4 py-10 text-center"
          >
            <span className="flex h-14 w-14 items-center justify-center rounded-full bg-emerald-400/15 text-emerald-300">
              <CheckCircle2 className="h-7 w-7" aria-hidden="true" />
            </span>
            <h3 className="font-display text-xl font-semibold text-white">Message received</h3>
            <p className="max-w-sm text-sm text-slate-400">
              Thanks for reaching out. A member of the TECHBISS team will get back to you at the email you
              provided within [Add response time].
            </p>
            <button
              type="button"
              onClick={() => setSubmitted(false)}
              className="mt-2 text-sm font-semibold text-violet-300 hover:text-violet-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 rounded"
            >
              Send another message
            </button>
          </motion.div>
        ) : (
          <motion.form
            key="form"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onSubmit={handleSubmit}
            className="flex flex-col gap-5"
            noValidate
          >
            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
              <div>
                <label htmlFor="contact-name" className={labelClass}>
                  Full name
                </label>
                <input id="contact-name" name="name" type="text" required className={inputClass} placeholder="Jordan Lee" />
              </div>
              <div>
                <label htmlFor="contact-email" className={labelClass}>
                  Email
                </label>
                <input id="contact-email" name="email" type="email" required className={inputClass} placeholder="you@company.com" />
              </div>
            </div>

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
              <div>
                <label htmlFor="contact-company" className={labelClass}>
                  Company
                </label>
                <input id="contact-company" name="company" type="text" className={inputClass} placeholder="Your business name" />
              </div>
              <div>
                <label htmlFor="contact-service" className={labelClass}>
                  Service interest
                </label>
                <select id="contact-service" name="service" defaultValue="" className={cn(inputClass, "appearance-none")}>
                  <option value="" disabled>
                    Select a service
                  </option>
                  {services.map((s) => (
                    <option key={s.slug} value={s.title} className="bg-[#0b0c14]">
                      {s.title}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            <div>
              <label htmlFor="contact-message" className={labelClass}>
                Message
              </label>
              <textarea
                id="contact-message"
                name="message"
                required
                rows={5}
                className={cn(inputClass, "resize-none")}
                placeholder="Tell us a bit about what you're trying to build..."
              />
            </div>

            <button
              type="submit"
              className="group inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-violet-500 via-fuchsia-500 to-blue-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-violet-500/25 transition-all hover:-translate-y-0.5 hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 focus-visible:ring-offset-2 focus-visible:ring-offset-[#0b0c14]"
            >
              <Send className="h-4 w-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
              Send Message
            </button>
          </motion.form>
        )}
      </AnimatePresence>
    </div>
  );
}
