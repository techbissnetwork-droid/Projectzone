import { Hero } from "@/components/home/hero";
import { MarqueeStrip } from "@/components/home/marquee-strip";
import { Transformation } from "@/components/home/transformation";
import { TwoPaths } from "@/components/home/two-paths";
import { MarketplaceTeaser } from "@/components/home/marketplace-teaser";
import { EcosystemGrid } from "@/components/home/ecosystem-grid";
import { Architecture } from "@/components/home/architecture";
import { BusinessTransformations } from "@/components/home/business-transformations";
import { ProcessTeaser } from "@/components/home/process-teaser";
import { WorkTeaser } from "@/components/home/work-teaser";
import { FinalCta } from "@/components/home/final-cta";

export default function Home() {
  return (
    <>
      <Hero />
      <MarqueeStrip />
      <Transformation />
      <TwoPaths />
      <MarketplaceTeaser />
      <EcosystemGrid />
      <Architecture />
      <BusinessTransformations />
      <ProcessTeaser />
      <WorkTeaser />
      <FinalCta />
    </>
  );
}
