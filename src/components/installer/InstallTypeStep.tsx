"use client";

import * as React from "react";
import { motion } from "framer-motion";
import { ArrowLeft, ArrowRight, FilePlus2, RefreshCcw, Sparkles, Upload } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { ProgressBar } from "@/components/ui/ProgressBar";
import { ScanList, type ScanItem } from "@/components/installer/ScanList";
import { cn } from "@/lib/utils";
import type { InstallType } from "@/components/installer/types";

const options: { key: InstallType; icon: typeof Sparkles; title: string; description: string }[] = [
  {
    key: "fresh",
    icon: Sparkles,
    title: "Fresh Install",
    description: "Start with a clean environment — recommended for new domains.",
  },
  {
    key: "migrate",
    icon: RefreshCcw,
    title: "Migrate Existing Site",
    description: "We'll detect your current site and migrate its content safely.",
  },
  {
    key: "import",
    icon: FilePlus2,
    title: "Import from Backup",
    description: "Upload a backup archive to restore content into your new site.",
  },
];

const migrateChecks: ScanItem[] = [
  { label: "Connecting to existing site", detail: "Connected" },
  { label: "Identifying current platform", detail: "WordPress 6.4 detected" },
  { label: "Scanning content & database", detail: "1,204 records found" },
  { label: "Scanning media library", detail: "3.1 GB across 842 files" },
  { label: "Checking for conflicts", detail: "No blocking conflicts" },
];

export function InstallTypeStep({
  installType,
  setInstallType,
  onBack,
  onContinue,
}: {
  installType: InstallType | null;
  setInstallType: (v: InstallType) => void;
  onBack: () => void;
  onContinue: () => void;
}) {
  const [migrateScanDone, setMigrateScanDone] = React.useState(false);
  const [uploadProgress, setUploadProgress] = React.useState(0);
  const [uploading, setUploading] = React.useState(false);
  const [fileName, setFileName] = React.useState("");

  function selectType(type: InstallType) {
    setInstallType(type);
    setMigrateScanDone(false);
    setUploadProgress(0);
    setUploading(false);
  }

  function simulateUpload(name: string) {
    setFileName(name);
    setUploading(true);
    setUploadProgress(0);
    const interval = setInterval(() => {
      setUploadProgress((p) => {
        if (p >= 100) {
          clearInterval(interval);
          setUploading(false);
          return 100;
        }
        return Math.min(100, p + Math.random() * 22 + 8);
      });
    }, 260);
  }

  const canContinue =
    installType === "fresh" ||
    (installType === "migrate" && migrateScanDone) ||
    (installType === "import" && uploadProgress >= 100);

  return (
    <div className="rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6 sm:p-8">
      <h2 className="text-lg font-medium text-(--color-ink)">How should we set up your site?</h2>
      <p className="mt-1 text-sm text-(--color-ink-muted)">Choose the installation path that fits your situation.</p>

      <div className="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
        {options.map(({ key, icon: Icon, title, description }) => (
          <button
            key={key}
            type="button"
            onClick={() => selectType(key)}
            className={cn(
              "focus-ring flex flex-col items-start gap-3 rounded-(--radius-md) border p-5 text-left transition-all duration-200",
              installType === key
                ? "border-(--color-accent) bg-(--color-accent)/8"
                : "border-(--color-border-strong) hover:bg-(--color-surface-raised)",
            )}
          >
            <Icon className={cn("h-5 w-5", installType === key ? "text-(--color-accent-2)" : "text-(--color-ink-faint)")} />
            <div>
              <p className="text-sm font-medium text-(--color-ink)">{title}</p>
              <p className="mt-1 text-xs leading-relaxed text-(--color-ink-muted)">{description}</p>
            </div>
          </button>
        ))}
      </div>

      {installType === "migrate" && (
        <motion.div initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} className="mt-6">
          <p className="mb-3 text-sm font-medium text-(--color-ink)">Existing-site detection</p>
          <ScanList items={migrateChecks} onComplete={() => setMigrateScanDone(true)} />
        </motion.div>
      )}

      {installType === "import" && (
        <motion.div initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} className="mt-6">
          <p className="mb-3 text-sm font-medium text-(--color-ink)">Upload backup archive</p>
          {!fileName ? (
            <button
              type="button"
              onClick={() => simulateUpload("site-backup.zip")}
              className="focus-ring flex w-full flex-col items-center gap-2 rounded-(--radius-md) border border-dashed border-(--color-border-strong) py-10 text-center transition-colors hover:bg-(--color-surface-raised)"
            >
              <Upload className="h-5 w-5 text-(--color-ink-faint)" />
              <span className="text-sm text-(--color-ink)">Click to select a .zip backup file</span>
              <span className="text-xs text-(--color-ink-faint)">or drag and drop — up to 2GB</span>
            </button>
          ) : (
            <div className="rounded-(--radius-md) border border-(--color-border) p-4">
              <div className="flex items-center justify-between text-sm">
                <span className="text-(--color-ink)">{fileName}</span>
                <span className="text-(--color-ink-faint)">{Math.min(100, Math.round(uploadProgress))}%</span>
              </div>
              <ProgressBar value={uploadProgress} className="mt-3" />
              <p className="mt-2 text-xs text-(--color-ink-faint)">
                {uploadProgress >= 100 ? "Upload complete — ready to import" : "Uploading & validating archive..."}
              </p>
            </div>
          )}
        </motion.div>
      )}

      <div className="mt-8 flex gap-3">
        <Button variant="outline" size="lg" icon={<ArrowLeft className="h-4 w-4" />} iconPosition="left" onClick={onBack}>
          Back
        </Button>
        <Button
          variant="secondary"
          size="lg"
          className="flex-1"
          icon={<ArrowRight className="h-4 w-4" />}
          disabled={!canContinue}
          onClick={onContinue}
        >
          Continue
        </Button>
      </div>
    </div>
  );
}
