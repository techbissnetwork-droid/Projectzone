import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { LaunchChecklist } from "@/components/dashboard/launch-checklist";
import { getSite, launchChecklist } from "@/lib/data/dashboard";

export async function generateMetadata({
  params,
}: {
  params: Promise<{ siteId: string }>;
}): Promise<Metadata> {
  const { siteId } = await params;
  const site = getSite(siteId);
  return {
    title: site ? `Launch — ${site.name}` : "Launch Website",
    description: "Complete your launch checklist and take your website live.",
  };
}

export default async function LaunchPage({
  params,
}: {
  params: Promise<{ siteId: string }>;
}) {
  const { siteId } = await params;
  const site = getSite(siteId);
  if (!site) notFound();

  const items = launchChecklist(siteId);

  return <LaunchChecklist site={site} initialItems={items} />;
}
