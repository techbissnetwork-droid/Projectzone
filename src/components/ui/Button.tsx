"use client";

import Link from "next/link";
import {
  motion,
  useMotionValue,
  useSpring,
  type HTMLMotionProps,
} from "motion/react";
import { ArrowRight } from "lucide-react";
import { useRef, type ReactNode } from "react";
import { cn } from "@/lib/utils/cn";

type Variant = "primary" | "secondary" | "ghost";
type Size = "md" | "lg";

const variantClasses: Record<Variant, string> = {
  primary:
    "bg-paper text-ink hover:bg-gold-bright border border-transparent shadow-[0_1px_0_0_rgba(255,255,255,0.15)_inset]",
  secondary:
    "bg-transparent text-paper border border-line-strong hover:border-gold/60 hover:text-gold-bright",
  ghost:
    "bg-transparent text-paper-dim border border-transparent hover:text-paper",
};

const sizeClasses: Record<Size, string> = {
  md: "h-11 px-5 text-sm",
  lg: "h-14 px-7 text-[0.95rem]",
};

interface BaseProps {
  children: ReactNode;
  variant?: Variant;
  size?: Size;
  icon?: boolean;
  className?: string;
  magnetic?: boolean;
}

interface ButtonAsLink extends BaseProps {
  href: string;
  external?: boolean;
}

interface ButtonAsButton
  extends BaseProps,
    Omit<HTMLMotionProps<"button">, "children" | "className"> {
  href?: undefined;
}

type ButtonProps = ButtonAsLink | ButtonAsButton;

function useMagnetic(strength = 0.35) {
  const elRef = useRef<HTMLElement | null>(null);
  const x = useMotionValue(0);
  const y = useMotionValue(0);
  const sx = useSpring(x, { stiffness: 300, damping: 20, mass: 0.5 });
  const sy = useSpring(y, { stiffness: 300, damping: 20, mass: 0.5 });

  const setRef = (el: HTMLElement | null) => {
    elRef.current = el;
  };

  const onMouseMove = (e: React.MouseEvent) => {
    const el = elRef.current;
    if (!el || !window.matchMedia("(pointer: fine)").matches) return;
    const rect = el.getBoundingClientRect();
    const relX = e.clientX - rect.left - rect.width / 2;
    const relY = e.clientY - rect.top - rect.height / 2;
    x.set(relX * strength);
    y.set(relY * strength);
  };

  const onMouseLeave = () => {
    x.set(0);
    y.set(0);
  };

  return { setRef, x: sx, y: sy, onMouseMove, onMouseLeave };
}

export function Button(props: ButtonProps) {
  const {
    children,
    variant = "primary",
    size = "md",
    icon = true,
    className,
    magnetic = true,
  } = props;

  const mag = useMagnetic();

  const classes = cn(
    "group relative inline-flex select-none items-center justify-center gap-2 whitespace-nowrap rounded-full font-medium tracking-tight transition-colors duration-300",
    variantClasses[variant],
    sizeClasses[size],
    className,
  );

  const content = (
    <>
      <span>{children}</span>
      {icon && (
        <ArrowRight
          className="size-4 shrink-0 transition-transform duration-300 ease-out group-hover:translate-x-1"
          aria-hidden
        />
      )}
    </>
  );

  const motionProps = magnetic
    ? {
        style: { x: mag.x, y: mag.y },
        onMouseMove: mag.onMouseMove,
        onMouseLeave: mag.onMouseLeave,
      }
    : {};

  if ("href" in props && props.href) {
    const { href, external } = props;
    if (external || href.startsWith("http") || href.startsWith("mailto:")) {
      return (
        <motion.a
          ref={mag.setRef}
          href={href}
          target={external ? "_blank" : undefined}
          rel={external ? "noopener noreferrer" : undefined}
          className={classes}
          {...motionProps}
        >
          {content}
        </motion.a>
      );
    }
    return (
      <motion.div
        // eslint-disable-next-line react-hooks/refs -- plain callback ref from useMagnetic; safe outside React Compiler
        ref={mag.setRef}
        className="inline-block"
        {...motionProps}
      >
        <Link href={href} className={classes}>
          {content}
        </Link>
      </motion.div>
    );
  }

  const {
    children: _children,
    variant: _variant,
    size: _size,
    icon: _icon,
    className: _className,
    magnetic: _magnetic,
    href: _href,
    ...nativeButtonProps
  } = props as ButtonAsButton;

  return (
    <motion.button
      // eslint-disable-next-line react-hooks/refs -- plain callback ref from useMagnetic; safe outside React Compiler
      ref={mag.setRef}
      className={classes}
      {...motionProps}
      {...nativeButtonProps}
    >
      {content}
    </motion.button>
  );
}
