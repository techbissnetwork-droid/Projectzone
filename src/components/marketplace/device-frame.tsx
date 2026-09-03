"use client";

import { cn } from "@/lib/utils";

export type Device = "desktop" | "tablet" | "mobile";

const widths: Record<Device, string> = {
  desktop: "w-full",
  tablet: "w-[520px] max-w-full",
  mobile: "w-[340px] max-w-full",
};

export function DeviceFrame({
  device,
  children,
  className,
}: {
  device: Device;
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <div className={cn("mx-auto transition-all duration-500 ease-out", widths[device], className)}>
      <div
        className={cn(
          "overflow-hidden border border-line-dark-strong bg-ink-950 shadow-[0_30px_80px_-30px_rgba(0,0,0,0.6)]",
          device === "desktop" ? "rounded-xl" : "rounded-[2rem]"
        )}
      >
        {device === "desktop" && (
          <div className="flex items-center gap-1.5 border-b border-line-dark bg-ink-900 px-4 py-2.5">
            <span className="size-2.5 rounded-full bg-danger-500/60" />
            <span className="size-2.5 rounded-full bg-warning-500/60" />
            <span className="size-2.5 rounded-full bg-success-500/60" />
          </div>
        )}
        {device !== "desktop" && (
          <div className="flex items-center justify-center bg-ink-900 py-2.5">
            <span className="h-1 w-10 rounded-full bg-paper-50/20" />
          </div>
        )}
        <div className={cn("overflow-y-auto", device === "desktop" ? "h-[520px]" : "h-[560px]")}>
          {children}
        </div>
      </div>
    </div>
  );
}
