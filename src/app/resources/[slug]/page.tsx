import type { Metadata } from "next";
import { notFound } from "next/navigation";
import Link from "next/link";
import { ChevronRight } from "lucide-react";
import { Section } from "@/components/ui/Section";
import { Container } from "@/components/ui/Container";
import { Badge } from "@/components/ui/Badge";
import { Reveal } from "@/components/ui/Reveal";
import { CtaBanner } from "@/components/home/CtaBanner";
import { articles } from "@/lib/data/resources";

export function generateStaticParams() {
  return articles.map((a) => ({ slug: a.slug }));
}

export async function generateMetadata(props: PageProps<"/resources/[slug]">): Promise<Metadata> {
  const { slug } = await props.params;
  const article = articles.find((a) => a.slug === slug);
  if (!article) return {};
  return { title: article.title, description: article.excerpt };
}

export default async function ArticlePage(props: PageProps<"/resources/[slug]">) {
  const { slug } = await props.params;
  const article = articles.find((a) => a.slug === slug);
  if (!article) notFound();

  const more = articles.filter((a) => a.slug !== article.slug).slice(0, 3);

  return (
    <>
      <Section size="tight">
        <Container size="narrow">
          <nav className="mb-8 flex items-center gap-1.5 text-sm text-(--color-ink-faint)">
            <Link href="/resources" className="hover:text-(--color-ink-muted)">
              Resources
            </Link>
            <ChevronRight className="h-3.5 w-3.5" />
            <span className="text-(--color-ink)">{article.category}</span>
          </nav>

          <Reveal>
            <Badge variant="accent">{article.category}</Badge>
            <h1 className="mt-5 text-balance text-3xl font-medium leading-tight tracking-tight text-(--color-ink) sm:text-4xl">
              {article.title}
            </h1>
            <div className="mt-5 flex items-center gap-2 text-sm text-(--color-ink-faint)">
              <span>{article.author}</span>
              <span>·</span>
              <span>{article.date}</span>
              <span>·</span>
              <span>{article.readTime}</span>
            </div>
          </Reveal>

          <Reveal delay={0.1} className="mt-10 flex flex-col gap-5">
            {article.content.map((paragraph, i) => (
              <p key={i} className="text-base leading-relaxed text-(--color-ink-muted)">
                {paragraph}
              </p>
            ))}
          </Reveal>
        </Container>
      </Section>

      {more.length > 0 && (
        <Section theme="light">
          <Container>
            <h2 className="mb-8 text-2xl font-medium tracking-tight text-(--color-ink)">More resources</h2>
            <div className="grid grid-cols-1 gap-5 sm:grid-cols-3">
              {more.map((a) => (
                <Link
                  key={a.slug}
                  href={`/resources/${a.slug}`}
                  className="focus-ring group rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                >
                  <Badge variant="outline">{a.category}</Badge>
                  <h3 className="mt-3 text-base font-medium text-(--color-ink)">{a.title}</h3>
                  <p className="mt-2 text-sm text-(--color-ink-muted) line-clamp-2">{a.excerpt}</p>
                </Link>
              ))}
            </div>
          </Container>
        </Section>
      )}

      <CtaBanner />
    </>
  );
}
