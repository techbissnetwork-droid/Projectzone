import * as React from "react";
import { cn } from "@/lib/utils";

const fieldBase =
  "focus-ring w-full rounded-(--radius-sm) border border-(--color-border-strong) bg-(--color-surface-raised) px-3.5 py-2.5 text-sm text-(--color-ink) placeholder:text-(--color-ink-faint) transition-colors duration-150 focus-visible:border-(--color-accent)";

export function Label({
  children,
  htmlFor,
  className,
  required,
}: {
  children: React.ReactNode;
  htmlFor?: string;
  className?: string;
  required?: boolean;
}) {
  return (
    <label htmlFor={htmlFor} className={cn("mb-1.5 block text-sm font-medium text-(--color-ink)", className)}>
      {children}
      {required && <span className="ml-0.5 text-(--color-accent-2)">*</span>}
    </label>
  );
}

export const Input = React.forwardRef<HTMLInputElement, React.InputHTMLAttributes<HTMLInputElement> & { error?: string }>(
  ({ className, error, ...props }, ref) => (
    <div>
      <input
        ref={ref}
        className={cn(fieldBase, error && "border-red-500/60 focus-visible:border-red-500", className)}
        {...props}
      />
      {error && <p className="mt-1.5 text-xs text-red-400">{error}</p>}
    </div>
  ),
);
Input.displayName = "Input";

export const Textarea = React.forwardRef<
  HTMLTextAreaElement,
  React.TextareaHTMLAttributes<HTMLTextAreaElement> & { error?: string }
>(({ className, error, ...props }, ref) => (
  <div>
    <textarea
      ref={ref}
      className={cn(fieldBase, "min-h-32 resize-y", error && "border-red-500/60 focus-visible:border-red-500", className)}
      {...props}
    />
    {error && <p className="mt-1.5 text-xs text-red-400">{error}</p>}
  </div>
));
Textarea.displayName = "Textarea";

export const Select = React.forwardRef<
  HTMLSelectElement,
  React.SelectHTMLAttributes<HTMLSelectElement> & { error?: string }
>(({ className, error, children, ...props }, ref) => (
  <div>
    <select ref={ref} className={cn(fieldBase, "appearance-none bg-no-repeat", className)} {...props}>
      {children}
    </select>
    {error && <p className="mt-1.5 text-xs text-red-400">{error}</p>}
  </div>
));
Select.displayName = "Select";

export function Checkbox({
  className,
  label,
  ...props
}: React.InputHTMLAttributes<HTMLInputElement> & { label?: React.ReactNode }) {
  return (
    <label className="flex cursor-pointer items-start gap-2.5 text-sm text-(--color-ink-muted)">
      <input
        type="checkbox"
        className={cn(
          "focus-ring mt-0.5 h-4 w-4 shrink-0 rounded border-(--color-border-strong) bg-(--color-surface-raised) accent-(--color-accent)",
          className,
        )}
        {...props}
      />
      {label}
    </label>
  );
}
