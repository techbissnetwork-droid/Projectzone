"use client";

import { useState, type FormEvent } from "react";
import { CheckCircle2 } from "lucide-react";
import { services } from "@/lib/site-data";
import { Button } from "@/components/concept-1/Button";

const inputClasses =
  "w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-neutral-100 placeholder:text-neutral-500 backdrop-blur-xl transition-colors focus:border-cyan-300/50 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/70";

export function ContactForm() {
  const [submitted, setSubmitted] = useState(false);

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setSubmitted(true);
  };

  if (submitted) {
    return (
      <div
        role="status"
        className="flex flex-col items-center justify-center rounded-3xl border border-white/10 bg-white/5 px-8 py-16 text-center backdrop-blur-xl"
      >
        <span className="flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-r from-cyan-400 via-indigo-400 to-fuchsia-500">
          <CheckCircle2 className="h-7 w-7 text-neutral-950" aria-hidden="true" />
        </span>
        <h3 className="mt-6 text-xl font-semibold tracking-tight text-neutral-50">
          Message received.
        </h3>
        <p className="mt-3 max-w-sm text-sm leading-relaxed text-neutral-400">
          Thank you for reaching out. Our team will review your message and get back to you
          shortly.
        </p>
      </div>
    );
  }

  return (
    <form
      onSubmit={handleSubmit}
      className="space-y-5 rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur-xl sm:p-8"
    >
      <div className="grid gap-5 sm:grid-cols-2">
        <div>
          <label htmlFor="contact-name" className="mb-2 block text-sm font-medium text-neutral-300">
            Full name
          </label>
          <input
            id="contact-name"
            name="name"
            type="text"
            required
            autoComplete="name"
            className={inputClasses}
            placeholder="Jordan Reyes"
          />
        </div>
        <div>
          <label htmlFor="contact-email" className="mb-2 block text-sm font-medium text-neutral-300">
            Email address
          </label>
          <input
            id="contact-email"
            name="email"
            type="email"
            required
            autoComplete="email"
            className={inputClasses}
            placeholder="you@company.com"
          />
        </div>
      </div>
      <div className="grid gap-5 sm:grid-cols-2">
        <div>
          <label htmlFor="contact-company" className="mb-2 block text-sm font-medium text-neutral-300">
            Company
          </label>
          <input
            id="contact-company"
            name="company"
            type="text"
            autoComplete="organization"
            className={inputClasses}
            placeholder="Your company name"
          />
        </div>
        <div>
          <label htmlFor="contact-service" className="mb-2 block text-sm font-medium text-neutral-300">
            Service of interest
          </label>
          <select id="contact-service" name="service" defaultValue="" className={inputClasses}>
            <option value="" disabled>
              Select a service
            </option>
            {services.map((service) => (
              <option key={service.slug} value={service.title}>
                {service.title}
              </option>
            ))}
          </select>
        </div>
      </div>
      <div>
        <label htmlFor="contact-message" className="mb-2 block text-sm font-medium text-neutral-300">
          Message
        </label>
        <textarea
          id="contact-message"
          name="message"
          required
          rows={5}
          className={inputClasses}
          placeholder="Tell us a bit about what you're looking to build."
        />
      </div>
      <Button type="submit" variant="primary" className="w-full sm:w-auto">
        Send Message
      </Button>
    </form>
  );
}
