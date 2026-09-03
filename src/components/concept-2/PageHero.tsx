import type { ReactNode } from "react";
import { fontSerif } from "@/components/concept-2/fonts";
import { Reveal } from "@/components/concept-2/Reveal";
import { Container } from "@/components/concept-2/Container";
import { cn } from "@/lib/cn";

/**
 * Shared editorial hero for every interior page: uppercase eyebrow top-left,
 * a massive serif headline, and a short supporting paragraph pushed off to
 * one side — the asymmetric composition this concept favors over centered
 * "SaaS template" heroes.
 */
export function PageHero({
  eyebrow,
  title,
  description,
  index,
  className,
}: {
  eyebrow: string;
  title: ReactNode;
  description?: ReactNode;
  index?: string;
  className?: string;
}) {
  return (
    <section className={cn("border-b border-neutral-200 pb-16 pt-16 sm:pb-24 sm:pt-24", className)}>
      <Container>
        <div className="flex items-start justify-between gap-8">
          <Reveal>
            <p className="text-xs font-medium uppercase tracking-[0.2em] text-neutral-500">{eyebrow}</p>
          </Reveal>
          {index ? (
            <Reveal>
              <span className={cn(fontSerif, "text-sm text-neutral-400")}>{index}</span>
            </Reveal>
          ) : null}
        </div>
        <Reveal delay={0.05}>
          <h1 className={cn(fontSerif, "mt-6 max-w-4xl text-5xl leading-[1.02] text-neutral-900 sm:text-7xl lg:text-[5.5rem]")}>
            {title}
          </h1>
        </Reveal>
        {description ? (
          <Reveal delay={0.1}>
            <div className="mt-10 max-w-md sm:ml-auto sm:mt-8">
              <p className="text-base leading-relaxed text-neutral-600 sm:text-lg">{description}</p>
            </div>
          </Reveal>
        ) : null}
      </Container>
    </section>
  );
}
