import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    ExternalLink,
    Globe2,
    Instagram,
    MapPin,
    MessageCircle,
    Store,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Branch = {
    id: number;
    code: string;
    name: string;
    city: string | null;
    phone: string | null;
    address: string | null;
    is_active: boolean;
};

type PublicProfileForm = {
    public_catalog_enabled: boolean;
    public_whatsapp: string;
    public_short_address: string;
    public_maps_url: string;
    public_instagram: string;
    public_opening_hours: string;
    public_logo_path: string;
    public_hero_title: string;
    public_hero_description: string;
};

type Props = {
    branch: Branch;
    profile: PublicProfileForm;
    previewUrl: string;
};

export default function BranchPublicProfile({
    branch,
    profile,
    previewUrl,
}: Props) {
    const form = useForm<PublicProfileForm>(profile);
    const enabled = form.data.public_catalog_enabled;
    const whatsappPreview = form.data.public_whatsapp
        ? `https://wa.me/${form.data.public_whatsapp}`
        : null;
    const instagramPreview = form.data.public_instagram
        ? `https://instagram.com/${form.data.public_instagram.replace(/^@/, '')}`
        : null;

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/branches/${branch.id}/public-profile`, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={`Katalog Publik ${branch.code}`} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="mb-2 -ml-3"
                        >
                            <Link href="/branches">
                                <ArrowLeft />
                                Kembali ke cabang
                            </Link>
                        </Button>
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="text-sm font-medium text-primary">
                                Modul 8 · Katalog Publik
                            </p>
                            <Badge
                                variant={
                                    branch.is_active ? 'outline' : 'secondary'
                                }
                            >
                                {branch.is_active
                                    ? 'Cabang operasional'
                                    : 'Cabang nonaktif'}
                            </Badge>
                        </div>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Profil publik {branch.name}
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Atur identitas, kontak, lokasi, dan status publik
                            cabang. Cabang hanya muncul pada landing page ketika
                            berstatus operasional dan katalog publik diaktifkan.
                        </p>
                    </div>

                    {enabled && branch.is_active && (
                        <Button asChild variant="outline">
                            <a
                                href={previewUrl}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink />
                                Preview halaman publik
                            </a>
                        </Button>
                    )}
                </header>

                {!branch.is_active && (
                    <Alert variant="destructive">
                        <Building2 />
                        <AlertTitle>Cabang sedang nonaktif</AlertTitle>
                        <AlertDescription>
                            Aktifkan kembali cabang melalui halaman Manajemen
                            Cabang sebelum menayangkannya pada katalog publik.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                    <form onSubmit={submit} className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Status publik</CardTitle>
                                <CardDescription>
                                    Kontrol apakah cabang ditampilkan sebagai
                                    pilihan aktif pada landing page dan katalog.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-start gap-3 rounded-xl border p-4">
                                    <Checkbox
                                        id="public_catalog_enabled"
                                        checked={enabled}
                                        disabled={
                                            !branch.is_active || form.processing
                                        }
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'public_catalog_enabled',
                                                checked === true,
                                            )
                                        }
                                    />
                                    <div className="min-w-0">
                                        <Label htmlFor="public_catalog_enabled">
                                            Tampilkan cabang pada katalog publik
                                        </Label>
                                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                            Cabang aktif yang dicentang akan
                                            otomatis muncul pada pemilih cabang.
                                            Cabang baru tetap tersembunyi sampai
                                            profil publiknya lengkap.
                                        </p>
                                        <InputError
                                            message={
                                                form.errors
                                                    .public_catalog_enabled
                                            }
                                            className="mt-2"
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Kontak dan lokasi</CardTitle>
                                <CardDescription>
                                    Informasi ini ditampilkan pada CTA, navbar,
                                    footer, dan kartu lokasi cabang.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-5 md:grid-cols-2">
                                <FormField
                                    label="Nomor WhatsApp"
                                    name="public_whatsapp"
                                    error={form.errors.public_whatsapp}
                                    description="Gunakan kode negara tanpa tanda +, contoh 6285784771927."
                                >
                                    <Input
                                        id="public_whatsapp"
                                        inputMode="numeric"
                                        value={form.data.public_whatsapp}
                                        onChange={(event) =>
                                            form.setData(
                                                'public_whatsapp',
                                                event.target.value.replace(
                                                    /\D/g,
                                                    '',
                                                ),
                                            )
                                        }
                                        placeholder="6285784771927"
                                    />
                                </FormField>

                                <FormField
                                    label="Instagram"
                                    name="public_instagram"
                                    error={form.errors.public_instagram}
                                    description="Masukkan username tanpa tanda @."
                                >
                                    <Input
                                        id="public_instagram"
                                        value={form.data.public_instagram}
                                        onChange={(event) =>
                                            form.setData(
                                                'public_instagram',
                                                event.target.value.replace(
                                                    /^@/,
                                                    '',
                                                ),
                                            )
                                        }
                                        placeholder="together_kamera"
                                    />
                                </FormField>

                                <FormField
                                    label="Jam operasional"
                                    name="public_opening_hours"
                                    error={form.errors.public_opening_hours}
                                >
                                    <Input
                                        id="public_opening_hours"
                                        value={form.data.public_opening_hours}
                                        onChange={(event) =>
                                            form.setData(
                                                'public_opening_hours',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="09.00–21.00 WIB"
                                    />
                                </FormField>

                                <FormField
                                    label="Google Maps URL"
                                    name="public_maps_url"
                                    error={form.errors.public_maps_url}
                                >
                                    <Input
                                        id="public_maps_url"
                                        type="url"
                                        value={form.data.public_maps_url}
                                        onChange={(event) =>
                                            form.setData(
                                                'public_maps_url',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="https://maps.google.com/..."
                                    />
                                </FormField>

                                <div className="md:col-span-2">
                                    <FormField
                                        label="Alamat singkat publik"
                                        name="public_short_address"
                                        error={form.errors.public_short_address}
                                    >
                                        <textarea
                                            id="public_short_address"
                                            value={
                                                form.data.public_short_address
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'public_short_address',
                                                    event.target.value,
                                                )
                                            }
                                            rows={3}
                                            className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            placeholder="Alamat yang mudah dibaca pelanggan"
                                        />
                                    </FormField>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Identitas landing page</CardTitle>
                                <CardDescription>
                                    Konten singkat yang berubah mengikuti cabang
                                    terpilih.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-5">
                                <FormField
                                    label="Path logo publik"
                                    name="public_logo_path"
                                    error={form.errors.public_logo_path}
                                    description="Dapat memakai file public, URL penuh, atau path storage publik."
                                >
                                    <Input
                                        id="public_logo_path"
                                        value={form.data.public_logo_path}
                                        onChange={(event) =>
                                            form.setData(
                                                'public_logo_path',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="/primary-logos.png"
                                    />
                                </FormField>

                                <FormField
                                    label="Judul hero"
                                    name="public_hero_title"
                                    error={form.errors.public_hero_title}
                                >
                                    <Input
                                        id="public_hero_title"
                                        value={form.data.public_hero_title}
                                        onChange={(event) =>
                                            form.setData(
                                                'public_hero_title',
                                                event.target.value,
                                            )
                                        }
                                        maxLength={160}
                                    />
                                </FormField>

                                <FormField
                                    label="Deskripsi hero"
                                    name="public_hero_description"
                                    error={form.errors.public_hero_description}
                                >
                                    <textarea
                                        id="public_hero_description"
                                        value={
                                            form.data.public_hero_description
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                'public_hero_description',
                                                event.target.value,
                                            )
                                        }
                                        rows={4}
                                        maxLength={500}
                                        className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        placeholder="Deskripsi singkat layanan cabang ini."
                                    />
                                </FormField>
                            </CardContent>
                        </Card>

                        <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <Button asChild type="button" variant="outline">
                                <Link href="/branches">Batal</Link>
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? 'Menyimpan…'
                                    : 'Simpan profil publik'}
                            </Button>
                        </div>
                    </form>

                    <aside className="space-y-4 xl:sticky xl:top-6 xl:self-start">
                        <Card className="overflow-hidden">
                            <div className="border-b bg-muted/30 p-4">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Preview ringkas
                                        </p>
                                        <p className="mt-1 font-semibold">
                                            {branch.code}
                                        </p>
                                    </div>
                                    <Badge
                                        variant={
                                            enabled ? 'default' : 'secondary'
                                        }
                                    >
                                        {enabled ? 'Publik' : 'Tersembunyi'}
                                    </Badge>
                                </div>
                            </div>
                            <CardContent className="space-y-5 p-5">
                                <div className="flex items-start gap-4">
                                    <div className="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl border bg-background">
                                        {form.data.public_logo_path ? (
                                            <img
                                                src={publicMediaUrl(
                                                    form.data.public_logo_path,
                                                )}
                                                alt="Preview logo"
                                                className="size-full object-contain p-2"
                                            />
                                        ) : (
                                            <Store className="size-6 text-muted-foreground" />
                                        )}
                                    </div>
                                    <div className="min-w-0">
                                        <p className="font-semibold">
                                            {branch.name}
                                        </p>
                                        <p className="mt-1 text-sm leading-5 text-muted-foreground">
                                            {form.data.public_hero_title ||
                                                'Judul hero belum diisi'}
                                        </p>
                                    </div>
                                </div>

                                <p className="text-sm leading-6 text-muted-foreground">
                                    {form.data.public_hero_description ||
                                        'Deskripsi hero belum diisi.'}
                                </p>

                                <div className="space-y-3 border-t pt-4 text-sm">
                                    <PreviewRow icon={<MapPin />}>
                                        {form.data.public_short_address ||
                                            'Alamat belum diisi'}
                                    </PreviewRow>
                                    <PreviewRow icon={<Globe2 />}>
                                        {form.data.public_opening_hours ||
                                            'Jam operasional belum diisi'}
                                    </PreviewRow>
                                    <PreviewRow icon={<MessageCircle />}>
                                        {form.data.public_whatsapp ||
                                            'WhatsApp belum diisi'}
                                    </PreviewRow>
                                    <PreviewRow icon={<Instagram />}>
                                        {form.data.public_instagram
                                            ? `@${form.data.public_instagram.replace(/^@/, '')}`
                                            : 'Instagram belum diisi'}
                                    </PreviewRow>
                                </div>

                                <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                                    {whatsappPreview && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <a
                                                href={whatsappPreview}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <MessageCircle />
                                                Uji WhatsApp
                                            </a>
                                        </Button>
                                    )}
                                    {instagramPreview && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <a
                                                href={instagramPreview}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <Instagram />
                                                Uji Instagram
                                            </a>
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        <Alert>
                            <Globe2 />
                            <AlertTitle>Otomatis multi-cabang</AlertTitle>
                            <AlertDescription>
                                Setelah disimpan dan diaktifkan, cabang ini
                                otomatis masuk ke pilihan cabang publik tanpa
                                perubahan kode React.
                            </AlertDescription>
                        </Alert>
                    </aside>
                </div>
            </div>
        </>
    );
}

function FormField({
    label,
    name,
    error,
    description,
    children,
}: {
    label: string;
    name: string;
    error?: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            {children}
            {description && (
                <p className="text-xs leading-5 text-muted-foreground">
                    {description}
                </p>
            )}
            <InputError message={error} />
        </div>
    );
}

function PreviewRow({
    icon,
    children,
}: {
    icon: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="flex items-start gap-2 text-muted-foreground [&_svg]:mt-0.5 [&_svg]:size-4 [&_svg]:shrink-0">
            {icon}
            <span className="min-w-0 break-words">{children}</span>
        </div>
    );
}

function publicMediaUrl(path: string): string {
    if (/^https?:\/\//i.test(path) || path.startsWith('/')) {
        return path;
    }

    return `/storage/${path.replace(/^\/+/, '')}`;
}
