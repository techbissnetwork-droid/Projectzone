"use client";

import { useState, FormEvent } from "react";
import { Check, Loader2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";

const projectTypes = [
  "Build something custom",
  "Buy a ready-made product",
  "Customize a purchased product",
  "Not sure yet",
];

function Field({
  label,
  children,
}: {
  label: string;
  children: React.ReactNode;
}) {
  return (
    <label className="flex flex-col gap-2">
      <span className="text-[13px] font-medium text-paper-50/70">{label}</span>
      {children}
    </label>
  );
}

const inputClass =
  "w-full rounded-lg border border-line-dark-strong bg-ink-950/60 px-4 py-3 text-[14px] text-paper-50 placeholder:text-paper-50/30 outline-none transition-colors focus:border-gold-500/60";

export function ContactForm() {
  const [status, setStatus] = useState<"idle" | "submitting" | "sent">("idle");
  const [projectType, setProjectType] = useState(projectTypes[0]);

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setStatus("submitting");
    setTimeout(() => setStatus("sent"), 900);
  }

  if (status === "sent") {
    return (
      <div className="flex flex-col items-start gap-4 rounded-2xl border border-gold-500/25 bg-gradient-to-br from-ink-850 to-ink-900 p-10">
        <span className="flex size-12 items-center justify-center rounded-full bg-gold-400 text-ink-950">
          <Check className="size-6" strokeWidth={2.5} />
        </span>
        <h3 className="text-[22px] font-medium text-paper-50">Message received.</h3>
        <p className="max-w-sm text-[14px] leading-relaxed text-paper-50/55">
          A member of the TECHBISS team will respond within one business day
          to talk through your project.
        </p>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-6">
      <div className="grid gap-5 sm:grid-cols-2">
        <Field label="Full name">
          <input required className={inputClass} placeholder="Jordan Lee" />
        </Field>
        <Field label="Work email">
          <input required type="email" className={inputClass} placeholder="jordan@business.com" />
        </Field>
      </div>

      <Field label="Business name">
        <input className={inputClass} placeholder="Your business" />
      </Field>

      <div className="flex flex-col gap-2">
        <span className="text-[13px] font-medium text-paper-50/70">What are you looking to do?</span>
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
          {projectTypes.map((type) => (
            <button
              key={type}
              type="button"
              onClick={() => setProjectType(type)}
              className={cn(
                "rounded-lg border px-3 py-2.5 text-left text-[12.5px] font-medium transition-colors",
                projectType === type
                  ? "border-gold-500/50 bg-gold-500/10 text-gold-300"
                  : "border-line-dark bg-ink-950/40 text-paper-50/60 hover:border-line-dark-strong"
              )}
            >
              {type}
            </button>
          ))}
        </div>
      </div>

      <Field label="Tell us about your project">
        <textarea
          required
          rows={5}
          className={cn(inputClass, "resize-none")}
          placeholder="What does your business do, and what are you hoping to launch or improve?"
        />
      </Field>

      <Button type="submit" size="lg" className="w-full sm:w-fit">
        {status === "submitting" ? (
          <>
            <Loader2 className="size-4 animate-spin" />
            Sending
          </>
        ) : (
          "Send Message"
        )}
      </Button>
    </form>
  );
}
