import { cn } from "@/lib/utils";
import type { Product } from "@/lib/data/marketplace";

function hashStr(s: string) {
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0;
  return h;
}

export function ThemeVisual({
  product,
  className,
  chrome = true,
}: {
  product: Pick<Product, "slug" | "name" | "gradient">;
  className?: string;
  chrome?: boolean;
}) {
  const h = hashStr(product.slug);
  const blockWidths = [72, 48, 60, 84, 40];
  const heroHeight = 34 + (h % 14);
  const cols = 2 + (h % 3);

  return (
    <div
      className={cn(
        "relative flex h-full w-full flex-col overflow-hidden rounded-[14px] border border-[var(--color-border-strong)]",
        className,
      )}
      style={{
        background: `linear-gradient(155deg, ${product.gradient[0]}22, ${product.gradient[1]}44)`,
      }}
    >
      {chrome && (
        <div className="flex items-center gap-1.5 border-b border-white/10 bg-black/20 px-3 py-2">
          <span className="size-1.5 rounded-full bg-white/30" />
          <span className="size-1.5 rounded-full bg-white/30" />
          <span className="size-1.5 rounded-full bg-white/30" />
          <span className="ml-2 h-3.5 flex-1 max-w-[140px] rounded-full bg-white/10" />
        </div>
      )}

      <div className="flex flex-1 flex-col gap-2.5 p-3.5">
        <div
          className="w-full flex-shrink-0 rounded-md"
          style={{
            height: `${heroHeight}%`,
            background: `linear-gradient(135deg, ${product.gradient[0]}aa, ${product.gradient[1]}aa)`,
          }}
        />
        <div className="flex flex-1 gap-2">
          <div
            className="flex flex-col gap-2 rounded-md bg-white/[0.06] p-2"
            style={{ width: `${28 + (h % 3) * 4}%` }}
          >
            {[0, 1, 2].map((i) => (
              <span key={i} className="h-1.5 rounded-full bg-white/15" style={{ width: `${60 - i * 12}%` }} />
            ))}
          </div>
          <div className="grid flex-1 gap-2" style={{ gridTemplateColumns: `repeat(${cols}, 1fr)` }}>
            {Array.from({ length: cols * 2 }).map((_, i) => (
              <div
                key={i}
                className="rounded-md bg-white/[0.05]"
                style={{ opacity: 1 - (i % cols) * 0.12 }}
              />
            ))}
          </div>
        </div>
        <div className="flex flex-shrink-0 gap-1.5">
          {blockWidths.slice(0, 3).map((w, i) => (
            <span key={i} className="h-1.5 rounded-full bg-white/10" style={{ width: `${w * 0.5}px` }} />
          ))}
        </div>
      </div>

      <div
        aria-hidden
        className="pointer-events-none absolute inset-0"
        style={{ boxShadow: `inset 0 0 60px ${product.gradient[0]}22` }}
      />
    </div>
  );
}
