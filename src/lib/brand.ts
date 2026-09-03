import { Product } from "@/lib/data/marketplace";

export type BrandFont = "Modern Sans" | "Classic Serif" | "Rounded";

export type BrandState = {
  businessName: string;
  industry: string;
  primaryColor: string;
  secondaryColor: string;
  font: BrandFont;
  headline: string;
  tagline: string;
  phone: string;
  email: string;
  address: string;
  hours: string;
  domain: string;
  services: string[];
};

export const colorSwatches = [
  "#C8A165",
  "#D9B77C",
  "#4A72F2",
  "#34C77B",
  "#E5595D",
  "#E8A93B",
  "#0C0D10",
  "#8A8F98",
];

export const fontOptions: BrandFont[] = ["Modern Sans", "Classic Serif", "Rounded"];

export function defaultBrandState(product: Product): BrandState {
  return {
    businessName: product.name,
    industry: product.industry,
    primaryColor: product.accent,
    secondaryColor: "#0C0D10",
    font: "Modern Sans",
    headline: `${product.industry} built for growth.`,
    tagline: product.tagline,
    phone: "+1 (555) 010-2040",
    email: `hello@${product.name.toLowerCase().replace(/\s+/g, "")}.com`,
    address: "123 Market Street, Your City",
    hours: "Mon–Sat, 9am–8pm",
    domain: `${product.name.toLowerCase().replace(/\s+/g, "")}.com`,
    services: product.features.slice(0, 4),
  };
}
