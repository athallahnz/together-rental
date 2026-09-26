import { Form, Head, usePage } from '@inertiajs/react';
/* @chisel-email-verification */
import { Link } from '@inertiajs/react';
/* @end-chisel-email-verification */
import { CircleUserRound } from 'lucide-react';
import { useAppLocale } from '@/lib/i18n';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types';
/* @chisel-email-verification */
import { send } from '@/routes/verification';
/* @end-chisel-email-verification */

type PageProps = {
    auth: Auth;
};

export default function Profile(
    /* @chisel-email-verification */
    {
        mustVerifyEmail,
        status,
    }: {
        mustVerifyEmail: boolean;
        status?: string;
    },
    /* @end-chisel-email-verification */
) {
    const { tr } = useAppLocale();
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title={tr('settings.profile.page')} />

            <h1 className="sr-only">{tr('settings.profile.page')}</h1>

            <div className="space-y-6">
                <Heading
                    title={tr('settings.profile.title')}
                    description={tr('settings.profile.description')}
                />

                <Card className="overflow-hidden">
                    <CardHeader className="border-b bg-muted/20">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <CircleUserRound className="size-5" />
                            {tr('settings.profile.card')}
                        </CardTitle>
                        <CardDescription>
                            {tr('settings.profile.cardDescription')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-6">
                        <Form
                            {...ProfileController.update.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-5 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">
                                                {tr('settings.profile.name')}
                                            </Label>

                                            <Input
                                                id="name"
                                                defaultValue={auth.user.name}
                                                name="name"
                                                required
                                                autoComplete="name"
                                                placeholder={tr(
                                                    'settings.profile.name',
                                                )}
                                            />

                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="email">
                                                {tr('settings.profile.email')}
                                            </Label>

                                            <Input
                                                id="email"
                                                type="email"
                                                defaultValue={auth.user.email}
                                                name="email"
                                                required
                                                autoComplete="username"
                                                placeholder="nama@email.com"
                                            />

                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>
                                    </div>

                                    {/* @chisel-email-verification */}
                                    {mustVerifyEmail &&
                                        auth.user.email_verified_at ===
                                            null && (
                                            <div className="rounded-lg border border-dashed bg-muted/20 p-4">
                                                <p className="text-sm text-muted-foreground">
                                                    {tr(
                                                        'settings.profile.unverified',
                                                    )}{' '}
                                                    <Link
                                                        href={send()}
                                                        as="button"
                                                        className="font-medium text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors hover:decoration-current dark:decoration-neutral-500"
                                                    >
                                                        {tr(
                                                            'settings.profile.resend',
                                                        )}
                                                    </Link>
                                                    .
                                                </p>

                                                {status ===
                                                    'verification-link-sent' && (
                                                    <p className="mt-2 text-sm font-medium text-green-600">
                                                        {tr(
                                                            'settings.profile.sent',
                                                        )}
                                                    </p>
                                                )}
                                            </div>
                                        )}
                                    {/* @end-chisel-email-verification */}

                                    <div className="flex justify-end border-t pt-5">
                                        <Button
                                            disabled={processing}
                                            data-test="update-profile-button"
                                        >
                                            {processing
                                                ? tr('settings.profile.saving')
                                                : tr('settings.profile.save')}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <div className="rounded-xl border bg-card p-5 shadow-sm sm:p-6">
                    <DeleteUser />
                </div>
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Pengaturan profil',
            href: edit(),
        },
    ],
};
