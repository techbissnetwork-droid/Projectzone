"use client";

import Link from "next/link";
import { motion } from "framer-motion";
import { X } from "lucide-react";

const LINKS = [
  { label: "Services", href: "/services" },
  { label: "Solutions", href: "/solutions" },
  { label: "Marketplace", href: "/marketplace" },
  { label: "Work", href: "/work" },
  { label: "Process", href: "/process" },
  { label: "About", href: "/about" },
  { label: "Contact", href: "/contact" },
];

const EASE = [0.16, 1, 0.3, 1] as const;

export function MobileMenu({ onClose }: { onClose: () => void }) {
  return (
    <motion.div
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      exit={{ opacity: 0, transition: { duration: 0.25, ease: EASE } }}
      transition={{ duration: 0.35, ease: EASE }}
      className="fixed inset-0 z-[60] flex flex-col bg-[var(--color-bg)] lg:hidden"
    >
      <div className="flex items-center justify-between px-6 py-6">
        <span className="font-mono-label text-[15px] font-semibold tracking-[0.08em]">
          TECHBISS
        </span>
        <button
          onClick={onClose}
          aria-label="Close menu"
          className="flex size-10 items-center justify-center rounded-full border border-[var(--color-border-strong)]"
        >
          <X className="size-4" />
        </button>
      </div>

      <nav className="flex flex-1 flex-col justify-center gap-1 px-6">
        {LINKS.map((link, i) => (
          <motion.div
            key={link.href}
            initial={{ opacity: 0, y: 24 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay: 0.08 + i * 0.05, ease: EASE }}
          >
            <Link
              href={link.href}
              onClick={onClose}
              className="block border-b border-[var(--color-border)] py-4 text-[13vw] leading-none font-medium tracking-tight text-[var(--color-ink)] sm:text-5xl"
            >
              {link.label}
            </Link>
          </motion.div>
        ))}
      </nav>

      <motion.div
        initial={{ opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5, delay: 0.5, ease: EASE }}
        className="flex flex-col gap-3 px-6 pb-10"
      >
        <Link
          href="/marketplace"
          onClick={onClose}
          className="rounded-full border border-[var(--color-border-strong)] px-6 py-4 text-center text-[15px] font-medium"
        >
          Browse Themes →
        </Link>
        <Link
          href="/contact"
          onClick={onClose}
          className="rounded-full bg-[var(--color-ink)] px-6 py-4 text-center text-[15px] font-medium text-[var(--color-bg)]"
        >
          Start a Project →
        </Link>
      </motion.div>
    </motion.div>
  );
}
