import { Link, usePage } from '@inertiajs/react';
import { BadgeCheck, ContactRound, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useGlobalLocale } from '@/lib/locale-store';
import { stage3Translate } from '@/components/stage3-text';
import { cn } from '@/lib/utils';

const items = [
    {
        titleKey: 'stage3.ui.correction.akun.pengguna.433cd',
        href: '/users',
        icon: Users,
        permission: 'users.view',
    },
    {
        titleKey: 'stage3.ui.correction.karyawan.jabatan.1c11a',
        href: '/employees',
        icon: ContactRound,
        permission: 'users.view',
    },
    {
        titleKey: 'stage3.ui.correction.role.permission.fb006',
        href: '/roles',
        icon: BadgeCheck,
        permission: 'roles.view',
    },
] as const;

export function AccessNav({ current }: { current: string }) {
    const { auth } = usePage().props;
    const stage3Locale = useGlobalLocale();

    return (
        <nav className="flex flex-wrap gap-2">
            {items
                .filter((item) => auth.permissions[item.permission])
                .map((item) => (
                    <Button
                        key={item.href}
                        asChild
                        size="sm"
                        variant={current === item.href ? 'default' : 'outline'}
                        className={cn(
                            current === item.href &&
                                'pointer-events-none shadow-sm',
                        )}
                    >
                        <Link href={item.href}>
                            <item.icon />
                            {stage3Translate(item.titleKey, stage3Locale)}
                        </Link>
                    </Button>
                ))}
        </nav>
    );
}
