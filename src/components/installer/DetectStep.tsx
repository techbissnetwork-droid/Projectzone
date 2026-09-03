"use client";

import * as React from "react";
import { ArrowRight, Globe, Search } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Input, Label } from "@/components/ui/Field";
import { ScanList, type ScanItem } from "@/components/installer/ScanList";

const envChecks: ScanItem[] = [
  { label: "Resolving domain & DNS records", detail: "Resolved" },
  { label: "Checking SSL/TLS certificate", detail: "Valid — TLS 1.3" },
  { label: "Detecting server environment", detail: "Nginx · Node 22 · Linux" },
  { label: "Verifying available disk space", detail: "48.2 GB free" },
  { label: "Checking write permissions", detail: "Writable" },
];

export function DetectStep({
  url,
  setUrl,
  detected,
  onDetected,
  onContinue,
}: {
  url: string;
  setUrl: (v: string) => void;
  detected: boolean;
  onDetected: () => void;
  onContinue: () => void;
}) {
  const [scanning, setScanning] = React.useState(false);
  const [error, setError] = React.useState("");

  function handleDetect() {
    if (!url.trim()) {
      setError("Enter a domain or URL to continue");
      return;
    }
    setError("");
    setScanning(true);
  }

  return (
    <div className="rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6 sm:p-8">
      <h2 className="text-lg font-medium text-(--color-ink)">Where should we deploy?</h2>
      <p className="mt-1 text-sm text-(--color-ink-muted)">
        Enter your domain and we&apos;ll automatically detect your environment.
      </p>

      <div className="mt-6">
        <Label htmlFor="site-url" required>
          Site URL
        </Label>
        <div className="flex flex-col gap-3 sm:flex-row">
          <div className="relative flex-1">
            <Globe className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-(--color-ink-faint)" />
            <Input
              id="site-url"
              className="pl-10"
              value={url}
              onChange={(e) => setUrl(e.target.value)}
              placeholder="www.yourcompany.com"
              disabled={scanning}
              error={error}
            />
          </div>
          <Button
            variant="secondary"
            size="lg"
            icon={<Search className="h-4 w-4" />}
            iconPosition="left"
            disabled={scanning || detected}
            onClick={handleDetect}
          >
            {detected ? "Detected" : scanning ? "Scanning..." : "Auto-Detect"}
          </Button>
        </div>
      </div>

      {scanning && (
        <div className="mt-6">
          <ScanList
            items={envChecks}
            onComplete={() => {
              setScanning(false);
              onDetected();
            }}
          />
        </div>
      )}

      {detected && (
        <div className="mt-6 flex justify-end">
          <Button variant="secondary" size="lg" icon={<ArrowRight className="h-4 w-4" />} onClick={onContinue}>
            Continue
          </Button>
        </div>
      )}
    </div>
  );
}
