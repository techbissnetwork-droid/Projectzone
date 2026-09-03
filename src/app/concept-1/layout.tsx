import type { Metadata } from "next";
import type { ReactNode } from "react";
import { Space_Grotesk, Inter } from "next/font/google";
import { Nav } from "@/components/concept-1/Nav";
import { Footer } from "@/components/concept-1/Footer";

const spaceGrotesk = Space_Grotesk({
  subsets: ["latin"],
  variable: "--font-display",
  display: "swap",
});

const inter = Inter({
  subsets: ["latin"],
  variable: "--font-body",
  display: "swap",
});

export const metadata: Metadata = {
  title: {
    default: "TECHBISS — Future Luxury",
    template: "%s · TECHBISS",
  },
  description:
    "A cinematic, futuristic enterprise-technology concept for TECHBISS — premium dark interface, glass surfaces, and purposeful motion.",
};

export default function ConceptOneLayout({ children }: { children: ReactNode }) {
  return (
    <div
      className={`${spaceGrotesk.variable} ${inter.variable} min-h-screen overflow-x-clip bg-[#05060a] font-[var(--font-body)] text-neutral-300 antialiased [&_h1]:font-[var(--font-display)] [&_h2]:font-[var(--font-display)] [&_h3]:font-[var(--font-display)]`}
    >
      <Nav />
      <main>{children}</main>
      <Footer />
    </div>
  );
}
