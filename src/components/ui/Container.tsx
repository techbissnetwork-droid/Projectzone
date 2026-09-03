import { cn } from "@/lib/utils";

export function Container({
  className,
  children,
  as: Tag = "div",
  size = "default",
}: {
  className?: string;
  children: React.ReactNode;
  as?: React.ElementType;
  size?: "default" | "narrow" | "wide";
}) {
  return (
    <Tag
      className={cn(
        "mx-auto w-full px-5 sm:px-8 lg:px-10",
        size === "default" && "max-w-7xl",
        size === "narrow" && "max-w-3xl",
        size === "wide" && "max-w-[100rem]",
        className,
      )}
    >
      {children}
    </Tag>
  );
}
