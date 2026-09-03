import type { Metadata } from "next";
import { Geist, Geist_Mono, Instrument_Serif } from "next/font/google";
import { SiteHeader } from "@/components/layout/site-header";
import { SiteFooter } from "@/components/layout/site-footer";
import "./globals.css";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

const instrumentSerif = Instrument_Serif({
  variable: "--font-instrument-serif",
  subsets: ["latin"],
  weight: "400",
  style: ["normal", "italic"],
});

export const metadata: Metadata = {
  metadataBase: new URL("https://techbiss.com"),
  title: {
    default: "TECHBISS — Digital Transformation Platform",
    template: "%s — TECHBISS",
  },
  description:
    "TECHBISS builds the entire digital presence of your business — custom websites, apps, e-commerce and infrastructure — or you can buy a ready-made theme, make it yours and launch faster.",
  openGraph: {
    title: "TECHBISS — Digital Transformation Platform",
    description:
      "Build from scratch. Buy ready-made. Make it yours. Launch faster. Grow with TECHBISS.",
    siteName: "TECHBISS",
    type: "website",
  },
  twitter: {
    card: "summary_large_image",
    title: "TECHBISS — Digital Transformation Platform",
    description:
      "Build from scratch. Buy ready-made. Make it yours. Launch faster. Grow with TECHBISS.",
  },
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html
      lang="en"
      className={`${geistSans.variable} ${geistMono.variable} ${instrumentSerif.variable} h-full antialiased`}
    >
      <body className="min-h-full flex flex-col bg-[var(--color-bg)] text-[var(--color-ink)]">
        <SiteHeader />
        <main className="flex-1">{children}</main>
        <SiteFooter />
      </body>
    </html>
  );
}
