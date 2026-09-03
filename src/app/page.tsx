import { Hero } from "@/components/home/Hero";
import { OfflineToOnline } from "@/components/home/OfflineToOnline";
import { ServicesShowcase } from "@/components/home/ServicesShowcase";
import { ArchitectureDiagram } from "@/components/home/ArchitectureDiagram";
import { ProcessPreview } from "@/components/home/ProcessPreview";
import { TransformationExamples } from "@/components/home/TransformationExamples";
import { CaseStudiesPreview } from "@/components/home/CaseStudiesPreview";
import { TechSection } from "@/components/home/TechSection";
import { FinalCTA } from "@/components/home/FinalCTA";

export default function HomePage() {
  return (
    <>
      <Hero />
      <OfflineToOnline />
      <ServicesShowcase />
      <ArchitectureDiagram />
      <ProcessPreview />
      <TransformationExamples />
      <CaseStudiesPreview />
      <TechSection />
      <FinalCTA />
    </>
  );
}
