"use client";

import * as React from "react";
import { useRouter } from "next/navigation";
import { motion } from "framer-motion";
import { Eye, EyeOff, Loader2, Lock, Mail, ShieldCheck } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Input, Label, Checkbox } from "@/components/ui/Field";
import { Logo } from "@/components/ui/Logo";
import { useAuth, type Role } from "@/lib/auth-context";
import { sleep } from "@/lib/utils";

const copy: Record<Role, { title: string; description: string; demoEmail: string }> = {
  admin: {
    title: "Admin sign in",
    description: "Manage the marketplace, staff and platform-wide settings.",
    demoEmail: "admin@techbiss.com",
  },
  client: {
    title: "Client sign in",
    description: "Access your projects, purchases and support in one place.",
    demoEmail: "client@techbiss.com",
  },
  staff: {
    title: "Staff sign in",
    description: "View assigned engagements, tickets and internal tools.",
    demoEmail: "staff@techbiss.com",
  },
};

export function LoginForm({ role }: { role: Role }) {
  const router = useRouter();
  const { login } = useAuth();
  const meta = copy[role];

  const [email, setEmail] = React.useState("");
  const [password, setPassword] = React.useState("");
  const [showPassword, setShowPassword] = React.useState(false);
  const [remember, setRemember] = React.useState(true);
  const [error, setError] = React.useState("");
  const [loading, setLoading] = React.useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!email.trim() || !password.trim()) {
      setError("Enter both email and password to continue.");
      return;
    }
    setError("");
    setLoading(true);
    await sleep(900);
    login(role, email);
    router.push(`/dashboard/${role}`);
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.5, ease: [0.16, 1, 0.3, 1] }}
      className="w-full max-w-md"
    >
      <div className="mb-8 flex flex-col items-center text-center">
        <Logo />
        <h1 className="mt-6 text-2xl font-medium tracking-tight text-(--color-ink)">{meta.title}</h1>
        <p className="mt-2 text-sm text-(--color-ink-muted)">{meta.description}</p>
      </div>

      <form onSubmit={handleSubmit} className="rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6 sm:p-8">
        <div className="flex flex-col gap-4">
          <div>
            <Label htmlFor="email" required>
              Email
            </Label>
            <div className="relative">
              <Mail className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-(--color-ink-faint)" />
              <Input
                id="email"
                type="email"
                className="pl-10"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder={meta.demoEmail}
              />
            </div>
          </div>
          <div>
            <div className="flex items-center justify-between">
              <Label htmlFor="password" required>
                Password
              </Label>
              <button type="button" className="focus-ring text-xs text-(--color-ink-faint) hover:text-(--color-ink-muted)">
                Forgot password?
              </button>
            </div>
            <div className="relative">
              <Lock className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-(--color-ink-faint)" />
              <Input
                id="password"
                type={showPassword ? "text" : "password"}
                className="pl-10 pr-10"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
              />
              <button
                type="button"
                onClick={() => setShowPassword((v) => !v)}
                className="focus-ring absolute right-3.5 top-1/2 -translate-y-1/2 text-(--color-ink-faint) hover:text-(--color-ink-muted)"
                aria-label={showPassword ? "Hide password" : "Show password"}
              >
                {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
              </button>
            </div>
          </div>

          {error && <p className="text-xs text-red-400">{error}</p>}

          <Checkbox
            label="Remember me for 30 days"
            checked={remember}
            onChange={(e) => setRemember(e.target.checked)}
          />
        </div>

        <Button type="submit" variant="secondary" size="lg" className="mt-6 w-full" disabled={loading}>
          {loading ? (
            <span className="flex items-center gap-2">
              <Loader2 className="h-4 w-4 animate-spin" /> Signing in...
            </span>
          ) : (
            "Sign in"
          )}
        </Button>

        <p className="mt-4 flex items-center justify-center gap-1.5 text-xs text-(--color-ink-faint)">
          <ShieldCheck className="h-3.5 w-3.5" /> Demo environment — any email &amp; password will work
        </p>
      </form>

      <p className="mt-6 text-center text-xs text-(--color-ink-faint)">
        {role !== "client" && (
          <>
            Are you a client?{" "}
            <a href="/login/client" className="text-(--color-ink-muted) hover:text-(--color-ink)">
              Client login
            </a>
            {" · "}
          </>
        )}
        {role !== "staff" && (
          <>
            Staff?{" "}
            <a href="/login/staff" className="text-(--color-ink-muted) hover:text-(--color-ink)">
              Staff login
            </a>
            {" · "}
          </>
        )}
        {role !== "admin" && (
          <>
            Admin?{" "}
            <a href="/login/admin" className="text-(--color-ink-muted) hover:text-(--color-ink)">
              Admin login
            </a>
          </>
        )}
      </p>
    </motion.div>
  );
}
