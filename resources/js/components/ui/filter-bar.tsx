import { SlidersHorizontal } from "lucide-react";
import type { ReactNode } from "react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";

type FilterBarProps = {
  children: ReactNode;
  title?: string;
  description?: string;
  context?: ReactNode;
  footer?: ReactNode;
  className?: string;
  contentClassName?: string;
};

export function FilterBar({
  children,
  title = "Filter & pencarian",
  description = "Persempit data untuk menemukan pekerjaan yang perlu ditindak.",
  context,
  footer,
  className,
  contentClassName,
}: FilterBarProps) {
  return (
    <Card
      data-slot="filter-bar"
      className={cn(
        "gap-0 overflow-hidden border-primary/15 py-0 shadow-xs",
        className,
      )}
    >
      <CardHeader className="border-b bg-muted/25 px-5 py-4">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex min-w-0 items-start gap-3">
            <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
              <SlidersHorizontal className="size-4" />
            </span>
            <div className="min-w-0">
              <CardTitle className="text-sm">{title}</CardTitle>
              <CardDescription className="mt-1 leading-5">
                {description}
              </CardDescription>
            </div>
          </div>
          {context && <div className="shrink-0">{context}</div>}
        </div>
      </CardHeader>
      <CardContent className="p-5">
        <div
          data-slot="filter-grid"
          className={cn(
            "grid grid-cols-[repeat(auto-fit,minmax(min(100%,12rem),1fr))] items-end gap-3",
            contentClassName,
          )}
        >
          {children}
        </div>
        {footer && <div className="mt-4 border-t pt-4">{footer}</div>}
      </CardContent>
    </Card>
  );
}

export function FilterField({
  label,
  children,
  className,
  htmlFor,
}: {
  label: string;
  children: ReactNode;
  className?: string;
  htmlFor?: string;
}) {
  return (
    <div
      data-slot="filter-field"
      className={cn("min-w-0 space-y-1.5", className)}
    >
      <Label
        htmlFor={htmlFor}
        className="text-xs font-medium text-muted-foreground"
      >
        {label}
      </Label>
      {children}
    </div>
  );
}

export function FilterActions({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  return (
    <div
      data-slot="filter-actions"
      className={cn("flex flex-wrap items-center gap-2", className)}
    >
      {children}
    </div>
  );
}
