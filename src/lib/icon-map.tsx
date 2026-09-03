import {
  Code2,
  Cloud,
  Sparkles,
  Palette,
  BarChart3,
  ShieldCheck,
  Building2,
  Rocket,
  ShoppingBag,
  Landmark,
  HeartPulse,
  LayoutGrid,
  type LucideIcon,
} from "lucide-react";

export const iconMap: Record<string, LucideIcon> = {
  Code2,
  Cloud,
  Sparkles,
  Palette,
  BarChart3,
  ShieldCheck,
  Building2,
  Rocket,
  ShoppingBag,
  Landmark,
  HeartPulse,
  LayoutGrid,
};

export function Icon({ name, className }: { name: string; className?: string }) {
  const Cmp = iconMap[name] ?? Sparkles;
  return <Cmp className={className} />;
}
