import type { Metadata } from "next";
import type { ReactNode } from "react";
import { Fraunces, Inter } from "next/font/google";
import { Nav } from "@/components/concept-2/Nav";
import { Footer } from "@/components/concept-2/Footer";

const fraunces = Fraunces({
  subsets: ["latin"],
  variable: "--font-fraunces",
  display: "swap",
});

const inter = Inter({
  subsets: ["latin"],
  variable: "--font-inter",
  display: "swap",
});

export const metadata: Metadata = {
  title: {
    default: "TECHBISS — Ultra-Minimal Luxury",
    template: "%s · TECHBISS",
  },
  description:
    "TECHBISS: technology and business digitization, presented with editorial restraint. Premium websites, applications, and infrastructure built with precision.",
};

export default function ConceptTwoLayout({ children }: { children: ReactNode }) {
  return (
    <div
      className={`${fraunces.variable} ${inter.variable} font-[family-name:var(--font-inter)] flex min-h-screen flex-col overflow-x-clip bg-white text-neutral-900 antialiased`}
    >
      <Nav />
      <main className="flex-1">{children}</main>
      <Footer />
    </div>
  );
}
