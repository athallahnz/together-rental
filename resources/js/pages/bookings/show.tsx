import { Head, Link, router } from "@inertiajs/react";
import { ArrowLeft, CheckCircle2, Pencil, XCircle } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import type { Booking } from "@/types";

type Props = {
  booking: Booking;
  permissions: { create: boolean; update: boolean; cancel: boolean };
};
const money = new Intl.NumberFormat("id-ID", {
  style: "currency",
  currency: "IDR",
  maximumFractionDigits: 0,
});
const dateTime = new Intl.DateTimeFormat("id-ID", {
  dateStyle: "full",
  timeStyle: "short",
});

export default function BookingShow({ booking, permissions }: Props) {
  const [reason, setReason] = useState("");
  const [cancelling, setCancelling] = useState(false);
  const active = ["draft", "confirmed"].includes(booking.status);

  return (
    <>
      <Head title={booking.booking_number} />
      <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <Button variant="ghost" size="sm" asChild>
              <Link href="/bookings">
                <ArrowLeft />
                Daftar booking
              </Link>
            </Button>
            <div className="mt-3 flex items-center gap-3">
              <h1 className="text-2xl font-semibold">
                {booking.booking_number}
              </h1>
              <Badge>{booking.status}</Badge>
            </div>
            <p className="mt-2 text-sm text-muted-foreground">
              {booking.branch?.name} · {booking.customer?.name}
            </p>
          </div>
          <div className="flex gap-2">
            {permissions.update && booking.status === "draft" && (
              <Button variant="outline" asChild>
                <Link href={`/bookings/${booking.id}/edit`}>
                  <Pencil />
                  Edit
                </Link>
              </Button>
            )}
            {permissions.update && booking.status === "draft" && (
              <Button
                onClick={() => router.post(`/bookings/${booking.id}/confirm`)}
              >
                <CheckCircle2 />
                Konfirmasi
              </Button>
            )}
          </div>
        </header>
        <section className="grid gap-4 lg:grid-cols-3">
          <Card>
            <CardHeader>
              <CardTitle>Jadwal</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <p>
                <b>Mulai:</b> {dateTime.format(new Date(booking.starts_at))}
              </p>
              <p>
                <b>Selesai:</b> {dateTime.format(new Date(booking.ends_at))}
              </p>
              <p>
                <b>Sumber:</b> {booking.source}
              </p>
              <p>
                <b>Rate:</b> {booking.rate_plan?.name}
              </p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle>Pelanggan</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <p className="font-medium">{booking.customer?.name}</p>
              <p>{booking.customer?.customer_number}</p>
              <p>{booking.customer?.phone || "-"}</p>
              <p>Risk: {booking.customer?.risk_level || "-"}</p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle>Nilai booking</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <p className="flex justify-between">
                <span>Subtotal</span>
                <b>{money.format(Number(booking.subtotal))}</b>
              </p>
              <p className="flex justify-between">
                <span>Deposit wajib</span>
                <b>{money.format(Number(booking.deposit_required))}</b>
              </p>
              <p className="flex justify-between border-t pt-2 text-base">
                <span>Total</span>
                <b>{money.format(Number(booking.total_amount))}</b>
              </p>
            </CardContent>
          </Card>
        </section>
        <Card>
          <CardHeader>
            <CardTitle>Item dan unit terreservasi</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {booking.items.map((item) => (
              <div key={item.id} className="rounded-lg border p-4">
                <div className="flex justify-between">
                  <div>
                    <p className="font-medium">{item.description}</p>
                    <p className="text-sm text-muted-foreground">
                      {item.quantity} × {money.format(Number(item.unit_rate))}
                    </p>
                  </div>
                  <b>{money.format(Number(item.total_amount))}</b>
                </div>
                <div className="mt-3 flex flex-wrap gap-2">
                  {item.reservations?.map((reservation) => (
                    <Badge key={reservation.id} variant="secondary">
                      {reservation.asset.asset_code} ·{" "}
                      {reservation.asset.condition}
                    </Badge>
                  ))}
                </div>
              </div>
            ))}
          </CardContent>
        </Card>
        <div className="grid gap-4 lg:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>Riwayat status</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              {booking.status_histories?.map((history) => (
                <div key={history.id} className="border-l-2 pl-3 text-sm">
                  <p className="font-medium">
                    {history.from_status ?? "awal"} → {history.to_status}
                  </p>
                  <p className="text-muted-foreground">
                    {history.reason} · {history.changer?.name ?? "Sistem"}
                  </p>
                </div>
              ))}
            </CardContent>
          </Card>
          {permissions.cancel && active && (
            <Card>
              <CardHeader>
                <CardTitle>Batalkan booking</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <textarea
                  className="min-h-24 w-full rounded-md border bg-transparent p-3 text-sm"
                  placeholder="Alasan pembatalan minimal 5 karakter"
                  value={reason}
                  onChange={(event) => setReason(event.target.value)}
                />
                <Button
                  variant="destructive"
                  disabled={cancelling || reason.trim().length < 5}
                  onClick={() =>
                    router.post(
                      `/bookings/${booking.id}/cancel`,
                      { reason },
                      {
                        onStart: () => setCancelling(true),
                        onFinish: () => setCancelling(false),
                      },
                    )
                  }
                >
                  <XCircle />
                  Batalkan dan lepas reservasi
                </Button>
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </>
  );
}
