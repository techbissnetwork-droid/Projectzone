import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import "./globals.css";
import { Navbar } from "@/components/layout/navbar";
import { Footer } from "@/components/layout/footer";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  metadataBase: new URL("https://techbiss.com"),
  title: {
    default: "TECHBISS — The Digital Transformation Platform",
    template: "%s — TECHBISS",
  },
  description:
    "TECHBISS builds custom digital products and offers a marketplace of ready-made websites, apps and business systems — brand it, connect your infrastructure, and launch.",
  openGraph: {
    title: "TECHBISS — The Digital Transformation Platform",
    description:
      "Build from scratch. Buy ready. Make it yours. Launch faster. Grow with TECHBISS.",
    siteName: "TECHBISS",
    type: "website",
  },
  twitter: {
    card: "summary_large_image",
    title: "TECHBISS — The Digital Transformation Platform",
    description:
      "Build from scratch. Buy ready. Make it yours. Launch faster. Grow with TECHBISS.",
  },
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html
      lang="en"
      className={`${geistSans.variable} ${geistMono.variable} h-full antialiased`}
    >
      <body className="min-h-full flex flex-col bg-ink-950">
        <Navbar />
        <main className="flex-1">{children}</main>
        <Footer />
      </body>
    </html>
  );
}
