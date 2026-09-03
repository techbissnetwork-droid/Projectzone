import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { Reveal } from "@/components/ui/Reveal";

export function PageHero({
  eyebrow,
  title,
  description,
  children,
}: {
  eyebrow: string;
  title: React.ReactNode;
  description: React.ReactNode;
  children?: React.ReactNode;
}) {
  return (
    <section className="relative overflow-hidden pt-16 pb-16 sm:pt-20 sm:pb-20 lg:pt-24 lg:pb-24">
      <div className="bg-grid pointer-events-none absolute inset-0 [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_50%,transparent_100%)]" />
      <div className="pointer-events-none absolute left-1/2 top-[-10rem] h-[26rem] w-[52rem] -translate-x-1/2 rounded-full bg-[radial-gradient(closest-side,rgba(75,91,255,0.2),transparent)] blur-2xl" />
      <Container className="relative">
        <SectionHeading eyebrow={eyebrow} title={title} description={description} size="large" />
        {children && (
          <Reveal delay={0.2} className="mt-10 flex justify-center">
            {children}
          </Reveal>
        )}
      </Container>
    </section>
  );
}
