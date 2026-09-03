import { Container } from "@/components/ui/Container";
import { Stat } from "@/components/ui/Stat";

const stats = [
  { value: "400+", label: "Companies transformed" },
  { value: "$2.1B+", label: "Client revenue enabled" },
  { value: "99.99%", label: "Average platform uptime" },
  { value: "34K+", label: "Marketplace deployments" },
];

export function Stats() {
  return (
    <div className="border-y border-(--color-border)">
      <Container size="wide">
        <div className="grid grid-cols-2 gap-8 py-14 sm:grid-cols-4 sm:py-16">
          {stats.map((stat, i) => (
            <Stat key={stat.label} value={stat.value} label={stat.label} delay={i * 0.06} className="items-center text-center sm:items-start sm:text-left" />
          ))}
        </div>
      </Container>
    </div>
  );
}
