import { Section } from "@/components/ui/Section";
import { Container } from "@/components/ui/Container";
import { SectionHeading } from "@/components/ui/SectionHeading";
import { Button } from "@/components/ui/Button";

export default function Home() {
  return (
    <Section size="loose">
      <Container>
        <SectionHeading
          eyebrow="TECHBISS"
          title="Digital transformation, engineered."
          description="Placeholder home page — full build in progress."
        />
        <div className="mt-8 flex justify-center">
          <Button href="/contact" variant="secondary">
            Get Started
          </Button>
        </div>
      </Container>
    </Section>
  );
}
