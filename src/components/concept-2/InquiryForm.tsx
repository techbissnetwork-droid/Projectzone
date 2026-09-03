"use client";

import { useState, type FormEvent } from "react";
import { services } from "@/lib/site-data";
import { cn } from "@/lib/cn";
import { Button } from "@/components/concept-2/Button";

const fieldClasses =
  "mt-3 w-full rounded-none border-0 border-b border-neutral-300 bg-transparent px-0 py-3 text-neutral-900 placeholder:text-neutral-400 focus:border-neutral-900 focus:outline-none focus-visible:ring-0 transition-colors";

const budgetRanges = [
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
    setSelectedServices((prev) =>
      prev.includes(title) ? prev.filter((t) => t !== title) : [...prev, title]
    );
  }

  function handleSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setSubmitted(true);
  }

  if (submitted) {
    return (
      <div className="border border-neutral-200 p-8 sm:p-10" role="status">
        <p className="text-lg text-neutral-900">Your project inquiry has been received.</p>
        <p className="mt-2 text-sm text-neutral-500">
          We respond within [X] business days with next steps.
        </p>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-10" noValidate>
      <div className="grid gap-8 sm:grid-cols-2">
        <div>
          <label htmlFor="inquiry-name" className="text-xs uppercase tracking-[0.2em] text-neutral-500">
            Name
          </label>
          <input id="inquiry-name" name="name" type="text" required className={fieldClasses} />
        </div>
        <div>
          <label htmlFor="inquiry-email" className="text-xs uppercase tracking-[0.2em] text-neutral-500">
            Email
          </label>
          <input id="inquiry-email" name="email" type="email" required className={fieldClasses} />
        </div>
        <div>
          <label htmlFor="inquiry-company" className="text-xs uppercase tracking-[0.2em] text-neutral-500">
            Company
          </label>
          <input id="inquiry-company" name="company" type="text" className={fieldClasses} />
        </div>
        <div>
          <label htmlFor="inquiry-budget" className="text-xs uppercase tracking-[0.2em] text-neutral-500">
            Budget range
          </label>
          <select id="inquiry-budget" name="budget" defaultValue="" className={fieldClasses}>
            <option value="" disabled>
              Select a range
            </option>
            {budgetRanges.map((b) => (
              <option key={b} value={b}>
                {b}
              </option>
            ))}
          </select>
        </div>
      </div>

      <fieldset>
        <legend className="text-xs uppercase tracking-[0.2em] text-neutral-500">
          Services you&apos;re interested in
        </legend>
        <div className="mt-4 grid gap-x-8 sm:grid-cols-2">
          {services.map((s) => (
            <label
              key={s.slug}
              className="flex cursor-pointer items-center gap-3 border-b border-neutral-200 py-3 text-sm text-neutral-700"
            >
              <input
                type="checkbox"
                checked={selectedServices.includes(s.title)}
                onChange={() => toggleService(s.title)}
                className="h-4 w-4 shrink-0 rounded-none border border-neutral-400 text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900"
              />
              {s.title}
            </label>
          ))}
        </div>
      </fieldset>

      <div>
        <label htmlFor="inquiry-description" className="text-xs uppercase tracking-[0.2em] text-neutral-500">
          Project description
        </label>
        <textarea
          id="inquiry-description"
          name="description"
          rows={6}
          required
          className={cn(fieldClasses, "resize-none")}
        />
      </div>

      <Button type="submit">Submit inquiry</Button>
    </form>
  );
}
