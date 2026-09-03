import { DashboardSidebar } from "@/components/dashboard/sidebar";
import { DashboardTopbar, DashboardAccountBar } from "@/components/dashboard/topbar";

export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen bg-ink-950">
      <DashboardTopbar />
      <div className="mx-auto flex max-w-[1600px]">
        <aside className="sticky top-0 hidden h-screen w-[260px] shrink-0 border-r border-line-dark p-5 lg:block">
          <DashboardSidebar />
        </aside>
        <div className="min-w-0 flex-1">
          <DashboardAccountBar />
          <main className="px-5 py-8 sm:px-8 sm:py-10">{children}</main>
        </div>
      </div>
    </div>
  );
}
