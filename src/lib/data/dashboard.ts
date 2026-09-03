export const clientOwnedProducts = [
  { name: "Orbit SaaS Dashboard", status: "Live", url: "app.acmecorp.com", updated: "2 days ago", gradient: ["#4b5bff", "#17c3ff"] as [string, string] },
  { name: "Summit Corporate", status: "Live", url: "www.acmecorp.com", updated: "1 week ago", gradient: ["#0ea5e9", "#4b5bff"] as [string, string] },
];

export const invoices = [
  { id: "INV-2026-0142", date: "Feb 18, 2026", amount: "$249.00", status: "Paid" },
  { id: "INV-2026-0098", date: "Jan 22, 2026", amount: "$179.00", status: "Paid" },
  { id: "INV-2025-0871", date: "Dec 09, 2025", amount: "$1,200.00", status: "Paid" },
];

export const supportTickets = [
  { id: "TCK-3021", subject: "SSL certificate renewal question", status: "Open", priority: "Normal", updated: "3h ago" },
  { id: "TCK-3009", subject: "Billing address update", status: "Resolved", priority: "Low", updated: "2d ago" },
  { id: "TCK-2988", subject: "Need help with theme customization", status: "In Progress", priority: "High", updated: "5h ago" },
];

export const recentOrders = [
  { id: "TB-482910", client: "Northwind Retail", product: "Boutique Commerce", amount: "$289.00", status: "Completed", date: "Mar 1, 2026" },
  { id: "TB-482887", client: "Solstice Group", product: "Orbit SaaS Dashboard", amount: "$249.00", status: "Completed", date: "Feb 28, 2026" },
  { id: "TB-482850", client: "Ferro Industries", product: "Vertex Marketplace", amount: "$329.00", status: "Processing", date: "Feb 27, 2026" },
  { id: "TB-482801", client: "Lumen Media Group", product: "Forge Agency", amount: "$199.00", status: "Completed", date: "Feb 25, 2026" },
  { id: "TB-482766", client: "Cascade Logistics", product: "Domain Real Estate", amount: "$259.00", status: "Refunded", date: "Feb 24, 2026" },
];

export const adminClients = [
  { name: "Northwind Retail", plan: "Enterprise", mrr: "$12,400", health: "Excellent", since: "2023" },
  { name: "Solstice Group", plan: "Growth", mrr: "$4,200", health: "Good", since: "2024" },
  { name: "Ferro Industries", plan: "Enterprise", mrr: "$18,900", health: "Good", since: "2021" },
  { name: "Lumen Media Group", plan: "Growth", mrr: "$3,100", health: "Excellent", since: "2023" },
  { name: "Cascade Logistics", plan: "Enterprise", mrr: "$9,600", health: "At risk", since: "2022" },
];

export const staffDirectory = [
  { name: "Idris Osei", role: "VP of Engineering", clients: 8, status: "Active" },
  { name: "Mei Lin Zhao", role: "VP of Design", clients: 5, status: "Active" },
  { name: "Camila Duarte", role: "Head of AI", clients: 6, status: "Active" },
  { name: "Noah Bergström", role: "Client Partner", clients: 11, status: "Active" },
  { name: "Grace Whitfield", role: "Engagement Lead", clients: 4, status: "On leave" },
];

export const assignedClients = [
  { name: "Northwind Retail", engagement: "Headless commerce rebuild", phase: "Build", nextMilestone: "Sprint demo — Mar 6" },
  { name: "Aurora Health Network", engagement: "Patient platform v2", phase: "Design", nextMilestone: "Prototype review — Mar 4" },
  { name: "Voltage Analytics", engagement: "Billing migration", phase: "Launch", nextMilestone: "Go-live — Mar 10" },
];

export const staffTickets = [
  { id: "TCK-3021", client: "Acme Corp", subject: "SSL certificate renewal question", priority: "Normal", updated: "3h ago" },
  { id: "TCK-3015", client: "Northwind Retail", subject: "Checkout flow bug on mobile", priority: "High", updated: "1h ago" },
  { id: "TCK-2994", client: "Ferro Industries", subject: "Request for staging environment", priority: "Low", updated: "1d ago" },
];
