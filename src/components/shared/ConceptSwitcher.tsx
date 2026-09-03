import Link from "next/link";
import { concepts } from "@/lib/site-data";
import { cn } from "@/lib/cn";

/**
 * Small cross-concept navigator. Each concept renders this with its own
 * color treatment via `className` — it never carries concept-specific
 * styling itself so it stays visually neutral wherever it's dropped.
 */
export function ConceptSwitcher({
  active,
  className,
  linkClassName,
  activeLinkClassName,
}: {
  active: "concept-1" | "concept-2" | "concept-3";
  className?: string;
  linkClassName?: string;
  activeLinkClassName?: string;
}) {
  return (
    <nav aria-label="Switch design concept" className={cn("flex flex-wrap items-center gap-2", className)}>
      <Link href="/" className={cn(linkClassName, "opacity-80 hover:opacity-100")}>
        All Concepts
      </Link>
      {concepts.map((c) => (
        <Link
          key={c.slug}
          href={`/${c.slug}`}
          aria-current={active === c.slug ? "page" : undefined}
          className={cn(linkClassName, active === c.slug && activeLinkClassName)}
        >
          {c.name}
        </Link>
      ))}
    </nav>
  );
}
