import { cn } from "@/lib/utils";

export function Section({
  className,
  children,
  theme = "dark",
  id,
  size = "default",
}: {
  className?: string;
  children: React.ReactNode;
  theme?: "dark" | "light";
  id?: string;
  size?: "default" | "tight" | "loose";
}) {
  return (
    <section
      id={id}
      data-theme={theme === "light" ? "light" : undefined}
      className={cn(
        "relative",
        size === "default" && "py-20 sm:py-24 lg:py-28",
        size === "tight" && "py-12 sm:py-16",
        size === "loose" && "py-28 sm:py-32 lg:py-36",
        className,
      )}
    >
      {children}
    </section>
  );
}
