import Link from "next/link";
import { ConceptSwitcher } from "@/components/shared/ConceptSwitcher";
import { company, primaryNav } from "@/lib/site-data";
import { fontSerif } from "@/components/concept-2/fonts";

const footerLinks = primaryNav.map((item) => ({
  label: item.label,
  href: item.href === "/" ? "/concept-2" : `/concept-2${item.href}`,
}));

export function Footer() {
  return (
    <footer className="border-t border-neutral-200 bg-white">
      <div className="mx-auto max-w-7xl px-6 py-16 sm:px-8 lg:px-10">
        <div className="grid gap-12 lg:grid-cols-[1.2fr_1fr_1fr]">
          <div>
            <Link
              href="/concept-2"
              className={`${fontSerif} rounded-sm text-2xl text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900`}
            >
              TECHBISS
            </Link>
            <p className="mt-4 max-w-sm text-sm leading-relaxed text-neutral-500">{company.description}</p>
          </div>

          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">Navigate</p>
            <ul className="mt-5 space-y-3">
              {footerLinks.map((link) => (
                <li key={link.href}>
                  <Link
                    href={link.href}
                    className="rounded-sm text-sm text-neutral-700 hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900"
                  >
                    {link.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-neutral-500">Contact</p>
            <ul className="mt-5 space-y-3 text-sm text-neutral-700">
              <li>
                <a
                  className="rounded-sm hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900"
                  href={`mailto:${company.email}`}
                >
                  {company.email}
                </a>
              </li>
              <li>{company.phone}</li>
              <li>{company.address}</li>
            </ul>
          </div>
        </div>

        <div className="mt-16 flex flex-col gap-6 border-t border-neutral-200 pt-8 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-xs text-neutral-500">
            © {new Date().getFullYear()} {company.name}. All rights reserved.
          </p>
          <ConceptSwitcher
            active="concept-2"
            className="gap-6"
            linkClassName="rounded-sm text-xs uppercase tracking-[0.15em] text-neutral-500 transition-colors hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900"
            activeLinkClassName="text-neutral-900"
          />
        </div>
      </div>
    </footer>
  );
}
