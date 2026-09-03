import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Star, Check, Smartphone, Monitor, RefreshCw, ShieldCheck, LifeBuoy } from "lucide-react";
import { Container } from "@/components/ui/container";
import { Eyebrow, Badge } from "@/components/ui/eyebrow";
import { Button } from "@/components/ui/button";
import { Reveal, RevealGroup, RevealItem } from "@/components/ui/reveal";
import { ThemeVisual } from "@/components/marketplace/theme-visual";
import { ThemeCard } from "@/components/marketplace/theme-card";
import { getProduct, getRelatedProducts, products, type Product, type ProductReview } from "@/lib/data/marketplace";

const badgeTone: Record<string, "accent" | "gold" | "live" | "neutral"> = {
  Featured: "accent",
  "Best Seller": "gold",
  Trending: "live",
  New: "accent",
  Free: "neutral",
};

const FALLBACK_REVIEWERS = ["Jordan A.", "Sam K.", "Riley P."];

function getReviews(product: Product): ProductReview[] {
  if (product.sampleReviews && product.sampleReviews.length > 0) return product.sampleReviews;
  return [
    {
      name: FALLBACK_REVIEWERS[0],
      quote: `${product.name} was exactly what we needed to get our ${product.category.toLowerCase()} presence live without starting from a blank canvas.`,
      rating: 5,
    },
    {
      name: FALLBACK_REVIEWERS[1],
      quote: `Clean structure, easy to make it feel like our own brand once we got into Brand Studio. Would buy again.`,
      rating: 5,
    },
    {
      name: FALLBACK_REVIEWERS[2],
      quote: `Solid theme for the price. Took a little time to fully customize but the foundation is genuinely strong.`,
      rating: 4,
    },
  ];
}

export async function generateStaticParams() {
  return products.map((p) => ({ slug: p.slug }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const product = getProduct(slug);
  if (!product) return { title: "Theme Not Found" };
  return {
    title: product.name,
    description: product.tagline,
  };
}

export default async function ThemeDetailPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const product = getProduct(slug);
  if (!product) notFound();

  const reviews = getReviews(product);
  const related = getRelatedProducts(product, 4);
  const gallerySeeds = ["a", "b", "c", "d"].map((s) => ({
    ...product,
    slug: `${product.slug}-${s}`,
  }));

  return (
    <>
      <section className="pb-16 pt-36 sm:pb-20 sm:pt-40 md:pt-44">
        <Container>
          <Reveal>
            <Eyebrow>
              Marketplace · {product.category}
            </Eyebrow>
            <div className="mt-6 flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
              <div className="max-w-[640px]">
                <h1 className="text-balance text-[34px] font-medium leading-[1.05] tracking-[-0.02em] sm:text-[48px]">
                  {product.name}
                </h1>
                <p className="mt-4 text-pretty text-[16px] leading-relaxed text-[var(--color-ink-muted)]">
                  {product.tagline}
                </p>

                <div className="mt-6 flex flex-wrap items-center gap-3">
                  {product.badges.map((b) => (
                    <Badge key={b} tone={badgeTone[b] ?? "neutral"}>
                      {b}
                    </Badge>
                  ))}
                  <span className="inline-flex items-center gap-1.5 text-[13.5px] text-[var(--color-ink-muted)]">
                    <Star className="size-3.5 fill-[var(--color-gold)] text-[var(--color-gold)]" />
                    {product.rating} · {product.reviews} reviews
                  </span>
                </div>
              </div>

              <div className="flex shrink-0 flex-col items-start gap-5 lg:items-end">
                <span className="text-[32px] font-medium tracking-[-0.01em]">
                  {product.free ? "Free" : `$${product.price}`}
                </span>
                <div className="flex flex-wrap gap-3">
                  <Button href={`/marketplace/theme/${product.slug}/preview`} variant="secondary" size="md">
                    Live Preview
                  </Button>
                  <Button href={`/marketplace/checkout/${product.slug}`} variant="primary" size="md">
                    Buy Theme
                  </Button>
                </div>
              </div>
            </div>
          </Reveal>

          <Reveal delay={0.1} className="mt-14">
            <div className="grid gap-4 lg:grid-cols-[1.6fr_1fr]">
              <div className="aspect-[16/10] overflow-hidden rounded-2xl">
                <ThemeVisual product={product} className="h-full" />
              </div>
              <div className="grid grid-cols-2 gap-4 lg:grid-cols-1">
                {gallerySeeds.slice(0, 3).map((seed, i) => (
                  <div
                    key={seed.slug}
                    className={`aspect-[4/3] overflow-hidden rounded-2xl ${i === 2 ? "hidden lg:block" : ""}`}
                  >
                    <ThemeVisual product={seed} className="h-full" chrome={i !== 1} />
                  </div>
                ))}
              </div>
            </div>
          </Reveal>
        </Container>
      </section>

      <section className="border-t border-[var(--color-border)] py-24 sm:py-32">
        <Container>
          <div className="grid gap-16 lg:grid-cols-[1fr_360px]">
            <div className="flex flex-col gap-16">
              <Reveal>
                <Eyebrow>Overview</Eyebrow>
                <p className="mt-5 text-pretty text-[16px] leading-relaxed text-[var(--color-ink-muted)]">
                  {product.description}
                </p>
              </Reveal>

              <Reveal>
                <Eyebrow>What&rsquo;s Included</Eyebrow>
                <RevealGroup className="mt-6 grid gap-3 sm:grid-cols-2">
                  {product.features.map((feature) => (
                    <RevealItem key={feature}>
                      <div className="flex items-start gap-2.5 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-3.5">
                        <Check className="mt-0.5 size-4 shrink-0 text-[var(--color-accent-ink)]" strokeWidth={2} />
                        <span className="text-[14px] leading-snug text-[var(--color-ink)]">{feature}</span>
                      </div>
                    </RevealItem>
                  ))}
                </RevealGroup>
              </Reveal>

              <Reveal>
                <Eyebrow>Pages Included</Eyebrow>
                <div className="mt-5 flex flex-wrap gap-2">
                  {product.pages.map((page) => (
                    <span
                      key={page}
                      className="rounded-full border border-[var(--color-border-strong)] px-3.5 py-1.5 text-[13px] text-[var(--color-ink-muted)]"
                    >
                      {page}
                    </span>
                  ))}
                </div>
              </Reveal>

              <Reveal>
                <Eyebrow>Make It Yours</Eyebrow>
                <p className="mt-5 max-w-[64ch] text-pretty text-[15px] leading-relaxed text-[var(--color-ink-muted)]">
                  Once purchased, {product.name} opens in Brand Studio — swap in your logo,
                  brand colors, fonts and real content, then publish under your own domain.
                  No design experience required; every section stays intact while you make
                  it unmistakably yours.
                </p>
              </Reveal>

              <Reveal>
                <Eyebrow>Reviews</Eyebrow>
                <RevealGroup className="mt-6 grid gap-4 sm:grid-cols-3">
                  {reviews.slice(0, 3).map((review) => (
                    <RevealItem key={review.name}>
                      <div className="flex h-full flex-col justify-between rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-5">
                        <p className="text-[13.5px] leading-relaxed text-[var(--color-ink-muted)]">
                          &ldquo;{review.quote}&rdquo;
                        </p>
                        <div className="mt-5 flex items-center justify-between">
                          <span className="text-[13px] font-medium text-[var(--color-ink)]">
                            {review.name}
                          </span>
                          <span className="flex items-center gap-1 text-[12px] text-[var(--color-gold)]">
                            <Star className="size-3 fill-[var(--color-gold)] text-[var(--color-gold)]" />
                            {review.rating}
                          </span>
                        </div>
                      </div>
                    </RevealItem>
                  ))}
                </RevealGroup>
              </Reveal>
            </div>

            <Reveal className="flex flex-col gap-6">
              <div className="rounded-2xl border border-[var(--color-border-strong)] bg-[var(--color-surface)] p-6">
                <h3 className="font-mono-label text-[11px] uppercase text-[var(--color-ink-faint)]">
                  Technology
                </h3>
                <div className="mt-4 flex flex-wrap gap-2">
                  {product.tech.map((t) => (
                    <span
                      key={t}
                      className="rounded-full bg-white/[0.06] px-3 py-1.5 text-[12.5px] text-[var(--color-ink-muted)]"
                    >
                      {t}
                    </span>
                  ))}
                </div>

                <div className="mt-6 flex items-center gap-2.5 border-t border-[var(--color-border)] pt-6">
                  {product.responsive ? (
                    <>
                      <Smartphone className="size-4 text-[var(--color-live)]" strokeWidth={1.75} />
                      <Monitor className="size-4 text-[var(--color-live)]" strokeWidth={1.75} />
                      <span className="text-[13px] text-[var(--color-ink-muted)]">
                        Fully responsive — desktop, tablet &amp; mobile
                      </span>
                    </>
                  ) : (
                    <span className="text-[13px] text-[var(--color-ink-muted)]">Desktop optimized</span>
                  )}
                </div>
              </div>

              <div className="rounded-2xl border border-[var(--color-border-strong)] bg-[var(--color-surface)] p-6">
                <h3 className="font-mono-label text-[11px] uppercase text-[var(--color-ink-faint)]">
                  License &amp; Support
                </h3>
                <ul className="mt-4 flex flex-col gap-3.5">
                  <li className="flex items-start gap-2.5 text-[13.5px] leading-snug text-[var(--color-ink-muted)]">
                    <ShieldCheck className="mt-0.5 size-4 shrink-0 text-[var(--color-accent-ink)]" strokeWidth={1.75} />
                    Single business-use license — one live deployment per purchase.
                  </li>
                  <li className="flex items-start gap-2.5 text-[13.5px] leading-snug text-[var(--color-ink-muted)]">
                    <RefreshCw className="mt-0.5 size-4 shrink-0 text-[var(--color-accent-ink)]" strokeWidth={1.75} />
                    Free updates for 12 months from purchase date.
                  </li>
                  <li className="flex items-start gap-2.5 text-[13.5px] leading-snug text-[var(--color-ink-muted)]">
                    <LifeBuoy className="mt-0.5 size-4 shrink-0 text-[var(--color-accent-ink)]" strokeWidth={1.75} />
                    90 days of setup support included, extendable anytime.
                  </li>
                </ul>
              </div>

              <Button href={`/marketplace/checkout/${product.slug}`} variant="primary" className="w-full">
                Buy Theme — {product.free ? "Free" : `$${product.price}`}
              </Button>
            </Reveal>
          </div>
        </Container>
      </section>

      {related.length > 0 && (
        <section className="border-t border-[var(--color-border)] py-24 sm:py-32">
          <Container>
            <Reveal>
              <Eyebrow>Related Themes</Eyebrow>
              <h2 className="mt-6 max-w-[520px] text-balance text-[28px] font-medium leading-[1.1] tracking-[-0.02em] sm:text-[36px]">
                More in {product.category}.
              </h2>
            </Reveal>

            <RevealGroup className="mt-10 grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
              {related.map((p) => (
                <RevealItem key={p.slug}>
                  <ThemeCard product={p} />
                </RevealItem>
              ))}
            </RevealGroup>
          </Container>
        </section>
      )}
    </>
  );
}
