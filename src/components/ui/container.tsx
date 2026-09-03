import { cn } from "@/lib/utils";

export function Container({
  className,
  wide,
  children,
}: {
  className?: string;
  wide?: boolean;
  children: React.ReactNode;
}) {
  return (
    <div
      className={cn(
        "mx-auto w-full px-6 sm:px-8 lg:px-10",
        wide ? "max-w-[1400px]" : "max-w-[1180px]",
        className,
      )}
    >
      {children}
    </div>
  );
}
