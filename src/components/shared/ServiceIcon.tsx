import {
  Globe,
  Smartphone,
  Workflow,
  Fingerprint,
  Server,
  ShieldCheck,
  Mail,
  ShoppingCart,
  AppWindow,
  Zap,
  CreditCard,
  LifeBuoy,
  type LucideProps,
} from "lucide-react";
import type { IconName } from "@/lib/data/services";

const iconMap: Record<IconName, React.ComponentType<LucideProps>> = {
  Globe,
  Smartphone,
  Workflow,
  Fingerprint,
  Server,
  ShieldCheck,
  Mail,
  ShoppingCart,
  AppWindow,
  Zap,
  CreditCard,
  LifeBuoy,
};

export function ServiceIcon({
  name,
  ...props
}: { name: IconName } & LucideProps) {
  const Icon = iconMap[name];
  return <Icon {...props} />;
}
