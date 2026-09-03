"use client";

import { motion } from "framer-motion";
import { RevealGroup, revealItem } from "@/components/ui/Reveal";

export const AboutMotionGrid = RevealGroup;

export function AboutMotionItem({ children, className }: { children: React.ReactNode; className?: string }) {
  return (
    <motion.div variants={revealItem} className={className}>
      {children}
    </motion.div>
  );
}
