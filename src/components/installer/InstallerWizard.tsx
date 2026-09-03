"use client";

import * as React from "react";
import { useSearchParams } from "next/navigation";
import { AnimatePresence, motion } from "framer-motion";
import { Stepper } from "@/components/ui/Stepper";
import { WelcomeStep } from "@/components/installer/WelcomeStep";
import { DetectStep } from "@/components/installer/DetectStep";
import { InstallTypeStep } from "@/components/installer/InstallTypeStep";
import { ConfigStep } from "@/components/installer/ConfigStep";
import { DeployStep } from "@/components/installer/DeployStep";
import { SuccessStep } from "@/components/installer/SuccessStep";
import { initialInstallerState, type InstallerState } from "@/components/installer/types";
import { products } from "@/lib/data/products";

const stepLabels = ["Environment", "Install Type", "Configuration", "Deploy"];

export function InstallerWizard() {
  const params = useSearchParams();
  const productSlug = params.get("product");
  const product = productSlug ? products.find((p) => p.slug === productSlug) : undefined;

  const [phase, setPhase] = React.useState<"welcome" | "wizard" | "success">("welcome");
  const [step, setStep] = React.useState(0);
  const [state, setState] = React.useState<InstallerState>({
    ...initialInstallerState,
    theme: product?.slug ?? null,
  });

  function update(patch: Partial<InstallerState>) {
    setState((prev) => ({ ...prev, ...patch }));
  }

  function restart() {
    setState({ ...initialInstallerState, theme: product?.slug ?? null });
    setStep(0);
    setPhase("welcome");
  }

  return (
    <div className="mx-auto max-w-2xl">
      {phase === "welcome" && <WelcomeStep product={product} onStart={() => setPhase("wizard")} />}

      {phase === "wizard" && (
        <>
          <Stepper steps={stepLabels} current={step} />
          <div className="mt-8">
            <AnimatePresence mode="wait">
              <motion.div
                key={step}
                initial={{ opacity: 0, x: 16 }}
                animate={{ opacity: 1, x: 0 }}
                exit={{ opacity: 0, x: -16 }}
                transition={{ duration: 0.25, ease: [0.16, 1, 0.3, 1] }}
              >
                {step === 0 && (
                  <DetectStep
                    url={state.url}
                    setUrl={(url) => update({ url })}
                    detected={state.detected}
                    onDetected={() => update({ detected: true })}
                    onContinue={() => setStep(1)}
                  />
                )}
                {step === 1 && (
                  <InstallTypeStep
                    installType={state.installType}
                    setInstallType={(installType) => update({ installType })}
                    onBack={() => setStep(0)}
                    onContinue={() => setStep(2)}
                  />
                )}
                {step === 2 && (
                  <ConfigStep state={state} update={update} onBack={() => setStep(1)} onContinue={() => setStep(3)} />
                )}
                {step === 3 && (
                  <DeployStep state={state} onBack={() => setStep(2)} onComplete={() => setPhase("success")} />
                )}
              </motion.div>
            </AnimatePresence>
          </div>
        </>
      )}

      {phase === "success" && <SuccessStep state={state} onRestart={restart} />}
    </div>
  );
}
