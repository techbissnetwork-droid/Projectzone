export type InstallType = "fresh" | "migrate" | "import";

export type InstallerState = {
  url: string;
  detected: boolean;
  installType: InstallType | null;
  siteName: string;
  adminUser: string;
  adminEmail: string;
  adminPassword: string;
  timezone: string;
  theme: string | null;
};

export const initialInstallerState: InstallerState = {
  url: "",
  detected: false,
  installType: null,
  siteName: "",
  adminUser: "",
  adminEmail: "",
  adminPassword: "",
  timezone: "UTC (Coordinated Universal Time)",
  theme: null,
};
