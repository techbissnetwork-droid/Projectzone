import { Hero } from "@/components/home/hero";
import { Transformation } from "@/components/home/transformation";
import { TwoWays } from "@/components/home/two-ways";
import { MarketplaceTeaser } from "@/components/home/marketplace-teaser";
import { ServicesEcosystem } from "@/components/home/services-ecosystem";
import { Architecture } from "@/components/home/architecture";
import { SolutionsTeaser } from "@/components/home/solutions-teaser";
import { ProcessTeaser } from "@/components/home/process-teaser";
import { WorkTeaser } from "@/components/home/work-teaser";
import { FinalCta } from "@/components/home/final-cta";

export default function Home() {
  return (
    <>
      <Hero />
      <Transformation />
      <TwoWays />
      <MarketplaceTeaser />
      <ServicesEcosystem />
      <Architecture />
      <SolutionsTeaser />
      <ProcessTeaser />
      <WorkTeaser />
      <FinalCta />
    </>
  );
}
