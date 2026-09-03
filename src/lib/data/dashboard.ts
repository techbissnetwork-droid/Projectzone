// Mock customer-account data for the /dashboard route tree.
// In a real deployment these would come from the account/provisioning API —
// each function below marks where that call would slot in.

export type SiteStatus = "draft" | "building" | "ready" | "live";

export interface MySite {
  id: string;
  name: string;
  themeSlug: string;
  domain: string | null;
  status: SiteStatus;
  ssl: boolean;
  lastUpdate: string;
  hostingPlan: string;
}

export interface DomainRecord {
  id: string;
  name: string;
  status: "active" | "pending";
  expires: string;
  siteId: string | null;
}

export interface Mailbox {
  id: string;
  address: string;
  storageUsedGB: number;
  storageLimitGB: number;
  siteId: string | null;
}

export interface Invoice {
  id: string;
  date: string;
  amount: number;
  status: "paid" | "due";
  description: string;
}

export interface SupportTicket {
  id: string;
  subject: string;
  status: "open" | "resolved";
  updated: string;
}

export interface ChecklistItem {
  key: string;
  label: string;
  done: boolean;
}

export const mySites: MySite[] = [
  {
    id: "site-1",
    name: "Ember & Oak",
    themeSlug: "restaurant-pro",
    domain: "emberandoak.com",
    status: "live",
    ssl: true,
    lastUpdate: "2026-08-29",
    hostingPlan: "Business Hosting",
  },
  {
    id: "site-2",
    name: "Vantage Partners",
    themeSlug: "meridian-corporate",
    domain: "vantagepartners.io",
    status: "live",
    ssl: true,
    lastUpdate: "2026-08-22",
    hostingPlan: "Enterprise Hosting",
  },
  {
    id: "site-3",
    name: "Alex Rivera — Portfolio",
    themeSlug: "folio-personal",
    domain: null,
    status: "building",
    ssl: false,
    lastUpdate: "2026-09-01",
    hostingPlan: "Starter Hosting",
  },
  {
    id: "site-4",
    name: "North & Bloom Studio",
    themeSlug: "studio-agency",
    domain: "northbloom.studio",
    status: "ready",
    ssl: true,
    lastUpdate: "2026-08-30",
    hostingPlan: "Business Hosting",
  },
  {
    id: "site-5",
    name: "Flux Apparel Co.",
    themeSlug: "flux-fashion",
    domain: null,
    status: "draft",
    ssl: false,
    lastUpdate: "2026-08-14",
    hostingPlan: "Starter Hosting",
  },
];

export const domains: DomainRecord[] = [
  {
    id: "dom-1",
    name: "emberandoak.com",
    status: "active",
    expires: "2027-03-12",
    siteId: "site-1",
  },
  {
    id: "dom-2",
    name: "vantagepartners.io",
    status: "active",
    expires: "2027-01-05",
    siteId: "site-2",
  },
  {
    id: "dom-3",
    name: "northbloom.studio",
    status: "active",
    expires: "2026-11-30",
    siteId: "site-4",
  },
  {
    id: "dom-4",
    name: "fluxapparel.co",
    status: "pending",
    expires: "2027-09-02",
    siteId: "site-5",
  },
];

export const mailboxes: Mailbox[] = [
  {
    id: "mbx-1",
    address: "hello@emberandoak.com",
    storageUsedGB: 4.2,
    storageLimitGB: 25,
    siteId: "site-1",
  },
  {
    id: "mbx-2",
    address: "reservations@emberandoak.com",
    storageUsedGB: 1.1,
    storageLimitGB: 25,
    siteId: "site-1",
  },
  {
    id: "mbx-3",
    address: "team@vantagepartners.io",
    storageUsedGB: 12.8,
    storageLimitGB: 50,
    siteId: "site-2",
  },
  {
    id: "mbx-4",
    address: "studio@northbloom.studio",
    storageUsedGB: 3.4,
    storageLimitGB: 25,
    siteId: "site-4",
  },
];

export const invoices: Invoice[] = [
  {
    id: "inv-2026-08",
    date: "2026-08-01",
    amount: 89,
    status: "paid",
    description: "Business Hosting — August",
  },
  {
    id: "inv-2026-07",
    date: "2026-07-01",
    amount: 89,
    status: "paid",
    description: "Business Hosting — July",
  },
  {
    id: "inv-2026-06",
    date: "2026-06-01",
    amount: 249,
    status: "paid",
    description: "Meridian Corporate — theme license",
  },
  {
    id: "inv-2026-05",
    date: "2026-05-01",
    amount: 189,
    status: "paid",
    description: "Restaurant Pro — theme license",
  },
  {
    id: "inv-2026-04",
    date: "2026-04-01",
    amount: 49,
    status: "paid",
    description: "Business Email — 3 mailboxes",
  },
  {
    id: "inv-2026-09",
    date: "2026-09-01",
    amount: 89,
    status: "due",
    description: "Business Hosting — September",
  },
];

export const supportTickets: SupportTicket[] = [
  {
    id: "tkt-4821",
    subject: "SSL certificate not renewing on northbloom.studio",
    status: "open",
    updated: "2026-09-02",
  },
  {
    id: "tkt-4790",
    subject: "How do I add a second mailbox to Vantage Partners?",
    status: "resolved",
    updated: "2026-08-25",
  },
  {
    id: "tkt-4712",
    subject: "Restaurant Pro — online ordering menu sync question",
    status: "resolved",
    updated: "2026-08-10",
  },
  {
    id: "tkt-4655",
    subject: "Requesting invoice history export for accounting",
    status: "open",
    updated: "2026-08-30",
  },
];

export function getSite(id: string): MySite | undefined {
  return mySites.find((s) => s.id === id);
}

// Deterministic per-site mix so the checklist looks plausible without randomness.
export function launchChecklist(siteId: string): ChecklistItem[] {
  const site = getSite(siteId);
  const status = site?.status ?? "draft";

  const base: ChecklistItem[] = [
    { key: "theme", label: "Theme purchased", done: true },
    { key: "brand", label: "Brand configured", done: false },
    { key: "content", label: "Content added", done: false },
    { key: "domain", label: "Domain connected", done: !!site?.domain },
    { key: "hosting", label: "Hosting configured", done: true },
    { key: "ssl", label: "SSL enabled", done: !!site?.ssl },
    { key: "email", label: "Business email configured", done: false },
    { key: "payments", label: "Payment system connected", done: false },
    { key: "seo", label: "SEO configured", done: false },
    { key: "mobile", label: "Mobile checked", done: false },
  ];

  if (status === "live") {
    return base.map((item) => ({ ...item, done: true }));
  }
  if (status === "ready") {
    return base.map((item) =>
      ["theme", "brand", "content", "hosting", "seo", "mobile"].includes(item.key)
        ? { ...item, done: true }
        : item,
    );
  }
  if (status === "building") {
    return base.map((item) =>
      ["theme", "brand", "content"].includes(item.key) ? { ...item, done: true } : item,
    );
  }
  // draft
  return base;
}
