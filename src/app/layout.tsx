import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: {
    default: "TECHBISS — We Build What Moves Business Forward",
    template: "%s · TECHBISS",
  },
  description:
    "TECHBISS helps businesses move from offline to online — premium websites, applications, and complete digital infrastructure. Explore three distinct design concepts.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="en" className="h-full antialiased">
      <body className="min-h-full flex flex-col overflow-x-clip font-sans">{children}</body>
    </html>
  );
}
