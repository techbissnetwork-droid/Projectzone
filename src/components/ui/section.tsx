import { cn } from "@/lib/utils";
import { Container } from "./container";

export function Section({
  className,
  children,
  id,
  wide,
}: {
  className?: string;
  children: React.ReactNode;
  id?: string;
  wide?: boolean;
}) {
  return (
    <section id={id} className={cn("py-20 sm:py-28 lg:py-32", className)}>
      <Container wide={wide}>{children}</Container>
    </section>
  );
}

export function Eyebrow({
  children,
  className,
}: {
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "font-mono-label inline-flex items-center gap-2 text-[11px] uppercase text-gold-400",
        className
      )}
    >
      <span className="h-px w-6 bg-gold-500/60" />
      {children}
    </div>
  );
}
