import Link from "next/link";
import { ArrowUpRight } from "lucide-react";
import type { ButtonHTMLAttributes, ReactNode } from "react";
import { cn } from "@/lib/cn";

type Variant = "primary" | "secondary";

const primaryClasses =
  "inline-flex min-h-[44px] items-center justify-center rounded-full bg-neutral-900 px-8 py-3.5 text-sm font-medium text-white transition-[opacity,transform] duration-300 hover:opacity-80 active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";

const secondaryClasses =
  "group inline-flex min-h-[44px] items-center gap-1.5 text-sm font-medium text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 rounded-sm";

function Underline({ children }: { children: ReactNode }) {
  return (
    <span className="relative">
      {children}
      <span className="absolute left-0 -bottom-0.5 h-px w-full origin-left scale-x-0 bg-neutral-900 transition-transform duration-300 group-hover:scale-x-100" />
    </span>
  );
}

/** Solid-fill or underline-link CTA, as a navigable link. */
export function LinkButton({
  href,
  variant = "primary",
  className,
  children,
}: {
  href: string;
  variant?: Variant;
  className?: string;
  children: ReactNode;
}) {
  if (variant === "secondary") {
    return (
      <Link href={href} className={cn(secondaryClasses, className)}>
        <Underline>{children}</Underline>
        <ArrowUpRight className="h-4 w-4 shrink-0 transition-transform duration-300 group-hover:-translate-y-0.5 group-hover:translate-x-0.5" />
      </Link>
    );
  }
  return (
    <Link href={href} className={cn(primaryClasses, className)}>
      {children}
    </Link>
  );
}

/** Same visual system for actual form/native buttons (submit, toggle, etc). */
export function Button({
  variant = "primary",
  className,
  children,
  ...rest
}: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: Variant }) {
  if (variant === "secondary") {
    return (
      <button className={cn(secondaryClasses, className)} {...rest}>
        <Underline>{children}</Underline>
      </button>
    );
  }
  return (
    <button className={cn(primaryClasses, className)} {...rest}>
      {children}
    </button>
  );
}
