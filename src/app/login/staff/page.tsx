import type { Metadata } from "next";
import { AuthLayout } from "@/components/auth/AuthLayout";
import { LoginForm } from "@/components/auth/LoginForm";

export const metadata: Metadata = { title: "Staff Login" };

export default function StaffLoginPage() {
  return (
    <AuthLayout>
      <LoginForm role="staff" />
    </AuthLayout>
  );
}
