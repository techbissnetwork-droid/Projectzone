"use client";

import { useState, type FormEvent } from "react";
import { services } from "@/lib/site-data";
import { cn } from "@/lib/cn";
import { Button } from "@/components/concept-2/Button";

const fieldClasses =
  "mt-3 w-full rounded-none border-0 border-b border-neutral-300 bg-transparent px-0 py-3 text-neutral-900 placeholder:text-neutral-400 focus:border-neutral-900 focus:outline-none focus-visible:ring-0 transition-colors";

export function ContactForm() {
  const [submitted, setSubmitted] = useState(false);

  function handleSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setSubmitted(true);
  }

  if (submitted) {
    return (
      <div className="border border-neutral-200 p-8 sm:p-10" role="status">
        <p className="text-lg text-neutral-900">Thank you. Your message has been received.</p>
        <p className="mt-2 text-sm text-neutral-500">We&apos;ll respond within [X] business days.</p>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-8" noValidate>
      <div className="grid gap-8 sm:grid-cols-2">
        <div>
          <label htmlFor="contact-name" className="text-xs uppercase tracking-[0.2em] text-neutral-500">
            Name
          </label>
          <input id="contact-name" name="name" type="text" required className={fieldClasses} />
        </div>
        <div>
          <label htmlFor="contact-email" className="text-xs uppercase tracking-[0.2em] text-neutral-500">
            Email
          </label>
          <input id="contact-email" name="email" type="email" required className={fieldClasses} />
        </div>
        <div>
          <label htmlFor="contact-company" className="text-xs uppercase tracking-[0.2em] text-neutral-500">
            Company
          </label>
          <input id="contact-company" name="company" type="text" className={fieldClasses} />
        </div>
        <div>
          <label htmlFor="contact-service" className="text-xs uppercase tracking-[0.2em] text-neutral-500">
            Service of interest
          </label>
          <select id="contact-service" name="service" defaultValue="" className={fieldClasses}>
            <option value="" disabled>
              Select a service
            </option>
            {services.map((s) => (
              <option key={s.slug} value={s.title}>
                {s.title}
              </option>
            ))}
          </select>
        </div>
      </div>
      <div>
        <label htmlFor="contact-message" className="text-xs uppercase tracking-[0.2em] text-neutral-500">
          Message
        </label>
        <textarea id="contact-message" name="message" rows={5} required className={cn(fieldClasses, "resize-none")} />
      </div>
      <Button type="submit">Send message</Button>
    </form>
  );
}
