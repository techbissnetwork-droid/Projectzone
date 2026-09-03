import type { ReactNode } from "react";
import { HeroBackground } from "@/components/concept-1/HeroBackground";
import { Eyebrow } from "@/components/concept-1/Section";
import { Container } from "@/components/concept-1/Container";
import { cn } from "@/lib/cn";

export function PageHero({
  eyebrow,
  title,
  description,
  children,
  align = "left",
  className,
}: {
  eyebrow?: string;
  title: ReactNode;
  description?: ReactNode;
  children?: ReactNode;
  align?: "left" | "center";
  className?: string;
}) {
  return (
    <section className={cn("relative overflow-hidden pb-16 pt-40 sm:pb-20 sm:pt-48", className)}>
      <HeroBackground />
      <Container>
        <div className={cn("max-w-3xl", align === "center" && "mx-auto text-center")}>
          {eyebrow ? <Eyebrow className="mb-6">{eyebrow}</Eyebrow> : null}
          <h1 className="text-4xl font-semibold leading-[1.05] tracking-tight text-neutral-50 sm:text-5xl lg:text-6xl">
            {title}
          </h1>
          {description ? (
            <p className="mt-6 text-lg leading-relaxed text-neutral-400">{description}</p>
          ) : null}
          {children}
        </div>
      </Container>
    </section>
  );
}
