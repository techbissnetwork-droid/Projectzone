"use client";

import { useState } from "react";
import { CreditCard, Info } from "lucide-react";
import { Card } from "@/components/dashboard/page-header";

export function PaymentMethodCard() {
  const [showNote, setShowNote] = useState(false);

  return (
    <Card className="flex flex-col gap-3">
      <div className="flex items-center gap-2 text-[var(--color-ink-faint)]">
        <CreditCard className="size-4" strokeWidth={1.75} />
        <span className="font-mono-label text-[10.5px] uppercase">Payment method</span>
      </div>
      <p className="text-[20px] font-medium tracking-wide text-[var(--color-ink)]">Visa •••• 4242</p>
      <p className="text-[13px] text-[var(--color-ink-muted)]">Expires 08/2028</p>
      <button
        onClick={() => setShowNote((v) => !v)}
        className="mt-1 inline-flex w-fit items-center gap-1.5 rounded-full border border-[var(--color-border-strong)] px-4 py-2 text-[12.5px] font-medium text-[var(--color-ink)] transition-colors hover:border-[var(--color-ink)]"
      >
        Update payment method
      </button>
      {showNote && (
        <p className="flex items-start gap-2 rounded-lg bg-white/[0.04] p-3 text-[12px] leading-relaxed text-[var(--color-ink-faint)]">
          <Info className="mt-0.5 size-3.5 shrink-0" strokeWidth={1.75} />
          Card updates are handled through our secure billing partner and open in a dedicated,
          PCI-compliant flow — this preview doesn&rsquo;t collect card details directly.
        </p>
      )}
    </Card>
  );
}
