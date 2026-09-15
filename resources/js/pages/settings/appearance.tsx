import { Head } from '@inertiajs/react';
import { Palette } from 'lucide-react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="Pengaturan tampilan" />

            <h1 className="sr-only">Pengaturan tampilan</h1>

            <div className="space-y-6">
                <Heading
                    title="Tampilan"
                    description="Atur preferensi tema antarmuka untuk akun Anda."
                />

                <Card className="overflow-hidden">
                    <CardHeader className="border-b bg-muted/20">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Palette className="size-5" />
                            Tema antarmuka
                        </CardTitle>
                        <CardDescription>
                            Pilihan ini hanya memengaruhi tampilan akun Anda dan
                            tidak mengubah konfigurasi pengguna lain.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-6">
                        <AppearanceTabs />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Pengaturan tampilan',
            href: editAppearance(),
        },
    ],
};
