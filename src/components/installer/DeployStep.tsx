"use client";

import * as React from "react";
import { motion } from "framer-motion";
import { ArrowLeft, Rocket } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { ProgressBar } from "@/components/ui/ProgressBar";
import { products } from "@/lib/data/products";
import type { InstallerState } from "@/components/installer/types";

const deployLog = [
  "Provisioning environment",
  "Installing core files",
  "Configuring database",
  "Applying theme & configuration",
  "Setting up SSL certificate",
  "Optimizing assets",
  "Running final health checks",
];

export function DeployStep({
  state,
  onBack,
  onComplete,
}: {
  state: InstallerState;
  onBack: () => void;
  onComplete: () => void;
}) {
  const [deploying, setDeploying] = React.useState(false);
  const [logIndex, setLogIndex] = React.useState(0);
  const theme = products.find((p) => p.slug === state.theme);

  React.useEffect(() => {
    if (!deploying) return;
    if (logIndex >= deployLog.length) {
      const t = setTimeout(onComplete, 500);
      return () => clearTimeout(t);
    }
    const t = setTimeout(() => setLogIndex((i) => i + 1), 650);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [deploying, logIndex]);

  const progress = deploying ? Math.round((logIndex / deployLog.length) * 100) : 0;

  const summary: [string, string][] = [
    ["Domain", state.url || "—"],
    ["Install type", state.installType ? state.installType[0].toUpperCase() + state.installType.slice(1) : "—"],
    ["Site name", state.siteName || "—"],
    ["Admin", state.adminEmail || "—"],
    ["Timezone", state.timezone],
    ["Theme", theme?.name ?? "—"],
  ];

  return (
    <div className="rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6 sm:p-8">
      {!deploying ? (
        <>
          <h2 className="text-lg font-medium text-(--color-ink)">Review &amp; deploy</h2>
          <p className="mt-1 text-sm text-(--color-ink-muted)">Confirm your configuration before we go live.</p>

          <dl className="mt-6 divide-y divide-(--color-border) rounded-(--radius-md) border border-(--color-border)">
            {summary.map(([label, value]) => (
              <div key={label} className="flex items-center justify-between px-4 py-3">
                <dt className="text-sm text-(--color-ink-faint)">{label}</dt>
                <dd className="text-sm font-medium text-(--color-ink)">{value}</dd>
              </div>
            ))}
          </dl>

          <div className="mt-8 flex gap-3">
            <Button variant="outline" size="lg" icon={<ArrowLeft className="h-4 w-4" />} iconPosition="left" onClick={onBack}>
              Back
            </Button>
            <Button
              variant="secondary"
              size="lg"
              className="flex-1"
              icon={<Rocket className="h-4 w-4" />}
              iconPosition="left"
              onClick={() => setDeploying(true)}
            >
              Deploy Now
            </Button>
          </div>
        </>
      ) : (
        <>
          <h2 className="text-lg font-medium text-(--color-ink)">Deploying your site...</h2>
          <p className="mt-1 text-sm text-(--color-ink-muted)">This usually takes under a minute. Don&apos;t close this window.</p>

          <div className="mt-6">
            <div className="mb-2 flex items-center justify-between text-xs text-(--color-ink-faint)">
              <span>Progress</span>
              <span>{progress}%</span>
            </div>
            <ProgressBar value={progress} />
          </div>

          <div className="mt-6 flex flex-col gap-2 rounded-(--radius-md) border border-(--color-border) bg-(--color-canvas) p-4 font-mono text-xs">
            {deployLog.slice(0, logIndex + 1).map((line, i) => (
              <motion.div
                key={line}
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                className="flex items-center gap-2 text-(--color-ink-muted)"
              >
                <span className="text-(--color-accent-2)">$</span>
                {line}
                {i === logIndex && i < deployLog.length ? (
                  <span className="text-(--color-ink-faint)">
                    {i < deployLog.length - 1 || logIndex < deployLog.length ? "..." : ""}
                  </span>
                ) : (
                  <span className="text-emerald-400">done</span>
                )}
              </motion.div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
