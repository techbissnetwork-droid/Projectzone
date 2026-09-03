import { Container } from "@/components/ui/container";
import { Eyebrow } from "@/components/ui/section";
import { cn } from "@/lib/utils";

export function PageHero({
  eyebrow,
  title,
  subtitle,
  children,
  className,
}: {
  eyebrow: string;
  title: React.ReactNode;
  subtitle?: string;
  children?: React.ReactNode;
  className?: string;
}) {
  return (
    <section className={cn("border-b border-line-dark pt-36 pb-16 sm:pt-44 sm:pb-20", className)}>
      <Container wide>
        <Eyebrow>{eyebrow}</Eyebrow>
        <h1 className="mt-6 max-w-3xl text-balance text-[38px] font-medium leading-[1.06] tracking-[-0.02em] text-paper-50 sm:text-[56px]">
          {title}
        </h1>
        {subtitle && (
          <p className="mt-6 max-w-xl text-[15px] leading-relaxed text-paper-50/55 sm:text-[17px]">
            {subtitle}
          </p>
        )}
        {children}
      </Container>
    </section>
  );
}
