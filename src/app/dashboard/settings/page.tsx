import type { Metadata } from "next";
import { PageHeader } from "@/components/dashboard/page-header";
import { SettingsForms } from "@/components/dashboard/settings-forms";

export const metadata: Metadata = {
  title: "Settings",
  description: "Manage your profile, security and notification preferences.",
};

export default function SettingsPage() {
  return (
    <div className="flex flex-col gap-8">
      <PageHeader
        eyebrow="Platform"
        title="Settings"
        description="Manage your profile, security and how TECHBISS notifies you."
      />
      <SettingsForms />
    </div>
  );
}
