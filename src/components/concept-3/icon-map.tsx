import {
  Globe,
  LayoutDashboard,
  Smartphone,
  Building2,
  Server,
  ShieldCheck,
  Mail,
  Sparkles,
  LifeBuoy,
  type LucideIcon,
} from "lucide-react";

/** Maps the `icon` string field on site-data records to a lucide-react component. */
export const iconMap: Record<string, LucideIcon> = {
  Globe,
  LayoutDashboard,
  Smartphone,
  Building2,
  Server,
  ShieldCheck,
  Mail,
  Sparkles,
  LifeBuoy,
};

export function getIcon(name: string): LucideIcon {
  return iconMap[name] ?? Sparkles;
}
