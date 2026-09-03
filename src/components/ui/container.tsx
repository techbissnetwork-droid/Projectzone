import { cn } from "@/lib/utils";

export function Container({
  className,
  children,
  wide = false,
}: {
  className?: string;
  children: React.ReactNode;
  wide?: boolean;
}) {
  return (
    <div
      className={cn(
        "mx-auto w-full px-5 sm:px-8 lg:px-10",
        wide ? "max-w-[1440px]" : "max-w-[1180px]",
        className
      )}
    >
      {children}
    </div>
  );
}
