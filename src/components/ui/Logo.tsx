import { cn } from "@/lib/utils";

export function LogoMark({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 32 32" fill="none" className={cn("h-7 w-7", className)} aria-hidden="true">
      <defs>
        <linearGradient id="techbiss-mark" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stopColor="#4b5bff" />
          <stop offset="50%" stopColor="#7a5cff" />
          <stop offset="100%" stopColor="#17c3ff" />
        </linearGradient>
      </defs>
      <rect width="32" height="32" rx="9" fill="url(#techbiss-mark)" />
      <path
        d="M9 20.5 15.5 9l1.9 1.1-4.6 8.1h6.7l-6.5 11.4-1.9-1.1 4.6-8H9Z"
        fill="white"
        opacity="0.95"
      />
    </svg>
  );
}

export function Logo({ className, dark }: { className?: string; dark?: boolean }) {
  return (
    <span className={cn("inline-flex items-center gap-2.5", className)}>
      <LogoMark />
      <span
        className={cn(
          "text-[1.05rem] font-semibold tracking-tight",
          dark ? "text-slate-900" : "text-(--color-ink)",
        )}
      >
        TECHBISS
      </span>
    </span>
  );
}
