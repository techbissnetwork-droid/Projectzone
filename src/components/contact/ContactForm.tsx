"use client";

import * as React from "react";
import { motion, AnimatePresence } from "framer-motion";
import { CheckCircle2, Loader2, Send } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Input, Label, Select, Textarea } from "@/components/ui/Field";
import { sleep } from "@/lib/utils";

type FormState = {
  name: string;
  email: string;
  company: string;
  budget: string;
  message: string;
};

const initialState: FormState = { name: "", email: "", company: "", budget: "$25k – $50k", message: "" };

export function ContactForm() {
  const [form, setForm] = React.useState<FormState>(initialState);
  const [errors, setErrors] = React.useState<Partial<Record<keyof FormState, string>>>({});
  const [status, setStatus] = React.useState<"idle" | "submitting" | "success">("idle");

  function validate() {
    const next: Partial<Record<keyof FormState, string>> = {};
    if (!form.name.trim()) next.name = "Please enter your name";
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) next.email = "Enter a valid email address";
    if (!form.message.trim() || form.message.trim().length < 10) next.message = "Tell us a bit more (10+ characters)";
    setErrors(next);
    return Object.keys(next).length === 0;
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!validate()) return;
    setStatus("submitting");
    await sleep(1200);
    setStatus("success");
  }

  if (status === "success") {
    return (
      <motion.div
        initial={{ opacity: 0, scale: 0.97 }}
        animate={{ opacity: 1, scale: 1 }}
        transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
        className="flex flex-col items-center rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-10 text-center"
      >
        <div className="flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500/12 text-emerald-400">
          <CheckCircle2 className="h-7 w-7" />
        </div>
        <h3 className="mt-5 text-xl font-medium text-(--color-ink)">Message sent</h3>
        <p className="mt-2 max-w-sm text-sm leading-relaxed text-(--color-ink-muted)">
          Thanks, {form.name.split(" ")[0]}. A member of our team will reply to {form.email} within 48 hours.
        </p>
        <Button
          variant="outline"
          size="lg"
          className="mt-7"
          onClick={() => {
            setForm(initialState);
            setStatus("idle");
          }}
        >
          Send another message
        </Button>
      </motion.div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6 sm:p-8">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <Label htmlFor="name" required>
            Full name
          </Label>
          <Input
            id="name"
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            placeholder="Jordan Blake"
            error={errors.name}
          />
        </div>
        <div>
          <Label htmlFor="email" required>
            Work email
          </Label>
          <Input
            id="email"
            type="email"
            value={form.email}
            onChange={(e) => setForm({ ...form, email: e.target.value })}
            placeholder="you@company.com"
            error={errors.email}
          />
        </div>
      </div>

      <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <Label htmlFor="company">Company</Label>
          <Input
            id="company"
            value={form.company}
            onChange={(e) => setForm({ ...form, company: e.target.value })}
            placeholder="Acme Inc."
          />
        </div>
        <div>
          <Label htmlFor="budget">Estimated budget</Label>
          <Select id="budget" value={form.budget} onChange={(e) => setForm({ ...form, budget: e.target.value })}>
            {["Under $25k", "$25k – $50k", "$50k – $150k", "$150k – $500k", "$500k+"].map((b) => (
              <option key={b} value={b}>
                {b}
              </option>
            ))}
          </Select>
        </div>
      </div>

      <div className="mt-4">
        <Label htmlFor="message" required>
          Tell us about your project
        </Label>
        <Textarea
          id="message"
          value={form.message}
          onChange={(e) => setForm({ ...form, message: e.target.value })}
          placeholder="What are you building, and what does success look like?"
          error={errors.message}
        />
      </div>

      <Button
        type="submit"
        variant="secondary"
        size="lg"
        className="mt-6 w-full"
        disabled={status === "submitting"}
        icon={status === "submitting" ? undefined : <Send className="h-4 w-4" />}
      >
        <AnimatePresence mode="wait">
          {status === "submitting" ? (
            <motion.span key="loading" initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="flex items-center gap-2">
              <Loader2 className="h-4 w-4 animate-spin" /> Sending...
            </motion.span>
          ) : (
            <motion.span key="idle" initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
              Send Message
            </motion.span>
          )}
        </AnimatePresence>
      </Button>
      <p className="mt-3 text-center text-xs text-(--color-ink-faint)">We typically respond within 48 hours.</p>
    </form>
  );
}
