import { Link } from "@inertiajs/react";
import { ChartNoAxesCombined } from "lucide-react";
import type { LucideIcon } from "lucide-react";
import type { ReactNode } from "react";
import { Card, CardContent } from "@/components/ui/card";
import { cn } from "@/lib/utils";

type MetricTone =
  "neutral" | "primary" | "info" | "success" | "warning" | "danger";

const toneStyles: Record<
  MetricTone,
  { card: string; icon: string; value: string }
> = {
  neutral: {
    card: "",
    icon: "bg-muted text-muted-foreground",
    value: "text-foreground",
  },
  primary: {
    card: "border-primary/20",
    icon: "bg-primary/10 text-primary",
    value: "text-primary",
  },
  info: {
    card: "border-sky-500/20",
    icon: "bg-sky-500/10 text-sky-600 dark:text-sky-400",
    value: "text-sky-700 dark:text-sky-300",
  },
  success: {
    card: "border-emerald-500/20",
    icon: "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400",
    value: "text-emerald-700 dark:text-emerald-300",
  },
  warning: {
    card: "border-amber-500/25",
    icon: "bg-amber-500/10 text-amber-600 dark:text-amber-400",
    value: "text-amber-700 dark:text-amber-300",
  },
  danger: {
    card: "border-destructive/30",
    icon: "bg-destructive/10 text-destructive",
    value: "text-destructive",
  },
};

type MetricCardProps = {
  label: string;
  value: ReactNode;
  detail?: ReactNode;
  footer?: ReactNode;
  icon?: LucideIcon;
  tone?: MetricTone;
  href?: string;
  compact?: boolean;
  className?: string;
  iconClassName?: string;
};

export function MetricCard({
  label,
  value,
  detail,
  footer,
  icon: Icon = ChartNoAxesCombined,
  tone = "neutral",
  href,
  compact = false,
  className,
  iconClassName,
}: MetricCardProps) {
  const styles = toneStyles[tone];
  const content = (
    <Card
      data-slot="metric-card"
      className={cn(
        "group h-full gap-0 overflow-hidden py-0 shadow-xs transition-[border-color,box-shadow,transform] duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/25 hover:shadow-md",
        styles.card,
        href &&
          "focus-within:border-primary/35 focus-within:ring-2 focus-within:ring-primary/15",
        className,
      )}
    >
      <CardContent className="flex h-full flex-col justify-between p-5">
        <div className="flex items-start justify-between gap-4">
          <div className="min-w-0">
            <p className="truncate text-xs font-semibold tracking-[0.08em] text-muted-foreground uppercase">
              {label}
            </p>
            <div
              className={cn(
                "mt-2 font-semibold tracking-tight tabular-nums",
                compact ? "text-lg" : "text-2xl",
                styles.value,
              )}
            >
              {value}
            </div>
          </div>
          <span
            className={cn(
              "flex size-10 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset ring-current/5 transition-transform duration-200 group-hover:scale-105",
              styles.icon,
              iconClassName,
            )}
          >
            <Icon className="size-5" aria-hidden="true" />
          </span>
        </div>
        {detail && (
          <p className="mt-3 line-clamp-2 text-xs leading-5 text-muted-foreground">
            {detail}
          </p>
        )}
        {footer && <div className="mt-3 border-t pt-3">{footer}</div>}
      </CardContent>
    </Card>
  );

  if (!href) {
    return content;
  }

  return (
    <Link href={href} className="block h-full rounded-xl outline-none">
      {content}
    </Link>
  );
}

export function MetricGrid({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  return (
    <section
      data-slot="metric-grid"
      className={cn(
        "grid grid-cols-[repeat(auto-fit,minmax(min(100%,13rem),1fr))] gap-3",
        className,
      )}
    >
      {children}
    </section>
  );
}

export type { MetricCardProps, MetricTone };
