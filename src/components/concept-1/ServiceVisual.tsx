import type { ReactNode } from "react";

const glow = (
  <div
    aria-hidden="true"
    className="pointer-events-none absolute inset-0 -z-10 opacity-70"
    style={{
      background:
        "radial-gradient(60% 60% at 50% 40%, rgba(99,102,241,0.35) 0%, rgba(0,0,0,0) 70%)",
    }}
  />
);

function Frame({ children }: { children: ReactNode }) {
  return (
    <div className="relative flex aspect-square w-full max-w-md items-center justify-center overflow-hidden rounded-[2.5rem] border border-white/10 bg-white/5 p-8 backdrop-blur-xl sm:aspect-[4/3] lg:aspect-square">
      {glow}
      {children}
    </div>
  );
}

function BrowserMotif() {
  return (
    <Frame>
      <div className="w-full max-w-xs overflow-hidden rounded-2xl border border-white/15 bg-neutral-950/80 shadow-2xl" aria-hidden="true">
        <div className="flex items-center gap-1.5 border-b border-white/10 px-4 py-3">
          <span className="h-2.5 w-2.5 rounded-full bg-fuchsia-400/70" />
          <span className="h-2.5 w-2.5 rounded-full bg-indigo-400/70" />
          <span className="h-2.5 w-2.5 rounded-full bg-cyan-400/70" />
          <div className="ml-3 h-4 flex-1 rounded-full bg-white/5" />
        </div>
        <div className="space-y-3 p-5">
          <div className="h-3 w-2/3 rounded-full bg-gradient-to-r from-cyan-400/70 via-indigo-400/70 to-fuchsia-500/70" />
          <div className="h-2 w-full rounded-full bg-white/10" />
          <div className="h-2 w-5/6 rounded-full bg-white/10" />
          <div className="mt-4 grid grid-cols-3 gap-2">
            <div className="h-12 rounded-xl bg-white/5" />
            <div className="h-12 rounded-xl bg-white/5" />
            <div className="h-12 rounded-xl bg-white/5" />
          </div>
        </div>
      </div>
    </Frame>
  );
}

function DashboardMotif() {
  return (
    <Frame>
      <div className="grid w-full max-w-xs grid-cols-2 gap-3" aria-hidden="true">
        <div className="col-span-2 rounded-2xl border border-white/10 bg-neutral-950/80 p-4">
          <div className="flex items-end gap-1.5">
            {[40, 70, 55, 90, 65, 100, 45].map((height, index) => (
              <div
                key={index}
                className="flex-1 rounded-t-sm bg-gradient-to-t from-cyan-400/70 via-indigo-400/70 to-fuchsia-500/70"
                style={{ height: `${height * 0.5}px` }}
              />
            ))}
          </div>
        </div>
        <div className="rounded-2xl border border-white/10 bg-neutral-950/80 p-4">
          <div className="h-2 w-1/2 rounded-full bg-white/10" />
          <div className="mt-3 h-8 w-2/3 rounded-full bg-gradient-to-r from-cyan-400/70 to-indigo-400/70" />
        </div>
        <div className="rounded-2xl border border-white/10 bg-neutral-950/80 p-4">
          <div className="h-2 w-1/2 rounded-full bg-white/10" />
          <div className="mt-3 h-8 w-1/2 rounded-full bg-gradient-to-r from-indigo-400/70 to-fuchsia-500/70" />
        </div>
      </div>
    </Frame>
  );
}

function PhoneMotif() {
  return (
    <Frame>
      <div
        className="flex h-72 w-36 flex-col overflow-hidden rounded-[2rem] border-2 border-white/15 bg-neutral-950/80 shadow-2xl"
        aria-hidden="true"
      >
        <div className="mx-auto mt-2 h-1.5 w-10 rounded-full bg-white/20" />
        <div className="flex-1 space-y-3 p-4">
          <div className="h-16 w-full rounded-2xl bg-gradient-to-br from-cyan-400/60 via-indigo-400/60 to-fuchsia-500/60" />
          <div className="h-2 w-3/4 rounded-full bg-white/10" />
          <div className="h-2 w-1/2 rounded-full bg-white/10" />
          <div className="grid grid-cols-2 gap-2 pt-2">
            <div className="h-14 rounded-xl bg-white/5" />
            <div className="h-14 rounded-xl bg-white/5" />
          </div>
        </div>
        <div className="mx-auto mb-3 h-1 w-14 rounded-full bg-white/20" />
      </div>
    </Frame>
  );
}

function BuildingMotif() {
  return (
    <Frame>
      <svg viewBox="0 0 200 200" className="h-56 w-56" aria-hidden="true">
        <defs>
          <linearGradient id="building-gradient" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stopColor="#22d3ee" stopOpacity="0.8" />
            <stop offset="50%" stopColor="#818cf8" stopOpacity="0.8" />
            <stop offset="100%" stopColor="#e879f9" stopOpacity="0.8" />
          </linearGradient>
        </defs>
        <rect x="30" y="90" width="34" height="90" rx="4" fill="url(#building-gradient)" fillOpacity="0.25" stroke="url(#building-gradient)" />
        <rect x="83" y="55" width="34" height="125" rx="4" fill="url(#building-gradient)" fillOpacity="0.3" stroke="url(#building-gradient)" />
        <rect x="136" y="20" width="34" height="160" rx="4" fill="url(#building-gradient)" fillOpacity="0.35" stroke="url(#building-gradient)" />
        <path d="M20 190 L180 190" stroke="url(#building-gradient)" strokeWidth="1.5" strokeLinecap="round" strokeDasharray="2 6" />
        <path d="M45 90 L45 20 M98 55 L150 15" stroke="url(#building-gradient)" strokeWidth="1.5" strokeDasharray="3 5" />
        <circle cx="45" cy="20" r="4" fill="#22d3ee" />
        <circle cx="150" cy="15" r="4" fill="#e879f9" />
      </svg>
    </Frame>
  );
}

function ServerMotif() {
  return (
    <Frame>
      <div className="flex w-full max-w-xs flex-col gap-3" aria-hidden="true">
        {[0, 1, 2].map((row) => (
          <div
            key={row}
            className="flex items-center gap-3 rounded-2xl border border-white/10 bg-neutral-950/80 px-4 py-3"
          >
            <span className="h-2.5 w-2.5 flex-none rounded-full bg-gradient-to-r from-cyan-400 to-fuchsia-500" />
            <div className="h-2 flex-1 rounded-full bg-white/10" />
            <div className="h-2 w-8 rounded-full bg-white/15" />
          </div>
        ))}
      </div>
    </Frame>
  );
}

function ShieldMotif() {
  return (
    <Frame>
      <svg viewBox="0 0 200 200" className="h-56 w-56" aria-hidden="true">
        <defs>
          <linearGradient id="shield-gradient" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stopColor="#22d3ee" />
            <stop offset="50%" stopColor="#818cf8" />
            <stop offset="100%" stopColor="#e879f9" />
          </linearGradient>
        </defs>
        <path
          d="M100 20 L165 45 V95 C165 140 138 172 100 185 C62 172 35 140 35 95 V45 Z"
          fill="url(#shield-gradient)"
          fillOpacity="0.15"
          stroke="url(#shield-gradient)"
          strokeWidth="2"
        />
        <rect x="80" y="100" width="40" height="32" rx="6" fill="none" stroke="url(#shield-gradient)" strokeWidth="2" />
        <path d="M88 100 V88 a12 12 0 0 1 24 0 V100" fill="none" stroke="url(#shield-gradient)" strokeWidth="2" />
      </svg>
    </Frame>
  );
}

function EnvelopeMotif() {
  return (
    <Frame>
      <svg viewBox="0 0 220 160" className="h-48 w-64" aria-hidden="true">
        <defs>
          <linearGradient id="envelope-gradient" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stopColor="#22d3ee" />
            <stop offset="50%" stopColor="#818cf8" />
            <stop offset="100%" stopColor="#e879f9" />
          </linearGradient>
        </defs>
        <rect x="10" y="20" width="200" height="120" rx="14" fill="url(#envelope-gradient)" fillOpacity="0.12" stroke="url(#envelope-gradient)" strokeWidth="2" />
        <path d="M14 28 L110 100 L206 28" fill="none" stroke="url(#envelope-gradient)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      </svg>
    </Frame>
  );
}

const motifs = {
  browser: BrowserMotif,
  dashboard: DashboardMotif,
  phone: PhoneMotif,
  building: BuildingMotif,
  server: ServerMotif,
  shield: ShieldMotif,
  envelope: EnvelopeMotif,
} as const;

export type ServiceVisualVariant = keyof typeof motifs;

export function ServiceVisual({ variant }: { variant: ServiceVisualVariant }) {
  const Motif = motifs[variant];
  return <Motif />;
}
