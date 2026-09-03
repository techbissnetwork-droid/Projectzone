import Link from "next/link";
import { cn } from "@/lib/utils";
import { ArrowUpRight } from "lucide-react";

type Variant = "primary" | "secondary" | "ghost" | "outline-light";
type Size = "md" | "lg" | "sm";

const base =
  "group relative inline-flex items-center justify-center gap-2 font-medium transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] select-none whitespace-nowrap focus-visible:outline-2 focus-visible:outline-[var(--color-accent)] focus-visible:outline-offset-2";

const variants: Record<Variant, string> = {
  primary:
    "bg-[var(--color-ink)] text-[var(--color-bg)] hover:bg-white rounded-full",
  secondary:
    "bg-transparent text-[var(--color-ink)] border border-[var(--color-border-strong)] hover:border-[var(--color-ink)] hover:bg-white/5 rounded-full",
  ghost:
    "bg-transparent text-[var(--color-ink-muted)] hover:text-[var(--color-ink)] rounded-full",
  "outline-light":
    "bg-white/5 text-white border border-white/15 hover:bg-white/10 hover:border-white/30 backdrop-blur-sm rounded-full",
};

const sizes: Record<Size, string> = {
  sm: "text-[13px] px-4 py-2",
  md: "text-[14px] px-5 py-3",
  lg: "text-[15px] px-7 py-4",
};

interface ButtonBaseProps {
  variant?: Variant;
  size?: Size;
  className?: string;
  children: React.ReactNode;
  icon?: boolean;
}

export function Button({
  variant = "primary",
  size = "md",
  className,
  children,
  icon = true,
  href,
  ...props
}: ButtonBaseProps & (
  | ({ href: string } & Omit<React.ComponentProps<typeof Link>, "href" | "className">)
  | ({ href?: undefined } & React.ButtonHTMLAttributes<HTMLButtonElement>)
)) {
  const content = (
    <>
      <span>{children}</span>
      {icon && (
        <ArrowUpRight
          className="size-4 transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
          strokeWidth={2}
        />
      )}
    </>
  );

  const classes = cn(base, variants[variant], sizes[size], className);

  if (href) {
    return (
      <Link
        href={href}
        className={classes}
        {...(props as Omit<React.ComponentProps<typeof Link>, "href" | "className">)}
      >
        {content}
      </Link>
    );
  }

  return (
    <button
      className={classes}
      {...(props as React.ButtonHTMLAttributes<HTMLButtonElement>)}
    >
      {content}
    </button>
  );
}
