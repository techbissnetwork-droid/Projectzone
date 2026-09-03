"use client";

import { useState, type FormEvent } from "react";
import { CheckCircle2 } from "lucide-react";
import { services } from "@/lib/site-data";
import { Button } from "@/components/concept-1/Button";
import { cn } from "@/lib/cn";

const inputClasses =
  "w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-neutral-100 placeholder:text-neutral-500 backdrop-blur-xl transition-colors focus:border-cyan-300/50 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/70";

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

  const toggleService = (title: string) => {
    setSelectedServices((prev) =>
      prev.includes(title) ? prev.filter((item) => item !== title) : [...prev, title]
    );
  };

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
          Your project brief is in.
        </h3>
        <p className="mt-3 max-w-sm text-sm leading-relaxed text-neutral-400">
          We respond within [X] business days with next steps and, where useful, a few
          clarifying questions before we scope your project.
        </p>
      </div>
    );
  }

  return (
    <form
      onSubmit={handleSubmit}
      className="space-y-6 rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur-xl sm:p-8"
    >
      <div className="grid gap-5 sm:grid-cols-2">
        <div>
          <label htmlFor="inquiry-name" className="mb-2 block text-sm font-medium text-neutral-300">
            Full name
          </label>
          <input
            id="inquiry-name"
            name="name"
            type="text"
            required
            autoComplete="name"
            className={inputClasses}
            placeholder="Jordan Reyes"
          />
        </div>
        <div>
          <label htmlFor="inquiry-email" className="mb-2 block text-sm font-medium text-neutral-300">
            Email address
          </label>
          <input
            id="inquiry-email"
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
          <label htmlFor="inquiry-company" className="mb-2 block text-sm font-medium text-neutral-300">
            Company
          </label>
          <input
            id="inquiry-company"
            name="company"
            type="text"
            autoComplete="organization"
            className={inputClasses}
            placeholder="Your company name"
          />
        </div>
        <div>
          <label htmlFor="inquiry-budget" className="mb-2 block text-sm font-medium text-neutral-300">
            Estimated budget
          </label>
          <select id="inquiry-budget" name="budget" defaultValue="" className={inputClasses}>
            <option value="" disabled>
              Select a range
            </option>
            {budgetOptions.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
        </div>
      </div>

      <fieldset>
        <legend className="mb-3 text-sm font-medium text-neutral-300">
          Which services are you interested in?
        </legend>
        <div className="grid gap-3 sm:grid-cols-2">
          {services.map((service) => {
            const checked = selectedServices.includes(service.title);
            return (
              <label
                key={service.slug}
                className={cn(
                  "flex cursor-pointer items-center gap-3 rounded-2xl border px-4 py-3 text-sm transition-colors",
                  checked
                    ? "border-cyan-300/40 bg-white/10 text-neutral-50"
                    : "border-white/10 bg-white/5 text-neutral-300 hover:border-white/20"
                )}
              >
                <input
                  type="checkbox"
                  name="services"
                  value={service.title}
                  checked={checked}
                  onChange={() => toggleService(service.title)}
                  className="h-4 w-4 flex-none rounded border-white/30 bg-transparent text-cyan-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/70"
                />
                {service.title}
              </label>
            );
          })}
        </div>
      </fieldset>

      <div>
        <label htmlFor="inquiry-description" className="mb-2 block text-sm font-medium text-neutral-300">
          Tell us about your project
        </label>
        <textarea
          id="inquiry-description"
          name="description"
          required
          rows={5}
          className={inputClasses}
          placeholder="What are you building, and what does success look like?"
        />
      </div>

      <Button type="submit" variant="primary" className="w-full sm:w-auto">
        Submit Project Inquiry
      </Button>
    </form>
  );
}
