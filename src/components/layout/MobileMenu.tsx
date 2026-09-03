"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { motion } from "motion/react";
import { primaryNav, site } from "@/lib/data/site";
import { Button } from "@/components/ui/Button";
import { ArrowUpRight } from "lucide-react";
import { cn } from "@/lib/utils/cn";

const listVariants = {
  hidden: {},
  visible: { transition: { staggerChildren: 0.06, delayChildren: 0.14 } },
};

const itemVariants = {
  hidden: { opacity: 0, y: 24 },
  visible: {
    opacity: 1,
    y: 0,
    transition: { duration: 0.6, ease: [0.16, 1, 0.3, 1] as const },
  },
};

export function MobileMenu({ onClose }: { onClose: () => void }) {
  const pathname = usePathname();

  return (
    <motion.div
      role="dialog"
      aria-modal="true"
      aria-label="Site navigation"
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      exit={{ opacity: 0 }}
      transition={{ duration: 0.35 }}
      className="fixed inset-0 z-40 flex flex-col bg-ink lg:hidden"
    >
      <div className="grain absolute inset-0" />
      <div className="h-[76px] shrink-0" />
      <motion.nav
        variants={listVariants}
        initial="hidden"
        animate="visible"
        className="container-content flex flex-1 flex-col justify-center gap-1 py-8"
      >
        {primaryNav.map((item, i) => {
          const active = pathname.startsWith(item.href);
          return (
            <motion.div
              key={item.href}
              variants={itemVariants}
              className="overflow-hidden border-b border-line py-3 first:pt-0"
            >
              <Link
                href={item.href}
                onClick={onClose}
                className="flex items-center justify-between py-2"
              >
                <span
                  className={cn(
                    "text-h3 font-medium tracking-tight",
                    active ? "text-gold-bright" : "text-paper",
                  )}
                >
                  <span className="text-eyebrow mr-3 text-paper-faint">
                    0{i + 1}
                  </span>
                  {item.label}
                </span>
                <ArrowUpRight className="size-6 text-paper-faint" aria-hidden />
              </Link>
            </motion.div>
          );
        })}
      </motion.nav>

      <motion.div
        variants={itemVariants}
        initial="hidden"
        animate="visible"
        transition={{ delay: 0.45 }}
        className="container-content flex flex-col gap-6 border-t border-line py-8"
      >
        <Button href="/contact" size="lg" className="w-full sm:w-fit">
          Start a Project
        </Button>
        <div className="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-paper-dim">
          <a href={`mailto:${site.email}`} className="hover:text-paper">
            {site.email}
          </a>
          <a href={`tel:${site.phone.replace(/[^+\d]/g, "")}`} className="hover:text-paper">
            {site.phone}
          </a>
        </div>
      </motion.div>
    </motion.div>
  );
}
