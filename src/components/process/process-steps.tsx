"use client";

import { useEffect, useRef, useState } from "react";
import { cn } from "@/lib/utils";
import { Container } from "@/components/ui/container";

const steps = [
  {
    n: "01",
    title: "Discover",
    detail:
      "We start by understanding your business, your customers, and what success actually looks like — before touching a single design decision.",
    points: ["Business & customer research", "Goals and success metrics", "Technical requirements"],
  },
  {
    n: "02",
    title: "Choose",
    detail:
      "Together we decide the right foundation: a ready-made product from the marketplace to move fast, or a custom architecture built from zero.",
    points: ["Marketplace product evaluation", "Custom architecture planning", "Timeline and budget alignment"],
  },
  {
    n: "03",
    title: "Build",
    detail:
      "We develop the website, application and infrastructure — with real engineering discipline, not disposable prototypes.",
    points: ["Production-grade engineering", "Iterative reviews", "Real content, not lorem ipsum"],
  },
  {
    n: "04",
    title: "Brand",
    detail:
      "Every pixel is transformed around your identity — logo, color, typography, imagery and voice — until it feels unmistakably yours.",
    points: ["Brand Studio customization", "Content & imagery integration", "Consistency across every page"],
  },
  {
    n: "05",
    title: "Launch",
    detail:
      "Domain, hosting, SSL, business email and payments — configured together, tested, and switched on.",
    points: ["Domain & DNS configuration", "Hosting, SSL & email setup", "Go-live checklist"],
  },
  {
    n: "06",
    title: "Grow",
    detail:
      "TECHBISS stays involved after launch — monitoring, maintaining, and continuously improving based on real usage.",
    points: ["Monitoring & maintenance", "Performance optimization", "Ongoing custom development"],
  },
];

export function ProcessSteps() {
  const [active, setActive] = useState(0);
  const refs = useRef<(HTMLDivElement | null)[]>([]);

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const idx = Number(entry.target.getAttribute("data-idx"));
            setActive(idx);
          }
        });
      },
      { rootMargin: "-40% 0px -50% 0px", threshold: 0 }
    );
    refs.current.forEach((el) => el && observer.observe(el));
    return () => observer.disconnect();
  }, []);

  return (
    <Container wide>
      <div className="grid gap-10 lg:grid-cols-[220px_1fr] lg:gap-16">
        <div className="hidden lg:block">
          <div className="sticky top-32 flex flex-col gap-1">
            {steps.map((step, i) => (
              <div
                key={step.n}
                className={cn(
                  "flex items-center gap-3 rounded-lg px-3 py-2.5 transition-colors",
                  active === i ? "bg-ink-900/60" : ""
                )}
              >
                <span
                  className={cn(
                    "font-mono-label text-[11px] transition-colors",
                    active === i ? "text-gold-400" : "text-paper-50/25"
                  )}
                >
                  {step.n}
                </span>
                <span
                  className={cn(
                    "text-[13px] font-medium transition-colors",
                    active === i ? "text-paper-50" : "text-paper-50/30"
                  )}
                >
                  {step.title}
                </span>
              </div>
            ))}
          </div>
        </div>

        <div className="flex flex-col">
          {steps.map((step, i) => (
            <div
              key={step.n}
              ref={(el) => {
                refs.current[i] = el;
              }}
              data-idx={i}
              className={cn(
                "border-b border-line-dark py-14 transition-opacity duration-500 first:pt-0 last:border-0 sm:py-20",
                active === i ? "opacity-100" : "opacity-50"
              )}
            >
              <div className="font-mono-label text-[12px] text-gold-400 lg:hidden">{step.n}</div>
              <h2 className="mt-3 text-[30px] font-medium tracking-tight text-paper-50 sm:text-[40px] lg:mt-0">
                {step.title}
              </h2>
              <p className="mt-5 max-w-xl text-[15.5px] leading-relaxed text-paper-50/60">
                {step.detail}
              </p>
              <ul className="mt-6 flex flex-col gap-2.5">
                {step.points.map((p) => (
                  <li key={p} className="flex items-center gap-2.5 text-[13.5px] text-paper-50/50">
                    <span className="size-1 rounded-full bg-gold-400" />
                    {p}
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      </div>
    </Container>
  );
}
