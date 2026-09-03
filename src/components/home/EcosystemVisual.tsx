"use client";

import { useEffect, useRef, useState } from "react";
import { motion, useMotionValue, useSpring, AnimatePresence } from "motion/react";
import {
  Building2,
  Fingerprint,
  Globe,
  Smartphone,
  Server,
  Mail,
  ShieldCheck,
  CreditCard,
  TrendingUp,
  type LucideProps,
} from "lucide-react";
import { cn } from "@/lib/utils/cn";

const nodes: {
  key: string;
  label: string;
  desc: string;
  icon: React.ComponentType<LucideProps>;
}[] = [
  { key: "business", label: "Business", desc: "Where it starts — your operation, today.", icon: Building2 },
  { key: "domain", label: "Domain", desc: "Your identity online, secured.", icon: Fingerprint },
  { key: "website", label: "Website", desc: "Your storefront to the world.", icon: Globe },
  { key: "app", label: "App", desc: "In your customer's pocket.", icon: Smartphone },
  { key: "hosting", label: "Hosting", desc: "Infrastructure that never sleeps.", icon: Server },
  { key: "email", label: "Email", desc: "Professional, on your own domain.", icon: Mail },
  { key: "security", label: "Security", desc: "Protected, by default.", icon: ShieldCheck },
  { key: "payments", label: "Payments", desc: "Get paid, anywhere.", icon: CreditCard },
  { key: "growth", label: "Growth", desc: "The compounding result.", icon: TrendingUp },
];

function Connector({ active, index }: { active: boolean; index: number }) {
  const [isDesktop, setIsDesktop] = useState(false);

  useEffect(() => {
    const mq = window.matchMedia("(min-width: 1280px)");
    const update = () => setIsDesktop(mq.matches);
    update();
    mq.addEventListener("change", update);
    return () => mq.removeEventListener("change", update);
  }, []);

  return (
    <div
      className={cn(
        "relative shrink-0 overflow-hidden bg-line-strong transition-colors duration-500",
        "mx-auto h-6 w-px sm:h-8 xl:mx-0 xl:h-px xl:w-full xl:min-w-6 xl:flex-1",
        active && "bg-gold/40",
      )}
    >
      <motion.span
        className={
          isDesktop
            ? "absolute inset-y-0 left-0 w-1/3 bg-gradient-to-r from-transparent via-gold-bright to-transparent"
            : "absolute inset-x-0 top-0 h-1/3 bg-gradient-to-b from-transparent via-gold-bright to-transparent"
        }
        key={isDesktop ? "h" : "v"}
        animate={isDesktop ? { x: ["-100%", "220%"] } : { y: ["-100%", "220%"] }}
        transition={{
          duration: 2.6,
          repeat: Infinity,
          ease: "linear",
          delay: (index % 5) * 0.28,
        }}
      />
    </div>
  );
}

export function EcosystemVisual() {
  const [active, setActive] = useState<number | null>(null);
  const [autoIndex, setAutoIndex] = useState(0);
  const idleTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const [userIdle, setUserIdle] = useState(true);

  const panelRef = useRef<HTMLDivElement>(null);
  const rx = useMotionValue(0);
  const ry = useMotionValue(0);
  const srx = useSpring(rx, { stiffness: 120, damping: 16 });
  const sry = useSpring(ry, { stiffness: 120, damping: 16 });

  useEffect(() => {
    if (!userIdle) return;
    const id = setInterval(() => {
      setAutoIndex((i) => (i + 1) % nodes.length);
    }, 1900);
    return () => clearInterval(id);
  }, [userIdle]);

  const wake = (i: number) => {
    setUserIdle(false);
    setActive(i);
    if (idleTimer.current) clearTimeout(idleTimer.current);
  };

  const sleep = () => {
    setActive(null);
    if (idleTimer.current) clearTimeout(idleTimer.current);
    idleTimer.current = setTimeout(() => setUserIdle(true), 1600);
  };

  const onPanelMove = (e: React.MouseEvent) => {
    if (!window.matchMedia("(pointer: fine)").matches) return;
    const rect = panelRef.current?.getBoundingClientRect();
    if (!rect) return;
    const px = (e.clientX - rect.left) / rect.width - 0.5;
    const py = (e.clientY - rect.top) / rect.height - 0.5;
    ry.set(px * 3.2);
    rx.set(py * -3.2);
  };

  const onPanelLeave = () => {
    rx.set(0);
    ry.set(0);
  };

  const activeIndex = active ?? (userIdle ? autoIndex : null);

  return (
    <div
      ref={panelRef}
      onMouseMove={onPanelMove}
      onMouseLeave={onPanelLeave}
      style={{ perspective: 1200 }}
      className="relative"
    >
      <motion.div
        style={{ rotateX: srx, rotateY: sry, transformStyle: "preserve-3d" }}
        className="grain relative rounded-2xl border border-line bg-gradient-to-b from-ink-raised to-ink-raised-2 px-5 py-10 sm:px-10 sm:py-14"
      >
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 rounded-2xl opacity-60"
          style={{
            background:
              "radial-gradient(60% 50% at 50% 0%, rgba(201,168,118,0.08), transparent)",
          }}
        />

        <div className="no-scrollbar relative mx-auto flex max-w-md flex-col items-stretch gap-0 xl:mx-0 xl:max-w-none xl:flex-row xl:items-center xl:overflow-x-auto">
          {nodes.map((node, i) => {
            const isActive = activeIndex === i;
            const Icon = node.icon;
            return (
              <div key={node.key} className="contents">
                <div
                  className="group relative flex items-center justify-center py-1.5 xl:py-0"
                  onMouseEnter={() => wake(i)}
                  onMouseLeave={sleep}
                  onClick={() => wake(i)}
                >
                  <motion.button
                    type="button"
                    aria-label={`${node.label}: ${node.desc}`}
                    animate={{
                      scale: isActive ? 1.08 : 1,
                      borderColor: isActive
                        ? "var(--color-gold)"
                        : "var(--color-line-strong)",
                    }}
                    transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
                    className={cn(
                      "relative flex w-full shrink-0 flex-row items-center gap-3 rounded-xl border bg-ink-raised-2/80 px-4 py-3 text-left backdrop-blur-sm xl:w-[88px] xl:flex-col xl:items-center xl:gap-2.5 xl:px-2.5 xl:py-4 xl:text-center",
                    )}
                  >
                    <span
                      className={cn(
                        "flex size-9 shrink-0 items-center justify-center rounded-full border transition-colors duration-300",
                        isActive
                          ? "border-gold/60 bg-gold-dim text-gold-bright"
                          : "border-line-strong text-paper-dim",
                      )}
                    >
                      <Icon className="size-4" aria-hidden />
                    </span>
                    <span
                      className={cn(
                        "text-[0.8rem] font-medium tracking-tight transition-colors duration-300 xl:text-xs",
                        isActive ? "text-paper" : "text-paper-dim",
                      )}
                    >
                      {node.label}
                    </span>
                  </motion.button>

                  <AnimatePresence>
                    {isActive && (
                      <motion.div
                        initial={{ opacity: 0, y: 8, scale: 0.96 }}
                        animate={{ opacity: 1, y: 0, scale: 1 }}
                        exit={{ opacity: 0, y: 4, scale: 0.96 }}
                        transition={{ duration: 0.25 }}
                        className="pointer-events-none absolute -top-3 left-1/2 z-20 hidden w-44 -translate-x-1/2 -translate-y-full rounded-lg border border-line-strong bg-ink-raised-3 px-3 py-2.5 text-center shadow-xl xl:block"
                      >
                        <p className="text-xs leading-snug text-paper-dim">
                          {node.desc}
                        </p>
                        <span className="absolute left-1/2 top-full h-2 w-2 -translate-x-1/2 -translate-y-1/2 rotate-45 border-b border-r border-line-strong bg-ink-raised-3" />
                      </motion.div>
                    )}
                  </AnimatePresence>
                </div>

                {i < nodes.length - 1 && <Connector active={isActive} index={i} />}
              </div>
            );
          })}
        </div>
      </motion.div>
    </div>
  );
}
