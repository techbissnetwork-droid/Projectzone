"use client";

import { useState } from "react";
import { Check, CreditCard, Loader2, ShieldCheck } from "lucide-react";
import { Product } from "@/lib/data/marketplace";
import { formatPrice, cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";

const steps = ["Review", "Business Details", "Payment", "Complete"];

const inputClass =
  "w-full rounded-lg border border-line-dark-strong bg-ink-950/60 px-4 py-3 text-[14px] text-paper-50 placeholder:text-paper-50/30 outline-none transition-colors focus:border-gold-500/60";

export function CheckoutFlow({ product }: { product: Product }) {
  const [step, setStep] = useState(0);
  const [processing, setProcessing] = useState(false);

  const next = () => {
    if (step === 2) {
      setProcessing(true);
      setTimeout(() => {
        setProcessing(false);
        setStep(3);
      }, 1200);
      return;
    }
    setStep((s) => Math.min(s + 1, steps.length - 1));
  };

  if (step === 3) {
    return (
      <div className="flex flex-col items-center gap-6 rounded-3xl border border-gold-500/25 bg-gradient-to-br from-ink-850 to-ink-900 p-10 text-center sm:p-14">
        <span className="flex size-16 items-center justify-center rounded-full bg-gold-400 text-ink-950">
          <Check className="size-7" strokeWidth={2.5} />
        </span>
        <div>
          <h2 className="text-[26px] font-medium text-paper-50">Purchase complete.</h2>
          <p className="mt-3 max-w-sm text-[14px] leading-relaxed text-paper-50/55">
            {product.name} has been added to My Products. Next, brand it as
            your own — or hand it to TECHBISS for a fully custom build-out.
          </p>
        </div>
        <div className="flex flex-wrap items-center justify-center gap-3">
          <Button href={`/dashboard/brand-studio/${product.slug}`} arrow>
            Customize in Brand Studio
          </Button>
          <Button href="/dashboard/products" variant="ghost">
            View My Products
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div>
      <div className="flex items-center gap-2">
        {steps.slice(0, 3).map((s, i) => (
          <div key={s} className="flex flex-1 items-center gap-2">
            <div
              className={cn(
                "flex size-7 shrink-0 items-center justify-center rounded-full text-[12px] font-medium transition-colors",
                i < step
                  ? "bg-gold-400 text-ink-950"
                  : i === step
                  ? "border border-gold-400 text-gold-400"
                  : "border border-line-dark-strong text-paper-50/30"
              )}
            >
              {i < step ? <Check className="size-3.5" /> : i + 1}
            </div>
            <span
              className={cn(
                "hidden text-[12.5px] font-medium sm:block",
                i <= step ? "text-paper-50/80" : "text-paper-50/30"
              )}
            >
              {s}
            </span>
            {i < 2 && <span className="mx-2 h-px flex-1 bg-line-dark" />}
          </div>
        ))}
      </div>

      <div className="mt-10 grid gap-10 lg:grid-cols-[1fr_320px]">
        <div>
          {step === 0 && (
            <div className="flex flex-col gap-5">
              <h2 className="text-[20px] font-medium text-paper-50">Review your order</h2>
              <div className="flex items-center gap-4 rounded-xl border border-line-dark bg-ink-900/40 p-5">
                <div
                  className="size-14 shrink-0 rounded-lg"
                  style={{ background: `linear-gradient(135deg, ${product.accent}44, transparent)` }}
                />
                <div className="flex-1">
                  <div className="text-[15px] font-medium text-paper-50">{product.name}</div>
                  <div className="text-[12.5px] text-paper-50/45">{product.license}</div>
                </div>
                <div className="text-[15px] font-medium text-paper-50">
                  {formatPrice(product.priceCents)}
                </div>
              </div>
              <ul className="flex flex-col gap-2 text-[13.5px] text-paper-50/55">
                <li className="flex items-center gap-2">
                  <Check className="size-3.5 text-gold-400" /> {product.updates}
                </li>
                <li className="flex items-center gap-2">
                  <Check className="size-3.5 text-gold-400" /> {product.support}
                </li>
              </ul>
            </div>
          )}

          {step === 1 && (
            <div className="flex flex-col gap-5">
              <h2 className="text-[20px] font-medium text-paper-50">Business details</h2>
              <div className="grid gap-4 sm:grid-cols-2">
                <input className={inputClass} placeholder="Business name" required />
                <input className={inputClass} placeholder="Work email" type="email" required />
                <input className={inputClass} placeholder="Phone number" />
                <input className={inputClass} placeholder="Industry" />
              </div>
            </div>
          )}

          {step === 2 && (
            <div className="flex flex-col gap-5">
              <h2 className="text-[20px] font-medium text-paper-50">Payment</h2>
              <div className="flex items-center gap-2 rounded-lg border border-line-dark bg-ink-900/40 px-4 py-3 text-[12.5px] text-paper-50/50">
                <ShieldCheck className="size-4 text-gold-400" />
                Payments are securely processed. Card details are never
                stored on TECHBISS servers.
              </div>
              <div className="grid gap-4">
                <div className="relative">
                  <CreditCard className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-paper-50/35" />
                  <input
                    className={cn(inputClass, "pl-11")}
                    placeholder="Card number"
                    required
                  />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <input className={inputClass} placeholder="MM / YY" required />
                  <input className={inputClass} placeholder="CVC" required />
                </div>
              </div>
            </div>
          )}
        </div>

        <div className="h-fit rounded-xl border border-line-dark bg-ink-900/40 p-6">
          <div className="text-[13px] text-paper-50/45">Order Total</div>
          <div className="mt-2 text-[26px] font-medium text-paper-50">
            {formatPrice(product.priceCents)}
          </div>
          <Button onClick={next} className="mt-6 w-full justify-center" disabled={processing}>
            {processing ? (
              <>
                <Loader2 className="size-4 animate-spin" />
                Processing
              </>
            ) : step === 2 ? (
              "Complete Purchase"
            ) : (
              "Continue"
            )}
          </Button>
        </div>
      </div>
    </div>
  );
}
