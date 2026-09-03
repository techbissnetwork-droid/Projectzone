import type { Metadata } from "next";
import { DashboardPageHeader } from "@/components/dashboard/page-header";
import { SettingsForm } from "@/components/dashboard/settings-form";

export const metadata: Metadata = { title: "Settings" };

export default function SettingsPage() {
  return (
    <div>
      <DashboardPageHeader title="Settings" subtitle="Manage your account and preferences." />
      <SettingsForm />
    </div>
  );
}
