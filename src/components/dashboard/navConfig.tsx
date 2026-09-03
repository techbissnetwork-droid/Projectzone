import { Building2, CreditCard, LayoutDashboard, LifeBuoy, Package, Settings, Store, Ticket, Users } from "lucide-react";
import type { NavItem } from "@/components/dashboard/DashboardShell";

export const clientNav: NavItem[] = [
  { label: "Overview", href: "/dashboard/client", icon: LayoutDashboard },
  { label: "My Products", href: "/dashboard/client/products", icon: Package },
  { label: "Billing", href: "/dashboard/client/billing", icon: CreditCard },
  { label: "Support", href: "/dashboard/client/support", icon: LifeBuoy },
  { label: "Settings", href: "/dashboard/client/settings", icon: Settings },
];

export const staffNav: NavItem[] = [
  { label: "Overview", href: "/dashboard/staff", icon: LayoutDashboard },
  { label: "My Clients", href: "/dashboard/staff/clients", icon: Building2 },
  { label: "Ticket Queue", href: "/dashboard/staff/tickets", icon: Ticket },
  { label: "Settings", href: "/dashboard/staff/settings", icon: Settings },
];

export const adminNav: NavItem[] = [
  { label: "Overview", href: "/dashboard/admin", icon: LayoutDashboard },
  { label: "Clients", href: "/dashboard/admin/clients", icon: Building2 },
  { label: "Marketplace", href: "/dashboard/admin/marketplace", icon: Store },
  { label: "Staff", href: "/dashboard/admin/staff", icon: Users },
  { label: "Settings", href: "/dashboard/admin/settings", icon: Settings },
];
