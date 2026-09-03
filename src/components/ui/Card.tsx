import { cn } from "@/lib/utils";

export function Card({
  children,
  className,
  hover = false,
  as: Tag = "div",
}: {
  children: React.ReactNode;
  className?: string;
  hover?: boolean;
  as?: React.ElementType;
}) {
  return (
    <Tag
      className={cn(
        "rounded-(--radius-lg) border border-(--color-border) bg-(--color-surface) p-6 sm:p-7",
        hover &&
          "transition-all duration-300 ease-out hover:-translate-y-1 hover:border-(--color-border-strong) hover:bg-(--color-surface-raised) hover:shadow-lg",
        className,
      )}
    >
      {children}
    </Tag>
  );
}
