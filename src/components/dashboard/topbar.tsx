"use client";

import { useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import { Menu, Bell, ChevronDown } from "lucide-react";
import { DashboardSidebar } from "./sidebar";

export function DashboardTopbar() {
  const [open, setOpen] = useState(false);

  return (
    <>
      <div className="flex items-center justify-between border-b border-line-dark bg-ink-950/80 px-5 py-4 backdrop-blur-xl lg:hidden">
        <span className="text-[15px] font-semibold text-paper-50">TECHBISS</span>
        <button
          onClick={() => setOpen(true)}
          className="flex size-9 items-center justify-center rounded-full border border-line-dark text-paper-50"
          aria-label="Open dashboard menu"
        >
          <Menu className="size-4.5" />
        </button>
      </div>

      <AnimatePresence>
        {open && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-[70] flex lg:hidden"
          >
            <motion.div
              initial={{ x: "-100%" }}
              animate={{ x: 0 }}
              exit={{ x: "-100%" }}
              transition={{ duration: 0.3, ease: [0.16, 1, 0.3, 1] }}
              className="h-full w-[280px] max-w-[80vw] border-r border-line-dark bg-ink-950 p-5"
            >
              <DashboardSidebar onNavigate={() => setOpen(false)} />
            </motion.div>
            <div className="flex-1 bg-black/60" onClick={() => setOpen(false)} />
          </motion.div>
        )}
      </AnimatePresence>
    </>
  );
}

export function DashboardAccountBar() {
  return (
    <div className="hidden items-center justify-end border-b border-line-dark px-8 py-4 lg:flex">
      <div className="flex items-center gap-3">
        <button
          aria-label="Notifications"
          className="flex size-9 items-center justify-center rounded-full border border-line-dark text-paper-50/60 hover:text-paper-50"
        >
          <Bell className="size-4" />
        </button>
        <button className="flex items-center gap-2 rounded-full border border-line-dark py-1.5 pl-1.5 pr-3 text-[13px] font-medium text-paper-50/80 hover:border-line-dark-strong">
          <span className="flex size-6 items-center justify-center rounded-full bg-gold-400 text-[11px] font-semibold text-ink-950">
            T
          </span>
          TECHBISS User
          <ChevronDown className="size-3.5 text-paper-50/40" />
        </button>
      </div>
    </div>
  );
}
