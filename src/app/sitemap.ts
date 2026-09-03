import type { MetadataRoute } from "next";
import { services } from "@/lib/data/services";
import { solutions } from "@/lib/data/solutions";
import { products } from "@/lib/data/marketplace";
import { caseStudies } from "@/lib/data/work";

const base = "https://techbiss.com";

export default function sitemap(): MetadataRoute.Sitemap {
  const staticRoutes = [
    "",
    "/services",
    "/solutions",
    "/marketplace",
    "/marketplace/sell",
    "/work",
    "/process",
    "/about",
    "/contact",
  ].map((path) => ({
    url: `${base}${path}`,
    lastModified: new Date(),
  }));

  const serviceRoutes = services.map((s) => ({
    url: `${base}/services/${s.slug}`,
    lastModified: new Date(),
  }));

  const solutionRoutes = solutions.map((s) => ({
    url: `${base}/solutions/${s.slug}`,
    lastModified: new Date(),
  }));

  const productRoutes = products.map((p) => ({
    url: `${base}/marketplace/product/${p.slug}`,
    lastModified: new Date(),
  }));

  const workRoutes = caseStudies.map((c) => ({
    url: `${base}/work/${c.slug}`,
    lastModified: new Date(),
  }));

  return [...staticRoutes, ...serviceRoutes, ...solutionRoutes, ...productRoutes, ...workRoutes];
}
