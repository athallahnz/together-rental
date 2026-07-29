import { Head, Link, router } from "@inertiajs/react";
import { CalendarDays, Eye, Plus, Search } from "lucide-react";
import { useState } from "react";
import { PaginationLinks } from "@/components/pagination-links";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import type { AccessBranch, BookingPagination } from "@/types";

type Props = {
  bookings: BookingPagination;
  summary: { total: number; draft: number; confirmed: number; today: number };
  filters: { search: string; status: string; branch_id: number | null };
  branches: AccessBranch[];
  permissions: { create: boolean; update: boolean; cancel: boolean };
};

const money = new Intl.NumberFormat("id-ID", {
  style: "currency",
  currency: "IDR",
  maximumFractionDigits: 0,
});
const dateTime = new Intl.DateTimeFormat("id-ID", {
  dateStyle: "medium",
  timeStyle: "short",
});

export default function BookingIndex({
  bookings,
  summary,
  filters,
  branches,
  permissions,
}: Props) {
  const [search, setSearch] = useState(filters.search);
  const apply = (next: Partial<typeof filters> = {}) => {
    const values = { ...filters, ...next, search };
    router.get(
      "/bookings",
      {
        search: values.search || undefined,
        status:
          values.status && values.status !== "all" ? values.status : undefined,
        branch_id: values.branch_id || undefined,
      },
      { preserveState: true, replace: true },
    );
  };

  return (
    <>
      <Head title="Booking" />
      <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <p className="text-sm font-medium text-primary">
              Operasional rental multi-cabang
            </p>
            <h1 className="mt-1 text-2xl font-semibold">Booking Management</h1>
            <p className="mt-2 text-sm text-muted-foreground">
              Kelola jadwal, pelanggan, item, harga, dan reservasi unit tanpa
              benturan periode.
            </p>
          </div>
          {permissions.create && (
            <Button asChild>
              <Link href="/bookings/create">
                <Plus />
                Booking baru
              </Link>
            </Button>
          )}
        </header>

        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {[
            ["Total booking", summary.total],
            ["Draft", summary.draft],
            ["Terkonfirmasi", summary.confirmed],
            ["Mulai hari ini", summary.today],
          ].map(([label, value]) => (
            <Card key={label}>
              <CardContent className="p-5">
                <p className="text-xs uppercase text-muted-foreground">
                  {label}
                </p>
                <p className="mt-2 text-3xl font-semibold">{value}</p>
              </CardContent>
            </Card>
          ))}
        </section>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <CalendarDays className="size-5" />
              Daftar booking
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-2 md:grid-cols-[1fr_180px_220px]">
              <form
                className="flex gap-2"
                onSubmit={(event) => {
                  event.preventDefault();
                  apply();
                }}
              >
                <Input
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                  placeholder="Nomor booking atau pelanggan"
                />
                <Button variant="outline" type="submit">
                  <Search />
                </Button>
              </form>
              <Select
                value={filters.status || "all"}
                onValueChange={(value) => apply({ status: value })}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Semua status" />
                </SelectTrigger>
                <SelectContent>
                  {[
                    "all",
                    "draft",
                    "confirmed",
                    "converted",
                    "completed",
                    "cancelled",
                    "expired",
                  ].map((status) => (
                    <SelectItem key={status} value={status}>
                      {status === "all" ? "Semua status" : status}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Select
                value={filters.branch_id?.toString() ?? "all"}
                onValueChange={(value) =>
                  apply({ branch_id: value === "all" ? null : Number(value) })
                }
              >
                <SelectTrigger>
                  <SelectValue placeholder="Semua cabang" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Semua cabang</SelectItem>
                  {branches.map((branch) => (
                    <SelectItem key={branch.id} value={String(branch.id)}>
                      {branch.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="overflow-x-auto rounded-lg border">
              <table className="w-full text-sm">
                <thead className="bg-muted/50 text-left">
                  <tr>
                    <th className="p-3">Booking</th>
                    <th className="p-3">Pelanggan</th>
                    <th className="p-3">Periode</th>
                    <th className="p-3">Item</th>
                    <th className="p-3">Total</th>
                    <th className="p-3">Status</th>
                    <th className="p-3" />
                  </tr>
                </thead>
                <tbody>
                  {bookings.data.map((booking) => (
                    <tr key={booking.id} className="border-t">
                      <td className="p-3 font-medium">
                        {booking.booking_number}
                        <div className="text-xs text-muted-foreground">
                          {booking.branch?.name}
                        </div>
                      </td>
                      <td className="p-3">
                        {booking.customer?.name}
                        <div className="text-xs text-muted-foreground">
                          {booking.customer?.customer_number}
                        </div>
                      </td>
                      <td className="p-3">
                        {dateTime.format(new Date(booking.starts_at))}
                        <div className="text-xs text-muted-foreground">
                          s.d. {dateTime.format(new Date(booking.ends_at))}
                        </div>
                      </td>
                      <td className="p-3">
                        {booking.items_count} baris /{" "}
                        {booking.reservations_count} unit
                      </td>
                      <td className="p-3">
                        {money.format(Number(booking.total_amount))}
                      </td>
                      <td className="p-3">
                        <Badge
                          variant={
                            booking.status === "confirmed"
                              ? "default"
                              : "secondary"
                          }
                        >
                          {booking.status}
                        </Badge>
                      </td>
                      <td className="p-3 text-right">
                        <Button asChild size="icon" variant="ghost">
                          <Link href={`/bookings/${booking.id}`}>
                            <Eye />
                          </Link>
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              {bookings.data.length === 0 && (
                <p className="p-8 text-center text-muted-foreground">
                  Belum ada booking sesuai filter.
                </p>
              )}
            </div>
            <PaginationLinks
              links={bookings.links}
              from={bookings.from}
              to={bookings.to}
              total={bookings.total}
            />
          </CardContent>
        </Card>
      </div>
    </>
  );
}
