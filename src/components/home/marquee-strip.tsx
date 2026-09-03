const ITEMS = [
  "Restaurants",
  "Retail",
  "Schools",
  "Hospitals",
  "Hotels",
  "Real Estate",
  "Construction",
  "Agencies",
  "Startups",
  "Service Businesses",
];

export function MarqueeStrip() {
  const loop = [...ITEMS, ...ITEMS];
  return (
    <div className="border-y border-[var(--color-border)] bg-[var(--color-bg-soft)] py-5">
      <div className="scrollbar-none flex overflow-hidden">
        <div className="animate-marquee flex shrink-0 items-center gap-10 pr-10">
          {loop.map((item, i) => (
            <span
              key={i}
              className="flex shrink-0 items-center gap-10 text-[13px] font-medium text-[var(--color-ink-faint)]"
            >
              {item}
              <span className="size-1 rounded-full bg-[var(--color-border-strong)]" />
            </span>
          ))}
        </div>
      </div>
    </div>
  );
}
