import Link from "next/link";
import { company, services } from "@/lib/site-data";
import { ConceptSwitcher } from "@/components/shared/ConceptSwitcher";
import { Container } from "@/components/concept-1/Container";

const footerServices = services.filter((service) => service.hasDetailPage);

const exploreLinks = [
  { label: "About", href: "/concept-1/about" },
  { label: "Portfolio", href: "/concept-1/portfolio" },
  { label: "Pricing", href: "/concept-1/pricing" },
  { label: "Process", href: "/concept-1/process" },
  { label: "Technology", href: "/concept-1/technology" },
  { label: "Contact", href: "/concept-1/contact" },
];

export function Footer() {
  return (
    <footer className="relative border-t border-white/10 bg-neutral-950">
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent"
      />
      <Container className="py-16">
        <div className="grid gap-12 lg:grid-cols-[1.4fr_1fr_1fr]">
          <div>
            <Link
              href="/concept-1"
              className="text-lg font-semibold tracking-tight text-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/70 rounded-full"
            >
              {company.name}
              <span className="ml-1 bg-gradient-to-r from-cyan-300 via-indigo-300 to-fuchsia-300 bg-clip-text text-transparent">
                .
              </span>
            </Link>
            <p className="mt-4 max-w-sm text-sm leading-relaxed text-neutral-400">
              {company.description}
            </p>
            <p className="mt-6 text-sm text-neutral-500">{company.legalTagline}</p>
          </div>

          <div>
            <h3 className="text-sm font-semibold uppercase tracking-[0.2em] text-neutral-300">
              Services
            </h3>
            <ul className="mt-5 space-y-3">
              {footerServices.map((service) => (
                <li key={service.slug}>
                  <Link
                    href={`/concept-1/services/${service.slug}`}
                    className="text-sm text-neutral-400 transition-colors hover:text-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/70 rounded"
                  >
                    {service.title}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          <div>
            <h3 className="text-sm font-semibold uppercase tracking-[0.2em] text-neutral-300">
              Explore
            </h3>
            <ul className="mt-5 space-y-3">
              {exploreLinks.map((link) => (
                <li key={link.href}>
                  <Link
                    href={link.href}
                    className="text-sm text-neutral-400 transition-colors hover:text-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/70 rounded"
                  >
                    {link.label}
                  </Link>
                </li>
              ))}
            </ul>
            <div className="mt-6 space-y-1 text-sm text-neutral-400">
              <p>
                <a
                  href={`mailto:${company.email}`}
                  className="transition-colors hover:text-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/70 rounded"
                >
                  {company.email}
                </a>
              </p>
              <p>{company.phone}</p>
              <p>{company.address}</p>
            </div>
          </div>
        </div>

        <div className="mt-14 flex flex-col items-start justify-between gap-6 border-t border-white/10 pt-8 sm:flex-row sm:items-center">
          <p className="text-xs text-neutral-500">
            © {new Date().getFullYear()} {company.name}. All rights reserved.
          </p>
          <ConceptSwitcher
            active="concept-1"
            className="text-xs"
            linkClassName="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-neutral-400 backdrop-blur-xl transition-colors hover:text-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/70"
            activeLinkClassName="!text-neutral-50 !border-white/25 bg-gradient-to-r from-cyan-400/20 via-indigo-400/20 to-fuchsia-500/20"
          />
        </div>
      </Container>
    </footer>
  );
}
