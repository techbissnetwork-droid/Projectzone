"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Container } from "@/components/ui/container";
import { footerNav } from "@/lib/data/nav";

function FooterColumn({
  title,
  items,
}: {
  title: string;
  items: { label: string; href: string }[];
}) {
  return (
    <div>
      <div className="font-mono-label text-[11px] uppercase text-paper-50/40">{title}</div>
      <ul className="mt-4 flex flex-col gap-3">
        {items.map((item) => (
          <li key={item.label}>
            <Link
              href={item.href}
              className="text-[14px] text-paper-50/70 transition-colors hover:text-paper-50"
            >
              {item.label}
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}

export function Footer() {
  const pathname = usePathname();
  if (pathname?.startsWith("/dashboard")) return null;

  return (
    <footer className="border-t border-line-dark bg-ink-950">
      <Container wide className="py-16 sm:py-20">
        <div className="grid grid-cols-2 gap-10 sm:grid-cols-3 lg:grid-cols-6">
          <div className="col-span-2 lg:col-span-2">
            <span className="text-[17px] font-semibold tracking-[-0.01em] text-paper-50">
              TECHBISS
            </span>
            <p className="mt-4 max-w-[26ch] text-[14px] leading-relaxed text-paper-50/50">
              Digital transformation for businesses ready to move forward.
            </p>
          </div>
          <FooterColumn title="Company" items={footerNav.company} />
          <FooterColumn title="Services" items={footerNav.services} />
          <FooterColumn title="Marketplace" items={footerNav.marketplace} />
          <FooterColumn title="Platform" items={footerNav.platform} />
        </div>

        <div className="mt-16 flex flex-col items-start justify-between gap-4 border-t border-line-dark pt-8 sm:flex-row sm:items-center">
          <p className="text-[13px] text-paper-50/40">
            © 2026 TECHBISS. All rights reserved.
          </p>
          <div className="flex items-center gap-6 text-[13px] text-paper-50/40">
            <Link href="/contact" className="hover:text-paper-50/70">
              Privacy
            </Link>
            <Link href="/contact" className="hover:text-paper-50/70">
              Terms
            </Link>
            <Link href="/contact" className="hover:text-paper-50/70">
              Contact
            </Link>
          </div>
        </div>
      </Container>
    </footer>
  );
}
