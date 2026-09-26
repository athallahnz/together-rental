import { Head, useForm } from '@inertiajs/react';
import { Globe2 } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAppLocale } from '@/lib/i18n';
import type { AppLocale } from '@/lib/i18n';

export default function Language() {
    const { locale, t } = useAppLocale();
    const form = useForm<{ locale: AppLocale }>({ locale });

    const save = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.patch('/settings/language', { preserveScroll: true });
    };

    return (
        <>
            <Head title={t('Pengaturan bahasa')} />

            <div className="space-y-6">
                <Heading
                    title={t('Bahasa & Regional')}
                    description={t(
                        'Bahasa yang dipilih hanya berlaku untuk akun Anda, bukan seluruh cabang atau perusahaan.',
                    )}
                />

                <Card>
                    <CardHeader className="border-b bg-muted/20">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Globe2 className="size-5" />
                            {t('Pilih bahasa aplikasi')}
                        </CardTitle>
                        <CardDescription>
                            {t(
                                'Bahasa yang dipilih hanya berlaku untuk akun Anda, bukan seluruh cabang atau perusahaan.',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5 pt-6">
                        <form onSubmit={save} className="space-y-4">
                            <div className="max-w-sm space-y-2">
                                <Label htmlFor="app-locale">
                                    {t('Bahasa aplikasi')}
                                </Label>
                                <Select
                                    value={form.data.locale}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'locale',
                                            value as AppLocale,
                                        )
                                    }
                                >
                                    <SelectTrigger id="app-locale">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="id">
                                            🇮🇩 Bahasa Indonesia
                                        </SelectItem>
                                        <SelectItem value="en">
                                            🇬🇧 English
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.locale} />
                            </div>

                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    form.data.locale === locale
                                }
                            >
                                {form.processing
                                    ? t('Menyimpan...')
                                    : t('Simpan bahasa')}
                            </Button>
                        </form>

                        <div className="rounded-lg border bg-muted/30 p-4 text-sm">
                            <p className="font-semibold">
                                {t('Cakupan terjemahan saat ini')}
                            </p>
                            <p className="mt-1 leading-6 text-muted-foreground">
                                {t(
                                    'Sidebar dan halaman pengaturan bahasa tersedia dalam Indonesia dan Inggris. Halaman operasional, validasi khusus modul, serta PDF akan diterjemahkan bertahap.',
                                )}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Language.layout = {
    breadcrumbs: [{ title: 'Bahasa & Regional', href: '/settings/language' }],
};
