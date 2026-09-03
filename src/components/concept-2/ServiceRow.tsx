import Link from "next/link";
import { ArrowUpRight } from "lucide-react";
import { cn } from "@/lib/cn";
import { fontSerif } from "@/components/concept-2/fonts";

/** A list row, not a card — this concept favors lists/tables over box grids. */
export function ServiceRow({
  index,
  title,
  description,
  href,
  className,
}: {
  index: string;
  title: string;
  description: string;
  href: string;
  className?: string;
}) {
  return (
    <Link
      href={href}
      className={cn(
        "group grid grid-cols-1 items-center gap-3 border-b border-neutral-200 py-8 transition-colors sm:grid-cols-[64px_1fr_auto] sm:gap-8 hover:border-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900",
        className
      )}
    >
      <span className={cn(fontSerif, "text-sm text-neutral-400")}>{index}</span>
      <span>
        <span className={cn(fontSerif, "block text-2xl text-neutral-900 transition-transform duration-300 group-hover:translate-x-1 sm:text-3xl")}>
          {title}
        </span>
        <span className="mt-2 block max-w-xl text-sm leading-relaxed text-neutral-500">{description}</span>
      </span>
      <ArrowUpRight className="hidden h-5 w-5 shrink-0 text-neutral-400 transition-transform duration-300 group-hover:-translate-y-1 group-hover:translate-x-1 group-hover:text-neutral-900 sm:block" />
    </Link>
  );
}
