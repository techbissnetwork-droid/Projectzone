import type { MetadataRoute } from "next";
import { site } from "@/lib/data/site";
import { detailedServices } from "@/lib/data/services";

export default function sitemap(): MetadataRoute.Sitemap {
  const now = new Date();

  const staticRoutes = [
    { path: "", priority: 1 },
    { path: "/services", priority: 0.9 },
    { path: "/solutions", priority: 0.8 },
    { path: "/work", priority: 0.8 },
    { path: "/process", priority: 0.7 },
    { path: "/about", priority: 0.6 },
    { path: "/contact", priority: 0.7 },
  ].map(({ path, priority }) => ({
    url: `${site.url}${path}`,
    lastModified: now,
    changeFrequency: "monthly" as const,
    priority,
  }));

  const serviceRoutes = detailedServices.map((service) => ({
    url: `${site.url}/services/${service.slug}`,
    lastModified: now,
    changeFrequency: "monthly" as const,
    priority: 0.7,
  }));

  return [...staticRoutes, ...serviceRoutes];
}
