"use client";

import * as React from "react";
import { useSearchParams } from "next/navigation";
import { motion, AnimatePresence } from "framer-motion";
import { ArrowLeft, ArrowRight, CheckCircle2, CreditCard, Loader2, Lock, Rocket } from "lucide-react";
import { Stepper } from "@/components/ui/Stepper";
import { Button } from "@/components/ui/Button";
import { Input, Label } from "@/components/ui/Field";
import { Card } from "@/components/ui/Card";
import { useCart } from "@/lib/cart-context";
import { products } from "@/lib/data/products";
import { formatCurrency, sleep } from "@/lib/utils";

const steps = ["Account", "Payment", "Confirmation"];

export function CheckoutFlow() {
  const params = useSearchParams();
  const cart = useCart();
  const directSlug = params.get("product");
  const directProduct = directSlug ? products.find((p) => p.slug === directSlug) : undefined;

  const orderItems = directProduct ? [{ product: directProduct, qty: 1 }] : cart.items;
  const total = orderItems.reduce((sum, i) => sum + i.product.price * i.qty, 0);

  const [step, setStep] = React.useState(0);
  const [processing, setProcessing] = React.useState(false);
  const [account, setAccount] = React.useState({ name: "", email: "", company: "" });
  const [card, setCard] = React.useState({ number: "", expiry: "", cvc: "" });
  const [errors, setErrors] = React.useState<Record<string, string>>({});

  const [orderId, setOrderId] = React.useState("");

  function validateAccount() {
    const next: Record<string, string> = {};
    if (!account.name.trim()) next.name = "Full name is required";
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(account.email)) next.email = "Enter a valid email address";
    setErrors(next);
    return Object.keys(next).length === 0;
  }

  function validateCard() {
    const next: Record<string, string> = {};
    if (card.number.replace(/\s/g, "").length < 16) next.number = "Enter a valid 16-digit card number";
    if (!/^\d{2}\s?\/\s?\d{2}$/.test(card.expiry)) next.expiry = "Use MM / YY format";
    if (card.cvc.length < 3) next.cvc = "Enter a valid CVC";
    setErrors(next);
    return Object.keys(next).length === 0;
  }

  async function handlePay() {
    if (!validateCard()) return;
    setProcessing(true);
    await sleep(1400);
    setProcessing(false);
    setOrderId(`TB-${Math.floor(100000 + Math.random() * 900000)}`);
    setStep(2);
  }

  if (orderItems.length === 0 && step < 2) {
    return (
      <Card className="text-center">
        <p className="text-lg font-medium text-(--color-ink)">Nothing to check out yet</p>
        <p className="mt-2 text-sm text-(--color-ink-muted)">Add a product from the marketplace to continue.</p>
        <Button href="/marketplace" variant="secondary" className="mt-6">
          Browse Marketplace
        </Button>
      </Card>
    );
  }

  return (
    <div>
      <Stepper steps={steps} current={step} />

      <div className="mt-10 grid grid-cols-1 gap-8 lg:grid-cols-12">
        <div className="lg:col-span-7">
          <AnimatePresence mode="wait">
            {step === 0 && (
              <motion.div
                key="account"
                initial={{ opacity: 0, x: 16 }}
                animate={{ opacity: 1, x: 0 }}
                exit={{ opacity: 0, x: -16 }}
                transition={{ duration: 0.25 }}
              >
                <Card>
                  <h2 className="text-lg font-medium text-(--color-ink)">Account details</h2>
                  <p className="mt-1 text-sm text-(--color-ink-muted)">
                    We&apos;ll use this to set up your client dashboard and license.
                  </p>
                  <div className="mt-6 flex flex-col gap-4">
                    <div>
                      <Label htmlFor="name" required>
                        Full name
                      </Label>
                      <Input
                        id="name"
                        value={account.name}
                        onChange={(e) => setAccount({ ...account, name: e.target.value })}
                        placeholder="Jordan Blake"
                        error={errors.name}
                      />
                    </div>
                    <div>
                      <Label htmlFor="email" required>
                        Email address
                      </Label>
                      <Input
                        id="email"
                        type="email"
                        value={account.email}
                        onChange={(e) => setAccount({ ...account, email: e.target.value })}
                        placeholder="you@company.com"
                        error={errors.email}
                      />
                    </div>
                    <div>
                      <Label htmlFor="company">Company (optional)</Label>
                      <Input
                        id="company"
                        value={account.company}
                        onChange={(e) => setAccount({ ...account, company: e.target.value })}
                        placeholder="Acme Inc."
                      />
                    </div>
                  </div>
                  <Button
                    variant="secondary"
                    size="lg"
                    className="mt-8 w-full"
                    icon={<ArrowRight className="h-4 w-4" />}
                    onClick={() => validateAccount() && setStep(1)}
                  >
                    Continue to Payment
                  </Button>
                </Card>
              </motion.div>
            )}

            {step === 1 && (
              <motion.div
                key="payment"
                initial={{ opacity: 0, x: 16 }}
                animate={{ opacity: 1, x: 0 }}
                exit={{ opacity: 0, x: -16 }}
                transition={{ duration: 0.25 }}
              >
                <Card>
                  <h2 className="flex items-center gap-2 text-lg font-medium text-(--color-ink)">
                    <Lock className="h-4 w-4 text-(--color-accent-2)" /> Secure payment
                  </h2>
                  <p className="mt-1 text-sm text-(--color-ink-muted)">Test mode — no real charge will be made.</p>
                  <div className="mt-6 flex flex-col gap-4">
                    <div>
                      <Label htmlFor="cardnum" required>
                        Card number
                      </Label>
                      <div className="relative">
                        <CreditCard className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-(--color-ink-faint)" />
                        <Input
                          id="cardnum"
                          className="pl-10"
                          value={card.number}
                          onChange={(e) =>
                            setCard({
                              ...card,
                              number: e.target.value
                                .replace(/[^\d]/g, "")
                                .slice(0, 16)
                                .replace(/(\d{4})(?=\d)/g, "$1 "),
                            })
                          }
                          placeholder="4242 4242 4242 4242"
                          error={errors.number}
                        />
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <Label htmlFor="expiry" required>
                          Expiry
                        </Label>
                        <Input
                          id="expiry"
                          value={card.expiry}
                          onChange={(e) => {
                            const digits = e.target.value.replace(/[^\d]/g, "").slice(0, 4);
                            const formatted = digits.length > 2 ? `${digits.slice(0, 2)} / ${digits.slice(2)}` : digits;
                            setCard({ ...card, expiry: formatted });
                          }}
                          placeholder="MM / YY"
                          error={errors.expiry}
                        />
                      </div>
                      <div>
                        <Label htmlFor="cvc" required>
                          CVC
                        </Label>
                        <Input
                          id="cvc"
                          value={card.cvc}
                          onChange={(e) => setCard({ ...card, cvc: e.target.value.replace(/[^\d]/g, "").slice(0, 4) })}
                          placeholder="123"
                          error={errors.cvc}
                        />
                      </div>
                    </div>
                  </div>
                  <div className="mt-8 flex gap-3">
                    <Button variant="outline" size="lg" icon={<ArrowLeft className="h-4 w-4" />} iconPosition="left" onClick={() => setStep(0)}>
                      Back
                    </Button>
                    <Button variant="secondary" size="lg" className="flex-1" disabled={processing} onClick={handlePay}>
                      {processing ? (
                        <span className="flex items-center gap-2">
                          <Loader2 className="h-4 w-4 animate-spin" /> Processing...
                        </span>
                      ) : (
                        `Pay ${formatCurrency(total)}`
                      )}
                    </Button>
                  </div>
                </Card>
              </motion.div>
            )}

            {step === 2 && (
              <motion.div
                key="confirm"
                initial={{ opacity: 0, scale: 0.97 }}
                animate={{ opacity: 1, scale: 1 }}
                transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
              >
                <Card className="text-center">
                  <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500/12 text-emerald-400">
                    <CheckCircle2 className="h-7 w-7" />
                  </div>
                  <h2 className="mt-5 text-xl font-medium text-(--color-ink)">Purchase complete</h2>
                  <p className="mt-2 text-sm text-(--color-ink-muted)">
                    Order <span className="text-(--color-ink)">{orderId}</span> confirmed. A receipt has been sent to{" "}
                    {account.email || "your email"}.
                  </p>
                  <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                    <Button
                      href={`/installer${directProduct ? `?product=${directProduct.slug}` : ""}`}
                      variant="secondary"
                      size="lg"
                      className="flex-1"
                      icon={<Rocket className="h-4 w-4" />}
                      iconPosition="left"
                    >
                      Launch Advanced Installer
                    </Button>
                    <Button href="/dashboard/client" variant="outline" size="lg" className="flex-1">
                      Go to Dashboard
                    </Button>
                  </div>
                </Card>
              </motion.div>
            )}
          </AnimatePresence>
        </div>

        <div className="lg:col-span-5">
          <Card className="lg:sticky lg:top-24">
            <h3 className="text-sm font-medium uppercase tracking-wide text-(--color-ink-faint)">Order summary</h3>
            <div className="mt-4 flex flex-col gap-4">
              {orderItems.map(({ product }) => (
                <div key={product.slug} className="flex items-center gap-3">
                  <div
                    className="flex h-10 w-10 shrink-0 items-center justify-center rounded-(--radius-sm) text-sm font-medium text-white"
                    style={{ background: `linear-gradient(135deg, ${product.gradient[0]}, ${product.gradient[1]})` }}
                  >
                    {product.name.slice(0, 1)}
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm text-(--color-ink)">{product.name}</p>
                    <p className="text-xs text-(--color-ink-faint)">Lifetime license</p>
                  </div>
                  <span className="text-sm text-(--color-ink)">{formatCurrency(product.price)}</span>
                </div>
              ))}
            </div>
            <div className="mt-6 flex items-center justify-between border-t border-(--color-border) pt-4 text-base font-medium text-(--color-ink)">
              <span>Total</span>
              <span>{formatCurrency(total)}</span>
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
}
