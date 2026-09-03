import type { ReactNode } from "react";
import { cn } from "@/lib/cn";
import { Container } from "@/components/concept-2/Container";

/**
 * The structural building block for concept-2 pages: generous vertical
 * rhythm, an optional subtle off-white tone, and thin hairline rules as the
 * only "decorative" device — no shadows, no gradients.
 */
export function Section({
  children,
  className,
  containerClassName,
  tone = "white",
  border = "none",
  id,
}: {
  children: ReactNode;
  className?: string;
  containerClassName?: string;
  tone?: "white" | "off";
  border?: "top" | "bottom" | "both" | "none";
  id?: string;
}) {
  return (
    <section
      id={id}
      className={cn(
        "py-20 sm:py-28",
        tone === "off" ? "bg-neutral-50" : "bg-white",
        border === "top" && "border-t border-neutral-200",
        border === "bottom" && "border-b border-neutral-200",
        border === "both" && "border-y border-neutral-200",
        className
      )}
    >
      <Container className={containerClassName}>{children}</Container>
    </section>
  );
}
