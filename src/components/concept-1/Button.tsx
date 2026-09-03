"use client";

import { forwardRef, useRef } from "react";
import type { AnchorHTMLAttributes, ButtonHTMLAttributes, MouseEvent, Ref, ReactNode } from "react";
import Link from "next/link";
import { motion, useMotionValue, useSpring } from "framer-motion";
import { cn } from "@/lib/cn";

type Variant = "primary" | "secondary" | "ghost";

const base =
  "relative inline-flex min-h-[44px] items-center justify-center gap-2 rounded-full px-7 py-3 text-sm font-medium tracking-tight transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/70 focus-visible:ring-offset-2 focus-visible:ring-offset-neutral-950";

const variants: Record<Variant, string> = {
  primary:
    "text-neutral-950 bg-gradient-to-r from-cyan-400 via-indigo-400 to-fuchsia-500 shadow-[0_0_0_1px_rgba(255,255,255,0.08),0_10px_40px_-10px_rgba(99,102,241,0.65)] hover:shadow-[0_0_0_1px_rgba(255,255,255,0.12),0_16px_50px_-8px_rgba(99,102,241,0.85)]",
  secondary:
    "text-neutral-100 bg-white/5 border border-white/15 backdrop-blur-xl hover:bg-white/10 hover:border-white/25",
  ghost:
    "text-neutral-300 hover:text-neutral-50 bg-transparent",
};

function Magnetic({ children }: { children: ReactNode }) {
  const ref = useRef<HTMLSpanElement>(null);
  const x = useMotionValue(0);
  const y = useMotionValue(0);
  const springX = useSpring(x, { stiffness: 200, damping: 15, mass: 0.2 });
  const springY = useSpring(y, { stiffness: 200, damping: 15, mass: 0.2 });

  const handleMouseMove = (e: MouseEvent<HTMLSpanElement>) => {
    const el = ref.current;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const relX = e.clientX - (rect.left + rect.width / 2);
    const relY = e.clientY - (rect.top + rect.height / 2);
    x.set(relX * 0.25);
    y.set(relY * 0.25);
  };

  const handleMouseLeave = () => {
    x.set(0);
    y.set(0);
  };

  return (
    <motion.span
      ref={ref}
      onMouseMove={handleMouseMove}
      onMouseLeave={handleMouseLeave}
      style={{ x: springX, y: springY }}
      className="inline-block"
    >
      {children}
    </motion.span>
  );
}

type ButtonAsButton = ButtonHTMLAttributes<HTMLButtonElement> & {
  href?: undefined;
  variant?: Variant;
};

type ButtonAsLink = AnchorHTMLAttributes<HTMLAnchorElement> & {
  href: string;
  variant?: Variant;
};

type ButtonProps = ButtonAsButton | ButtonAsLink;

export const Button = forwardRef<HTMLButtonElement | HTMLAnchorElement, ButtonProps>(
  function Button({ variant = "primary", className, children, ...props }, ref) {
    const classes = cn(base, variants[variant], className);
    const content = <span className="relative z-10">{children}</span>;

    if (typeof props.href === "string") {
      const { href, ...rest } = props as ButtonAsLink;
      const link = (
        <Link
          ref={ref as Ref<HTMLAnchorElement>}
          href={href}
          className={classes}
          {...rest}
        >
          {content}
        </Link>
      );
      return variant === "primary" ? <Magnetic>{link}</Magnetic> : link;
    }

    const buttonProps = props as ButtonAsButton;
    const button = (
      <button ref={ref as Ref<HTMLButtonElement>} className={classes} {...buttonProps}>
        {content}
      </button>
    );
    return variant === "primary" ? <Magnetic>{button}</Magnetic> : button;
  }
);
