import * as React from "react";
import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";

type RupiahInputProps = Omit<
  React.ComponentProps<"input">,
  "type" | "value" | "onChange"
> & {
  value: number | string;
  onValueChange: (value: number) => void;
};

const onlyDigits = (value: string) => value.replace(/\D/g, "");

export function RupiahInput({
  value,
  onValueChange,
  className,
  ...props
}: RupiahInputProps) {
  const numericValue = Number(value) || 0;
  const formattedValue = new Intl.NumberFormat("id-ID").format(numericValue);

  return (
    <div className="relative">
      <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-muted-foreground">
        Rp
      </span>
      <Input
        {...props}
        type="text"
        inputMode="numeric"
        autoComplete="off"
        value={formattedValue}
        className={cn("pl-10 tabular-nums", className)}
        onChange={(event) => {
          const digits = onlyDigits(event.target.value);

          onValueChange(digits === "" ? 0 : Number(digits));
        }}
      />
    </div>
  );
}
