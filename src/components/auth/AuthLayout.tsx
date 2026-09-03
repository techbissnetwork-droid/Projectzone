import { ShieldCheck, Sparkles, Zap } from "lucide-react";

const highlights = [
  { icon: Zap, text: "Deploy purchased platforms in minutes" },
  { icon: ShieldCheck, text: "SOC 2 & ISO 27001-ready infrastructure" },
  { icon: Sparkles, text: "One account across every TECHBISS product" },
];

export function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="grid min-h-[calc(100vh-64px)] grid-cols-1 lg:grid-cols-2">
      <div className="flex items-center justify-center px-5 py-16 sm:px-8">{children}</div>
      <div className="relative hidden overflow-hidden bg-[linear-gradient(160deg,#0e1016,#161a2e_45%,#1a1440)] lg:block">
        <div className="bg-grid pointer-events-none absolute inset-0 opacity-40" />
        <div className="pointer-events-none absolute right-[-6rem] top-[-6rem] h-[26rem] w-[26rem] rounded-full bg-[radial-gradient(closest-side,rgba(75,91,255,0.35),transparent)] blur-2xl" />
        <div className="relative flex h-full flex-col justify-end p-12 xl:p-16">
          <blockquote className="text-2xl font-medium leading-snug text-white xl:text-3xl">
            &ldquo;We stopped losing weeks to manual customer setup and started compounding growth instead.&rdquo;
          </blockquote>
          <p className="mt-4 text-sm text-white/60">Sam Ito — Co-founder &amp; CEO, Voltage Analytics</p>

          <div className="mt-12 flex flex-col gap-4 border-t border-white/10 pt-8">
            {highlights.map(({ icon: Icon, text }) => (
              <div key={text} className="flex items-center gap-3 text-sm text-white/75">
                <Icon className="h-4 w-4 text-(--color-accent-2)" />
                {text}
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
