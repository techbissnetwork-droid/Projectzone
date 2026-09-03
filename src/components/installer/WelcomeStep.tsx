import { Rocket, ShieldCheck, Timer, Wand2 } from "lucide-react";
import { Button } from "@/components/ui/Button";
import type { Product } from "@/lib/types";

const perks = [
  { icon: Wand2, label: "Automatic URL & environment detection" },
  { icon: ShieldCheck, label: "Clean install or safe existing-site migration" },
  { icon: Timer, label: "Typically live in under 10 minutes" },
];

export function WelcomeStep({ product, onStart }: { product?: Product; onStart: () => void }) {
  return (
    <div className="flex flex-col items-center rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-8 text-center sm:p-12">
      <div className="flex h-14 w-14 items-center justify-center rounded-(--radius-lg) bg-[linear-gradient(135deg,#4b5bff,#17c3ff)] text-white">
        <Rocket className="h-6 w-6" />
      </div>
      <h1 className="mt-6 text-2xl font-medium tracking-tight text-(--color-ink) sm:text-3xl">
        Advanced Installer
      </h1>
      <p className="mt-3 max-w-md text-sm leading-relaxed text-(--color-ink-muted) sm:text-base">
        {product
          ? `Let's get ${product.name} deployed. This wizard will detect your environment, configure your site and deploy it — automatically.`
          : "Deploy any TECHBISS marketplace product to your domain in minutes — with automatic detection, safe migration and guided configuration."}
      </p>

      {product && (
        <div className="mt-6 flex items-center gap-3 rounded-(--radius-md) border border-(--color-border) bg-(--color-surface-raised) px-4 py-3">
          <div
            className="flex h-9 w-9 items-center justify-center rounded-(--radius-sm) text-sm font-medium text-white"
            style={{ background: `linear-gradient(135deg, ${product.gradient[0]}, ${product.gradient[1]})` }}
          >
            {product.name.slice(0, 1)}
          </div>
          <div className="text-left">
            <p className="text-sm font-medium text-(--color-ink)">{product.name}</p>
            <p className="text-xs text-(--color-ink-faint)">Ready to deploy</p>
          </div>
        </div>
      )}

      <div className="mt-8 grid grid-cols-1 gap-3 sm:grid-cols-3">
        {perks.map(({ icon: Icon, label }) => (
          <div key={label} className="flex flex-col items-center gap-2 rounded-(--radius-md) border border-(--color-border) p-4">
            <Icon className="h-4 w-4 text-(--color-accent-2)" />
            <span className="text-xs text-(--color-ink-muted)">{label}</span>
          </div>
        ))}
      </div>

      <Button variant="secondary" size="lg" className="mt-9" onClick={onStart}>
        Begin Installation
      </Button>
    </div>
  );
}
