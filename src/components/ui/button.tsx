import Link from "next/link";
import { cn } from "@/lib/utils";
import { ArrowUpRight } from "lucide-react";

type Variant = "primary" | "secondary" | "ghost" | "outline-light";
type Size = "sm" | "md" | "lg";

const variants: Record<Variant, string> = {
  primary:
    "bg-gold-400 text-ink-950 hover:bg-gold-300 border border-gold-400",
  secondary:
    "bg-paper-50 text-ink-950 hover:bg-white border border-paper-50",
  ghost:
    "bg-transparent text-paper-50 border border-line-dark-strong hover:border-paper-50/40 hover:bg-white/5",
  "outline-light":
    "bg-transparent text-ink-950 border border-ink-950/20 hover:border-ink-950/50 hover:bg-ink-950/5",
};

const sizes: Record<Size, string> = {
  sm: "px-4 py-2 text-[13px]",
  md: "px-5 py-3 text-sm",
  lg: "px-7 py-4 text-[15px]",
};

export function Button({
  href,
  children,
  variant = "primary",
  size = "md",
  className,
  arrow = false,
  onClick,
  type = "button",
  target,
  disabled = false,
}: {
  href?: string;
  children: React.ReactNode;
  variant?: Variant;
  size?: Size;
  className?: string;
  arrow?: boolean;
  onClick?: () => void;
  type?: "button" | "submit";
  target?: string;
  disabled?: boolean;
}) {
  const classes = cn(
    "group inline-flex items-center justify-center gap-2 rounded-full font-medium tracking-tight transition-all duration-300 ease-out whitespace-nowrap",
    variants[variant],
    sizes[size],
    disabled && "pointer-events-none opacity-60",
    className
  );

  const content = (
    <>
      {children}
      {arrow && (
        <ArrowUpRight
          className="size-4 transition-transform duration-300 ease-out group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
          strokeWidth={2}
        />
      )}
    </>
  );

  if (href) {
    return (
      <Link href={href} className={classes} target={target}>
        {content}
      </Link>
    );
  }

  return (
    <button type={type} onClick={onClick} className={classes} disabled={disabled}>
      {content}
    </button>
  );
}
