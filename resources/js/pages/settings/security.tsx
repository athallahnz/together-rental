import { Form, Head } from '@inertiajs/react';
import { KeyRound, LockKeyhole, ShieldCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import { useRef } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/security';
/* @chisel-passkeys */
import type { Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import ManagePasskeys from '@/components/manage-passkeys';
/* @end-chisel-passkeys */
/* @chisel-2fa */
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import ManageTwoFactor from '@/components/manage-two-factor';
/* @end-chisel-2fa */

type Props = {
    passwordRules: string;
} /* @chisel-passkeys */ & ManagePasskeysProps /* @end-chisel-passkeys */ /* @chisel-2fa */ &
    ManageTwoFactorProps /* @end-chisel-2fa */;

export default function Security(props: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const passkeys = props.passkeys ?? [];

    return (
        <>
            <Head title="Pengaturan keamanan" />

            <h1 className="sr-only">Pengaturan keamanan</h1>

            <div className="space-y-6">
                <Heading
                    title="Keamanan akun"
                    description="Kelola password, autentikasi dua faktor, dan passkey dalam satu area keamanan."
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <SecuritySummary
                        icon={<LockKeyhole className="size-4" />}
                        label="Password"
                        value="Aktif"
                    />
                    {/* @chisel-2fa */}
                    <SecuritySummary
                        icon={<ShieldCheck className="size-4" />}
                        label="Two-factor"
                        value={props.twoFactorEnabled ? 'Aktif' : 'Belum aktif'}
                        muted={!props.twoFactorEnabled}
                    />
                    {/* @end-chisel-2fa */}
                    {/* @chisel-passkeys */}
                    <SecuritySummary
                        icon={<KeyRound className="size-4" />}
                        label="Passkey"
                        value={`${passkeys.length} tersimpan`}
                        muted={passkeys.length === 0}
                    />
                    {/* @end-chisel-passkeys */}
                </div>

                <Card className="overflow-hidden">
                    <CardHeader className="border-b bg-muted/20">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <LockKeyhole className="size-5" />
                            Ubah password
                        </CardTitle>
                        <CardDescription>
                            Gunakan password yang panjang dan unik untuk menjaga
                            keamanan akun.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-6">
                        <Form
                            {...SecurityController.update.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            resetOnError={[
                                'password',
                                'password_confirmation',
                                'current_password',
                            ]}
                            resetOnSuccess
                            onError={(errors) => {
                                if (errors.password) {
                                    passwordInput.current?.focus();
                                }

                                if (errors.current_password) {
                                    currentPasswordInput.current?.focus();
                                }
                            }}
                            className="space-y-6"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-5 xl:grid-cols-3">
                                        <div className="grid gap-2">
                                            <Label htmlFor="current_password">
                                                Password saat ini
                                            </Label>

                                            <PasswordInput
                                                id="current_password"
                                                ref={currentPasswordInput}
                                                name="current_password"
                                                autoComplete="current-password"
                                                placeholder="Password saat ini"
                                            />

                                            <InputError
                                                message={
                                                    errors.current_password
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="password">
                                                Password baru
                                            </Label>

                                            <PasswordInput
                                                id="password"
                                                ref={passwordInput}
                                                name="password"
                                                autoComplete="new-password"
                                                placeholder="Password baru"
                                                passwordrules={
                                                    props.passwordRules
                                                }
                                            />

                                            <InputError
                                                message={errors.password}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="password_confirmation">
                                                Konfirmasi password
                                            </Label>

                                            <PasswordInput
                                                id="password_confirmation"
                                                name="password_confirmation"
                                                autoComplete="new-password"
                                                placeholder="Ulangi password baru"
                                                passwordrules={
                                                    props.passwordRules
                                                }
                                            />

                                            <InputError
                                                message={
                                                    errors.password_confirmation
                                                }
                                            />
                                        </div>
                                    </div>

                                    <div className="flex justify-end border-t pt-5">
                                        <Button
                                            disabled={processing}
                                            data-test="update-password-button"
                                        >
                                            {processing
                                                ? 'Menyimpan...'
                                                : 'Simpan password'}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {/* @chisel-2fa */}
                <Card>
                    <CardContent className="pt-6">
                        <ManageTwoFactor
                            canManageTwoFactor={props.canManageTwoFactor}
                            requiresConfirmation={props.requiresConfirmation}
                            twoFactorEnabled={props.twoFactorEnabled}
                        />
                    </CardContent>
                </Card>
                {/* @end-chisel-2fa */}

                {/* @chisel-passkeys */}
                <Card>
                    <CardContent className="pt-6">
                        <ManagePasskeys
                            canManagePasskeys={props.canManagePasskeys}
                            passkeys={props.passkeys}
                        />
                    </CardContent>
                </Card>
                {/* @end-chisel-passkeys */}
            </div>
        </>
    );
}

function SecuritySummary({
    icon,
    label,
    value,
    muted = false,
}: {
    icon: ReactNode;
    label: string;
    value: string;
    muted?: boolean;
}) {
    return (
        <div className="rounded-xl border bg-card p-4 shadow-sm">
            <div className="flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {icon}
                {label}
            </div>
            <p
                className={
                    muted
                        ? 'mt-2 text-sm font-semibold text-muted-foreground'
                        : 'mt-2 text-sm font-semibold text-foreground'
                }
            >
                {value}
            </p>
        </div>
    );
}

Security.layout = {
    breadcrumbs: [
        {
            title: 'Pengaturan keamanan',
            href: edit(),
        },
    ],
};
