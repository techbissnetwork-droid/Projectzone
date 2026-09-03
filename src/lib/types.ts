export type Service = {
  slug: string;
  name: string;
  short: string;
  description: string;
  icon: string;
  capabilities: string[];
  deliverables: string[];
};

export type Solution = {
  slug: string;
  name: string;
  audience: string;
  description: string;
  icon: string;
  highlights: string[];
  stat: { value: string; label: string };
};

export type Product = {
  slug: string;
  name: string;
  category: "Business" | "E-commerce" | "SaaS" | "Portfolio" | "Agency" | "Restaurant" | "Real Estate" | "Education";
  tagline: string;
  description: string;
  price: number;
  originalPrice?: number;
  rating: number;
  reviews: number;
  sales: number;
  tags: string[];
  features: string[];
  stack: string[];
  gradient: [string, string];
  featured?: boolean;
  new?: boolean;
};

export type CaseStudy = {
  slug: string;
  client: string;
  industry: string;
  title: string;
  summary: string;
  challenge: string;
  solution: string;
  results: { value: string; label: string }[];
  services: string[];
  gradient: [string, string];
  year: string;
  quote: { text: string; author: string; role: string };
};

export type Testimonial = {
  quote: string;
  author: string;
  role: string;
  company: string;
  rating: number;
};

export type TeamMember = {
  name: string;
  role: string;
  bio: string;
  initials: string;
};

export type Article = {
  slug: string;
  title: string;
  category: "Guide" | "Insight" | "Playbook" | "Report" | "Webinar";
  excerpt: string;
  content: string[];
  readTime: string;
  date: string;
  author: string;
};

export type ProcessStep = {
  number: string;
  title: string;
  description: string;
  deliverables: string[];
  duration: string;
};
