import { Suspense } from "react";
import { Section } from "@/components/ui/Section";
import { Container } from "@/components/ui/Container";
import { PageHero } from "@/components/ui/PageHero";
import { CheckoutFlow } from "@/components/marketplace/CheckoutFlow";

export default function CheckoutPage() {
  return (
    <>
      <PageHero eyebrow="Checkout" title="Complete your purchase" description="Secure, test-mode checkout — your card is never actually charged in this demo environment." />
      <Section size="tight">
        <Container>
          <Suspense fallback={<div className="py-20 text-center text-sm text-(--color-ink-faint)">Loading checkout...</div>}>
            <CheckoutFlow />
          </Suspense>
        </Container>
      </Section>
    </>
  );
}
