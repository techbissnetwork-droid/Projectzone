import type { Metadata } from "next";
import { AuthLayout } from "@/components/auth/AuthLayout";
import { LoginForm } from "@/components/auth/LoginForm";

export const metadata: Metadata = { title: "Client Login" };

export default function ClientLoginPage() {
  return (
    <AuthLayout>
      <LoginForm role="client" />
    </AuthLayout>
  );
}
