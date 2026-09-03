import { cn } from "@/lib/cn";
import { fontSerif } from "@/components/concept-2/fonts";
import type { CaseStudy } from "@/lib/site-data";

export function CaseStudyRow({
  caseStudy,
  className,
}: {
  caseStudy: CaseStudy;
  className?: string;
}) {
  return (
    <article className={cn("border-b border-neutral-200 py-10", className)}>
      <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">{caseStudy.industry}</p>
      <h3 className={cn(fontSerif, "mt-3 text-3xl text-neutral-900 sm:text-4xl")}>{caseStudy.title}</h3>
      <p className="mt-4 max-w-2xl text-sm leading-relaxed text-neutral-600 sm:text-base">{caseStudy.summary}</p>
      <div className="mt-5 flex flex-wrap gap-x-4 gap-y-2 text-xs uppercase tracking-[0.1em] text-neutral-500">
        {caseStudy.services.map((s) => (
          <span key={s}>{s}</span>
        ))}
      </div>
      <p className="mt-5 text-sm italic text-neutral-500">{caseStudy.outcome}</p>
    </article>
  );
}
