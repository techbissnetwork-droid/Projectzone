import Link from "next/link";
import type { LucideIcon } from "lucide-react";
import { ArrowRight } from "lucide-react";
import { cn } from "@/lib/cn";

type CommonProps = {
  children: React.ReactNode;
  variant?: "primary" | "secondary" | "ghost";
  size?: "md" | "sm";
  icon?: LucideIcon;
  iconPosition?: "leading" | "trailing";
  className?: string;
};

type ButtonAsLink = CommonProps & {
  href: string;
  onClick?: never;
  type?: never;
};

type ButtonAsButton = CommonProps & {
  href?: never;
  onClick?: React.MouseEventHandler<HTMLButtonElement>;
  type?: "button" | "submit";
};

type ButtonProps = ButtonAsLink | ButtonAsButton;

const base =
  "group relative inline-flex items-center justify-center gap-2 rounded-xl font-semibold transition-all duration-200 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 focus-visible:ring-offset-2 focus-visible:ring-offset-[#0b0c14] disabled:opacity-50 disabled:pointer-events-none min-h-[44px]";

const variants: Record<NonNullable<CommonProps["variant"]>, string> = {
  primary:
    "bg-gradient-to-r from-violet-500 via-fuchsia-500 to-blue-500 bg-[length:160%_160%] bg-left text-white shadow-lg shadow-violet-500/25 hover:-translate-y-0.5 hover:bg-right hover:shadow-xl hover:shadow-violet-500/30",
  secondary:
    "border border-white/15 bg-white/[0.03] text-white hover:-translate-y-0.5 hover:border-white/30 hover:bg-white/[0.08]",
  ghost: "text-slate-300 hover:text-white",
};

const sizes: Record<NonNullable<CommonProps["size"]>, string> = {
  md: "px-6 py-3 text-sm sm:text-base",
  sm: "px-4 py-2.5 text-sm",
};

export function Button({
  children,
  variant = "primary",
  size = "md",
  icon: Icon = ArrowRight,
  iconPosition = "trailing",
  className,
  ...rest
}: ButtonProps) {
  const classes = cn(base, variants[variant], sizes[size], className);
  const content = (
    <>
      {Icon && iconPosition === "leading" ? (
        <Icon className="h-4 w-4 shrink-0 transition-transform duration-200 group-hover:-translate-x-0.5" aria-hidden="true" />
      ) : null}
      <span>{children}</span>
      {Icon && iconPosition === "trailing" ? (
        <Icon className="h-4 w-4 shrink-0 transition-transform duration-200 group-hover:translate-x-0.5" aria-hidden="true" />
      ) : null}
    </>
  );

  if ("href" in rest && rest.href) {
    return (
      <Link href={rest.href} className={classes}>
        {content}
      </Link>
    );
  }

  const { onClick, type = "button" } = rest as ButtonAsButton;
  return (
    <button type={type} onClick={onClick} className={classes}>
      {content}
    </button>
  );
}
