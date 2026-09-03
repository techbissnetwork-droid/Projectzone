import { cn } from "@/lib/utils";
import { Reveal } from "./Reveal";

export function SectionHeading({
  eyebrow,
  title,
  description,
  align = "center",
  size = "default",
  className,
}: {
  eyebrow?: string;
  title: React.ReactNode;
  description?: React.ReactNode;
  align?: "center" | "left";
  size?: "default" | "large";
  className?: string;
}) {
  return (
    <div
      className={cn(
        "flex flex-col gap-4",
        align === "center" ? "items-center text-center" : "items-start text-left",
        className,
      )}
    >
      {eyebrow && (
        <Reveal>
          <span className="inline-flex items-center gap-2 rounded-full border border-(--color-border-strong) bg-(--color-surface-raised) px-3.5 py-1.5 text-xs font-medium tracking-wide text-(--color-ink-muted) uppercase">
            <span className="h-1.5 w-1.5 rounded-full bg-(--color-accent)" />
            {eyebrow}
          </span>
        </Reveal>
      )}
      <Reveal delay={0.06}>
        <h2
          className={cn(
            "font-medium tracking-tight text-balance text-(--color-ink)",
            size === "default" ? "text-3xl sm:text-4xl lg:text-[2.75rem] lg:leading-[1.1]" : "text-4xl sm:text-5xl lg:text-6xl lg:leading-[1.05]",
          )}
        >
          {title}
        </h2>
      </Reveal>
      {description && (
        <Reveal delay={0.12}>
          <p
            className={cn(
              "text-balance text-(--color-ink-muted) leading-relaxed",
              align === "center" ? "max-w-2xl" : "max-w-xl",
              size === "default" ? "text-base sm:text-lg" : "text-lg sm:text-xl",
            )}
          >
            {description}
          </p>
        </Reveal>
      )}
    </div>
  );
}
