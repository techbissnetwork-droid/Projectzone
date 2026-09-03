import { cn } from "@/lib/cn";
import { fontSerif } from "@/components/concept-2/fonts";

export function ProcessListItem({
  step,
  title,
  description,
  className,
}: {
  step: string;
  title: string;
  description: string;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "grid grid-cols-1 gap-4 border-t border-neutral-200 py-10 sm:grid-cols-[120px_1fr] sm:gap-10",
        className
      )}
    >
      <span className={cn(fontSerif, "text-5xl leading-none text-neutral-300 sm:text-6xl")}>{step}</span>
      <div>
        <h3 className={cn(fontSerif, "text-2xl text-neutral-900 sm:text-3xl")}>{title}</h3>
        <p className="mt-3 max-w-2xl text-sm leading-relaxed text-neutral-600 sm:text-base">{description}</p>
      </div>
    </div>
  );
}
