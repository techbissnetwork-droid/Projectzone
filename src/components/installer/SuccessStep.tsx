"use client";

import { motion } from "framer-motion";
import { CheckCircle2, ExternalLink, LayoutDashboard, RotateCcw } from "lucide-react";
import { Button } from "@/components/ui/Button";
import type { InstallerState } from "@/components/installer/types";

export function SuccessStep({ state, onRestart }: { state: InstallerState; onRestart: () => void }) {
  const liveUrl = state.url || "yoursite.techbiss.app";

  return (
    <motion.div
      initial={{ opacity: 0, scale: 0.97 }}
      animate={{ opacity: 1, scale: 1 }}
      transition={{ duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
      className="flex flex-col items-center rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-8 text-center sm:p-12"
    >
      <motion.div
        initial={{ scale: 0 }}
        animate={{ scale: 1 }}
        transition={{ delay: 0.15, type: "spring", stiffness: 260, damping: 18 }}
        className="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/12 text-emerald-400"
      >
        <CheckCircle2 className="h-8 w-8" />
      </motion.div>

      <h1 className="mt-6 text-2xl font-medium tracking-tight text-(--color-ink) sm:text-3xl">Your site is live</h1>
      <p className="mt-3 max-w-md text-sm leading-relaxed text-(--color-ink-muted)">
        {state.siteName || "Your site"} has been deployed successfully and is ready for visitors.
      </p>

      <div className="mt-6 flex items-center gap-2 rounded-full border border-(--color-border-strong) bg-(--color-surface-raised) px-4 py-2.5 text-sm text-(--color-ink)">
        <span className="h-2 w-2 rounded-full bg-emerald-400" />
        {liveUrl}
      </div>

      <div className="mt-9 flex flex-col gap-3 sm:flex-row">
        <Button href="/dashboard/client" variant="secondary" size="lg" icon={<LayoutDashboard className="h-4 w-4" />} iconPosition="left">
          Go to Dashboard
        </Button>
        <Button href="#" variant="outline" size="lg" icon={<ExternalLink className="h-4 w-4" />} iconPosition="left">
          Visit Site
        </Button>
        <Button variant="ghost" size="lg" icon={<RotateCcw className="h-4 w-4" />} iconPosition="left" onClick={onRestart}>
          Install Another
        </Button>
      </div>
    </motion.div>
  );
}
