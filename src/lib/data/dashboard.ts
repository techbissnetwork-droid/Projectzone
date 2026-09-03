export type WebsiteStatus = "Draft" | "Building" | "Ready" | "Live" | "Maintenance";

export type Website = {
  id: string;
  name: string;
  domain: string | null;
  product: string;
  status: WebsiteStatus;
  hosting: "Active" | "Pending" | "Not connected";
  ssl: "Active" | "Pending" | "Not connected" | "Expiring";
  lastUpdate: string;
  visitors30d: number;
  accent: string;
};

export const websites: Website[] = [
  {
    id: "ws_himalayan",
    name: "Himalayan Kitchen",
    domain: "himalayankitchen.com",
    product: "Restaurant Pro",
    status: "Live",
    hosting: "Active",
    ssl: "Active",
    lastUpdate: "2 days ago",
    visitors30d: 8420,
    accent: "#C8A165",
  },
  {
    id: "ws_meridian",
    name: "Meridian Realty",
    domain: "meridianrealty.io",
    product: "Meridian Realty",
    status: "Live",
    hosting: "Active",
    ssl: "Active",
    lastUpdate: "1 week ago",
    visitors30d: 15230,
    accent: "#4A72F2",
  },
  {
    id: "ws_atlas",
    name: "Atlas Consulting",
    domain: "atlasconsulting.co",
    product: "Corporate Summit",
    status: "Building",
    hosting: "Pending",
    ssl: "Pending",
    lastUpdate: "3 hours ago",
    visitors30d: 0,
    accent: "#4A72F2",
  },
  {
    id: "ws_novafit",
    name: "Nova Fitness",
    domain: null,
    product: "Vantage Portfolio",
    status: "Draft",
    hosting: "Not connected",
    ssl: "Not connected",
    lastUpdate: "Just now",
    visitors30d: 0,
    accent: "#E6CD9C",
  },
  {
    id: "ws_wellpoint",
    name: "Wellpoint Clinic",
    domain: "wellpointclinic.com",
    product: "Wellpoint Clinic",
    status: "Maintenance",
    hosting: "Active",
    ssl: "Expiring",
    lastUpdate: "Today",
    visitors30d: 5310,
    accent: "#E5595D",
  },
];

export type Domain = {
  name: string;
  status: "Connected" | "Pending" | "Expiring" | "Expired" | "Available";
  registrar: string;
  expires: string;
  autoRenew: boolean;
};

export const domains: Domain[] = [
  { name: "himalayankitchen.com", status: "Connected", registrar: "TECHBISS Domains", expires: "Mar 14, 2027", autoRenew: true },
  { name: "meridianrealty.io", status: "Connected", registrar: "TECHBISS Domains", expires: "Nov 2, 2026", autoRenew: true },
  { name: "atlasconsulting.co", status: "Pending", registrar: "TECHBISS Domains", expires: "—", autoRenew: false },
  { name: "wellpointclinic.com", status: "Expiring", registrar: "TECHBISS Domains", expires: "Sep 18, 2026", autoRenew: false },
];

export type HostingPlan = {
  site: string;
  plan: "Starter" | "Growth" | "Scale";
  status: "Active" | "Pending" | "Suspended" | "Renewal required";
  usage: number;
  region: string;
  renews: string;
};

export const hostingPlans: HostingPlan[] = [
  { site: "himalayankitchen.com", plan: "Growth", status: "Active", usage: 42, region: "US East", renews: "Oct 1, 2026" },
  { site: "meridianrealty.io", plan: "Scale", status: "Active", usage: 68, region: "US East", renews: "Dec 4, 2026" },
  { site: "atlasconsulting.co", plan: "Starter", status: "Pending", usage: 0, region: "EU West", renews: "—" },
  { site: "wellpointclinic.com", plan: "Growth", status: "Renewal required", usage: 55, region: "US East", renews: "Overdue" },
];

export type SSLCert = {
  domain: string;
  status: "Active" | "Pending" | "Expiring" | "Expired";
  issuer: string;
  expires: string;
};

export const sslCerts: SSLCert[] = [
  { domain: "himalayankitchen.com", status: "Active", issuer: "TECHBISS Secure CA", expires: "Mar 14, 2027" },
  { domain: "meridianrealty.io", status: "Active", issuer: "TECHBISS Secure CA", expires: "Nov 2, 2026" },
  { domain: "atlasconsulting.co", status: "Pending", issuer: "—", expires: "—" },
  { domain: "wellpointclinic.com", status: "Expiring", issuer: "TECHBISS Secure CA", expires: "Sep 18, 2026" },
];

export type Mailbox = {
  address: string;
  user: string;
  storage: number;
  status: "Active" | "Pending";
};

export const mailboxes: Mailbox[] = [
  { address: "hello@himalayankitchen.com", user: "Reception", storage: 34, status: "Active" },
  { address: "orders@himalayankitchen.com", user: "Orders Team", storage: 12, status: "Active" },
  { address: "info@meridianrealty.io", user: "Front Desk", storage: 58, status: "Active" },
  { address: "team@atlasconsulting.co", user: "Atlas Team", storage: 0, status: "Pending" },
];

export type Invoice = {
  id: string;
  description: string;
  date: string;
  amountCents: number;
  status: "Paid" | "Due" | "Overdue";
};

export const invoices: Invoice[] = [
  { id: "INV-2026-0114", description: "Restaurant Pro — theme license", date: "Jan 14, 2026", amountCents: 24900, status: "Paid" },
  { id: "INV-2026-0114-2", description: "Growth Hosting — himalayankitchen.com", date: "Jan 14, 2026", amountCents: 4900, status: "Paid" },
  { id: "INV-2026-0201", description: "Meridian Realty — custom development", date: "Feb 1, 2026", amountCents: 480000, status: "Paid" },
  { id: "INV-2026-0801", description: "Growth Hosting — wellpointclinic.com", date: "Aug 1, 2026", amountCents: 4900, status: "Overdue" },
  { id: "INV-2026-0901", description: "Scale Hosting — meridianrealty.io", date: "Sep 1, 2026", amountCents: 9900, status: "Due" },
];

export type SupportTicket = {
  id: string;
  subject: string;
  site: string;
  status: "Open" | "In Progress" | "Resolved";
  priority: "Low" | "Normal" | "High";
  updated: string;
};

export const supportTickets: SupportTicket[] = [
  { id: "TCK-4821", subject: "SSL renewal failing on wellpointclinic.com", site: "wellpointclinic.com", status: "In Progress", priority: "High", updated: "2 hours ago" },
  { id: "TCK-4790", subject: "Add delivery zone for new neighborhood", site: "himalayankitchen.com", status: "Open", priority: "Normal", updated: "1 day ago" },
  { id: "TCK-4712", subject: "Question about CRM integration", site: "meridianrealty.io", status: "Resolved", priority: "Low", updated: "5 days ago" },
];

export const analyticsSummary = {
  visitors30d: 29420,
  conversion: 3.8,
  avgLoad: 0.9,
  topSources: [
    { source: "Organic Search", pct: 44 },
    { source: "Direct", pct: 27 },
    { source: "Social", pct: 18 },
    { source: "Referral", pct: 11 },
  ],
};
