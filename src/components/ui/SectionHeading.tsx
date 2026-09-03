import { cn } from "@/lib/utils/cn";
import { Eyebrow } from "./Eyebrow";
import { Reveal } from "@/components/shared/Reveal";

export function SectionHeading({
  eyebrow,
  title,
  lead,
  align = "left",
  tone = "gold",
  size = "h2",
  className,
  titleClassName,
}: {
  eyebrow?: string;
  title: React.ReactNode;
  lead?: React.ReactNode;
  align?: "left" | "center";
  tone?: "gold" | "signal" | "neutral";
  size?: "h1" | "h2" | "h3";
  className?: string;
  titleClassName?: string;
}) {
  return (
    <div
      className={cn(
        "flex flex-col gap-5",
        align === "center" && "items-center text-center",
        className,
      )}
    >
      {eyebrow && (
        <Reveal>
          <Eyebrow tone={tone}>{eyebrow}</Eyebrow>
        </Reveal>
      )}
      <Reveal delay={0.06}>
        <h2
          className={cn(
            size === "h1" ? "text-h1" : size === "h3" ? "text-h3" : "text-h2",
            "text-balance font-medium text-paper",
            titleClassName,
          )}
        >
          {title}
        </h2>
      </Reveal>
      {lead && (
        <Reveal delay={0.12}>
          <p
            className={cn(
              "text-lead max-w-2xl text-paper-dim",
              align === "center" && "mx-auto",
            )}
          >
            {lead}
          </p>
        </Reveal>
      )}
    </div>
  );
}
