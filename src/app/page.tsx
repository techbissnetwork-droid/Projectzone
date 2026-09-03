import { Hero } from "@/components/home/Hero";
import { LogoCloud } from "@/components/home/LogoCloud";
import { ServicesGrid } from "@/components/home/ServicesGrid";
import { MarketplaceTeaser } from "@/components/home/MarketplaceTeaser";
import { ProcessTeaser } from "@/components/home/ProcessTeaser";
import { Stats } from "@/components/home/Stats";
import { Testimonials } from "@/components/home/Testimonials";
import { CaseStudyHighlight } from "@/components/home/CaseStudyHighlight";
import { CtaBanner } from "@/components/home/CtaBanner";

export default function Home() {
  return (
    <>
      <Hero />
      <LogoCloud />
      <ServicesGrid />
      <MarketplaceTeaser />
      <Stats />
      <ProcessTeaser />
      <CaseStudyHighlight />
      <Testimonials />
      <CtaBanner />
    </>
  );
}
