import { cn } from "@/lib/cn";
import { Container } from "./Container";

export function Section({
  className,
  containerClassName,
  children,
  id,
  "aria-label": ariaLabel,
}: {
  className?: string;
  containerClassName?: string;
  children: React.ReactNode;
  id?: string;
  "aria-label"?: string;
}) {
  return (
    <section id={id} aria-label={ariaLabel} className={cn("relative py-16 sm:py-20 lg:py-28", className)}>
      <Container className={containerClassName}>{children}</Container>
    </section>
  );
}

export function Eyebrow({ children, className }: { children: React.ReactNode; className?: string }) {
  return (
    <span
      className={cn(
        "inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-widest text-violet-300",
        className
      )}
    >
      {children}
    </span>
  );
}

export function SectionHeading({
  eyebrow,
  title,
  description,
  align = "left",
  className,
}: {
  eyebrow?: string;
  title: React.ReactNode;
  description?: React.ReactNode;
  align?: "left" | "center";
  className?: string;
}) {
  return (
    <div
      className={cn(
        "flex flex-col gap-4",
        align === "center" && "items-center text-center",
        className
      )}
    >
      {eyebrow ? <Eyebrow>{eyebrow}</Eyebrow> : null}
      <h2 className="font-display text-3xl font-semibold tracking-tight text-white sm:text-4xl lg:text-[2.75rem]">
        {title}
      </h2>
      {description ? (
        <p className={cn("max-w-2xl text-base text-slate-400 sm:text-lg", align === "center" && "mx-auto")}>
          {description}
        </p>
      ) : null}
    </div>
  );
}
