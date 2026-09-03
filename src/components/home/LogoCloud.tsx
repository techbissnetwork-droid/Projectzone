import { Container } from "@/components/ui/Container";
import { Reveal } from "@/components/ui/Reveal";
import { logoCloud } from "@/lib/data/testimonials";

export function LogoCloud() {
  const loop = [...logoCloud, ...logoCloud];
  return (
    <div className="border-y border-(--color-border) py-10">
      <Container size="wide">
        <Reveal>
          <p className="mb-8 text-center text-xs font-medium uppercase tracking-wide text-(--color-ink-faint)">
            Powering teams at ambitious companies worldwide
          </p>
        </Reveal>
      </Container>
      <div className="relative overflow-hidden [mask-image:linear-gradient(90deg,transparent,#000_12%,#000_88%,transparent)]">
        <div className="flex w-max animate-marquee items-center gap-14">
          {loop.map((name, i) => (
            <span
              key={`${name}-${i}`}
              className="shrink-0 text-lg font-semibold tracking-tight text-(--color-ink-faint)"
            >
              {name}
            </span>
          ))}
        </div>
      </div>
    </div>
  );
}
