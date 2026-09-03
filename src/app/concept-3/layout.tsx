import type { Metadata } from "next";
import { Sora, Manrope } from "next/font/google";
import { Nav } from "@/components/concept-3/Nav";
import { Footer } from "@/components/concept-3/Footer";

const sora = Sora({
  subsets: ["latin"],
  variable: "--font-sora",
  weight: ["500", "600", "700", "800"],
  display: "swap",
});

const manrope = Manrope({
  subsets: ["latin"],
  variable: "--font-manrope",
  weight: ["400", "500", "600", "700", "800"],
  display: "swap",
});

export const metadata: Metadata = {
  title: {
    default: "TECHBISS — Digital Experience",
    template: "%s · TECHBISS",
  },
  description:
    "An interactive, dashboard-inspired digital product experience for TECHBISS — technology and business digitization, presented like a next-generation software product.",
};

export default function ConceptThreeLayout({ children }: { children: React.ReactNode }) {
  return (
    <div
      className={`${sora.variable} ${manrope.variable} ${manrope.className} flex min-h-screen flex-col bg-[#0b0c14] text-slate-300 antialiased`}
    >
      <style>{`
        .font-display {
          font-family: var(--font-sora), var(--font-manrope), ui-sans-serif, system-ui, sans-serif;
        }
      `}</style>
      <Nav />
      <main className="flex-1">{children}</main>
      <Footer />
    </div>
  );
}
