export function DashboardPageHeader({
  title,
  subtitle,
  action,
}: {
  title: string;
  subtitle?: string;
  action?: React.ReactNode;
}) {
  return (
    <div className="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
      <div>
        <h1 className="text-[24px] font-medium tracking-tight text-paper-50 sm:text-[28px]">
          {title}
        </h1>
        {subtitle && <p className="mt-2 max-w-lg text-[14px] text-paper-50/50">{subtitle}</p>}
      </div>
      {action}
    </div>
  );
}
