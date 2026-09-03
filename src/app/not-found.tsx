import type { Metadata } from "next";
import { Container } from "@/components/ui/Container";
import { Eyebrow } from "@/components/ui/Eyebrow";
import { Button } from "@/components/ui/Button";

export const metadata: Metadata = {
  title: "Page Not Found",
  robots: { index: false, follow: false },
};

export default function NotFound() {
  return (
    <section className="relative flex min-h-[80vh] items-center justify-center overflow-hidden py-32">
      <div
        aria-hidden
        className="pointer-events-none absolute inset-x-0 top-1/3 -z-10 h-[500px]"
        style={{
          background:
            "radial-gradient(45% 60% at 50% 50%, rgba(201,168,118,0.10), transparent)",
        }}
      />
      <Container className="flex flex-col items-center gap-6 text-center">
        <Eyebrow tone="gold">404</Eyebrow>
        <h1 className="text-h1 max-w-2xl text-balance font-medium text-paper">
          This page didn&apos;t make it online.
        </h1>
        <p className="text-lead max-w-md text-balance text-paper-dim">
          The page you&apos;re looking for doesn&apos;t exist, or it&apos;s
          moved. Let&apos;s get you back on track.
        </p>
        <div className="flex flex-col gap-4 pt-4 sm:flex-row">
          <Button href="/" size="lg">
            Back to Home
          </Button>
          <Button href="/contact" variant="secondary" size="lg">
            Contact Us
          </Button>
        </div>
      </Container>
    </section>
  );
}
