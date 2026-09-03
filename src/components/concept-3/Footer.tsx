import Link from "next/link";
import { Zap, Mail, Phone, MapPin } from "lucide-react";
import { company } from "@/lib/site-data";
import { ConceptSwitcher } from "@/components/shared/ConceptSwitcher";

const columns = [
  {
    title: "Product",
    links: [
      { label: "Services", href: "/concept-3/services" },
      { label: "Pricing", href: "/concept-3/pricing" },
      { label: "Process", href: "/concept-3/process" },
      { label: "Technology", href: "/concept-3/technology" },
    ],
  },
  {
    title: "Company",
    links: [
      { label: "About", href: "/concept-3/about" },
      { label: "Portfolio", href: "/concept-3/portfolio" },
      { label: "Contact", href: "/concept-3/contact" },
      { label: "Get Started", href: "/concept-3/get-started" },
    ],
  },
];

export function Footer() {
  return (
    <footer className="border-t border-white/10 bg-[#08090f]">
      <div className="mx-auto w-full max-w-7xl px-5 py-14 sm:px-6 lg:px-8 lg:py-16">
        <div className="grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-4">
          <div className="sm:col-span-2 lg:col-span-1">
            <Link href="/concept-3" className="flex items-center gap-2 text-lg font-bold text-white">
              <span
                className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-violet-500 via-fuchsia-500 to-blue-500"
                aria-hidden="true"
              >
                <Zap className="h-4 w-4" />
              </span>
              <span className="font-display">
                TECHBISS<span className="text-violet-400">.</span>
              </span>
            </Link>
            <p className="mt-4 max-w-xs text-sm leading-relaxed text-slate-400">{company.description}</p>
          </div>

          {columns.map((col) => (
            <div key={col.title}>
              <h3 className="text-sm font-semibold uppercase tracking-widest text-slate-500">{col.title}</h3>
              <ul className="mt-4 flex flex-col gap-3">
                {col.links.map((l) => (
                  <li key={l.href}>
                    <Link
                      href={l.href}
                      className="rounded text-sm text-slate-400 transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400"
                    >
                      {l.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}

          <div>
            <h3 className="text-sm font-semibold uppercase tracking-widest text-slate-500">Get in touch</h3>
            <ul className="mt-4 flex flex-col gap-3 text-sm text-slate-400">
              <li className="flex items-center gap-2">
                <Mail className="h-4 w-4 shrink-0 text-violet-400" aria-hidden="true" />
                <a href={`mailto:${company.email}`} className="rounded hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400">
                  {company.email}
                </a>
              </li>
              <li className="flex items-center gap-2">
                <Phone className="h-4 w-4 shrink-0 text-violet-400" aria-hidden="true" />
                <span>{company.phone}</span>
              </li>
              <li className="flex items-start gap-2">
                <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-violet-400" aria-hidden="true" />
                <span>{company.address}</span>
              </li>
            </ul>
          </div>
        </div>

        <div className="mt-12 flex flex-col gap-6 border-t border-white/10 pt-8 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-xs text-slate-500">
            &copy; {new Date().getFullYear()} {company.name}. All rights reserved.
          </p>
          <ConceptSwitcher
            active="concept-3"
            className="text-xs"
            linkClassName="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-slate-400 transition-colors hover:text-white hover:border-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400"
            activeLinkClassName="!text-white !border-violet-400/40 !bg-violet-500/10"
          />
        </div>
      </div>
    </footer>
  );
}
