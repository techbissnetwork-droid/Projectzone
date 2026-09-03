import Link from "next/link";
import { Container } from "@/components/ui/Container";
import { Button } from "@/components/ui/Button";
import { site, footerNav, primaryNav } from "@/lib/data/site";
import { InstagramIcon, LinkedInIcon, XIcon } from "@/components/shared/SocialIcons";

const socials = [
  { label: "LinkedIn", href: site.social.linkedin, icon: LinkedInIcon },
  { label: "Instagram", href: site.social.instagram, icon: InstagramIcon },
  { label: "X", href: site.social.x, icon: XIcon },
];

function FooterColumn({
  title,
  links,
}: {
  title: string;
  links: readonly { label: string; href: string }[];
}) {
  return (
    <div className="flex flex-col gap-4">
      <span className="text-eyebrow text-paper-faint">{title}</span>
      <ul className="flex flex-col gap-3">
        {links.map((link) => (
          <li key={link.href}>
            <Link
              href={link.href}
              className="text-[0.95rem] text-paper-dim transition-colors duration-300 hover:text-paper"
            >
              {link.label}
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );
}

export function Footer() {
  return (
    <footer className="relative border-t border-line bg-ink">
      <div
        className="pointer-events-none absolute inset-x-0 top-0 h-px"
        style={{
          background:
            "linear-gradient(90deg, transparent, var(--color-line-strong), transparent)",
        }}
      />
      <Container className="flex flex-col gap-16 py-20 md:py-28">
        <div className="flex flex-col justify-between gap-10 border-b border-line pb-16 md:flex-row md:items-end">
          <div className="max-w-lg">
            <span className="text-eyebrow font-semibold tracking-[0.16em] text-paper">
              TECHBISS
            </span>
            <p className="text-h3 mt-5 text-balance font-medium text-paper">
              Digital transformation for businesses ready to move forward.
            </p>
          </div>
          <div className="shrink-0">
            <Button href="/contact" size="lg">
              Start Your Project
            </Button>
          </div>
        </div>

        <div className="grid grid-cols-2 gap-10 sm:grid-cols-4">
          <FooterColumn
            title="Company"
            links={footerNav.company}
          />
          <FooterColumn title="Services" links={footerNav.services} />
          <FooterColumn
            title="Infrastructure"
            links={footerNav.infrastructure}
          />
          <div className="flex flex-col gap-4">
            <span className="text-eyebrow text-paper-faint">Connect</span>
            <ul className="flex flex-col gap-3">
              <li>
                <a
                  href={`mailto:${site.email}`}
                  className="text-[0.95rem] text-paper-dim transition-colors duration-300 hover:text-paper"
                >
                  {site.email}
                </a>
              </li>
              <li>
                <a
                  href={`tel:${site.phone.replace(/[^+\d]/g, "")}`}
                  className="text-[0.95rem] text-paper-dim transition-colors duration-300 hover:text-paper"
                >
                  {site.phone}
                </a>
              </li>
            </ul>
            <div className="mt-2 flex items-center gap-3">
              {socials.map((s) => (
                <a
                  key={s.label}
                  href={s.href}
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label={s.label}
                  className="flex size-9 items-center justify-center rounded-full border border-line-strong text-paper-dim transition-colors duration-300 hover:border-gold/50 hover:text-gold-bright"
                >
                  <s.icon className="size-4" />
                </a>
              ))}
            </div>
          </div>
        </div>

        <div className="flex flex-col-reverse items-start justify-between gap-6 text-sm text-paper-faint sm:flex-row sm:items-center">
          <p>© 2026 TECHBISS. All rights reserved.</p>
          <nav className="flex flex-wrap items-center gap-x-6 gap-y-2">
            {primaryNav.map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className="transition-colors duration-300 hover:text-paper-dim"
              >
                {item.label}
              </Link>
            ))}
          </nav>
        </div>
      </Container>
    </footer>
  );
}
