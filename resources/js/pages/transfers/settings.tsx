import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Camera, FileText, Settings2 } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { Stage4Text, stage4Translate } from '@/components/stage4-text';
import type { Stage4Key } from '@/components/stage4-text';
import { useAppLocale } from '@/lib/i18n';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { TransferBranch } from '@/types';

type CaptureMode = 'camera_required' | 'camera_preferred' | 'gallery_allowed';

type SettingsForm = {
    branch_id: number;
    dispatch_capture_mode: CaptureMode;
    receiving_capture_mode: CaptureMode;
    dispatch_min_photos: number;
    receiving_min_photos: number;
    require_waybill: boolean;
    allow_gallery_override: boolean;
};

type Props = {
    branches: TransferBranch[];
    selectedBranchId: number;
    settings: Omit<SettingsForm, 'branch_id'>;
};

const captureOptions: Array<{
    value: CaptureMode;
    label: Stage4Key;
    description: Stage4Key;
}> = [
    {
        value: 'camera_required',
        label: "stage4.ui.19b92b9e5d81",
        description: "stage4.ui.421b2819a9e1",
    },
    {
        value: 'camera_preferred',
        label: "stage4.ui.457d8197ce51",
        description: "stage4.ui.4b375f0bcd11",
    },
    {
        value: 'gallery_allowed',
        label: "stage4.ui.d656c2cfa8b1",
        description: "stage4.ui.e58299ba9c8c",
    },
];

export default function TransferSettings({
    branches,
    selectedBranchId,
    settings,
}: Props) {
    const { locale: stage4Locale } = useAppLocale();

    const form = useForm<SettingsForm>({
        branch_id: selectedBranchId,
        ...settings,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put('/transfers/settings', { preserveScroll: true });
    };

    const switchBranch = (branchId: string) => {
        router.get(
            '/transfers/settings',
            { branch_id: Number(branchId) },
            { preserveState: false, replace: true },
        );
    };

    return (
        <>
            <Head title={stage4Translate("stage4.ui.91ffdc7ee124", stage4Locale)} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 md:p-6">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="mb-2 -ml-3"
                        >
                            <Link href="/transfers">
                                <ArrowLeft /><Stage4Text k="stage4.ui.604c5fa0e1ad" />
                            </Link>
                        </Button>
                        <p className="text-sm font-medium text-primary"><Stage4Text k="stage4.ui.e7d42379c783" />
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight"><Stage4Text k="stage4.ui.e1f642f53dcf" />
                        </h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground"><Stage4Text k="stage4.ui.82cb5c70f14d" />
                        </p>
                    </div>

                    <div className="w-full max-w-sm space-y-2">
                        <Label htmlFor="branch-filter"><Stage4Text k="stage4.ui.ad74c838e3bf" />
                        </Label>
                        <Select
                            value={String(selectedBranchId)}
                            onValueChange={switchBranch}
                        >
                            <SelectTrigger id="branch-filter">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {branches.map((branch) => (
                                    <SelectItem
                                        key={branch.id}
                                        value={String(branch.id)}
                                    >
                                        {branch.code} · {branch.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </header>

                <Alert>
                    <Settings2 />
                    <AlertTitle><Stage4Text k="stage4.ui.35d6e3032971" /></AlertTitle>
                    <AlertDescription><Stage4Text k="stage4.ui.22ca87fb397b" />
                    </AlertDescription>
                </Alert>

                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-6 xl:grid-cols-2">
                        <CapturePolicyCard
                            icon={<Camera className="size-5" />}
                            title={stage4Translate("stage4.ui.d9eb2f157a64", stage4Locale)}
                            description={stage4Translate("stage4.ui.6546bfb7b369", stage4Locale)}
                            mode={form.data.dispatch_capture_mode}
                            minPhotos={form.data.dispatch_min_photos}
                            modeError={form.errors.dispatch_capture_mode}
                            minPhotosError={form.errors.dispatch_min_photos}
                            onModeChange={(value) =>
                                form.setData('dispatch_capture_mode', value)
                            }
                            onMinPhotosChange={(value) =>
                                form.setData('dispatch_min_photos', value)
                            }
                        />

                        <CapturePolicyCard
                            icon={<Camera className="size-5" />}
                            title={stage4Translate("stage4.ui.f63456877850", stage4Locale)}
                            description={stage4Translate("stage4.ui.dc0d77c9bcc1", stage4Locale)}
                            mode={form.data.receiving_capture_mode}
                            minPhotos={form.data.receiving_min_photos}
                            modeError={form.errors.receiving_capture_mode}
                            minPhotosError={form.errors.receiving_min_photos}
                            onModeChange={(value) =>
                                form.setData('receiving_capture_mode', value)
                            }
                            onMinPhotosChange={(value) =>
                                form.setData('receiving_min_photos', value)
                            }
                        />
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="size-5" /><Stage4Text k="stage4.ui.c6ee59a59187" />
                            </CardTitle>
                            <CardDescription><Stage4Text k="stage4.ui.edac3bbff6e8" />
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <BooleanSetting
                                id="require_waybill"
                                checked={form.data.require_waybill}
                                title={stage4Translate("stage4.ui.5f011b83fd7d", stage4Locale)}
                                description={stage4Translate("stage4.ui.ec345ad957ee", stage4Locale)}
                                onCheckedChange={(checked) =>
                                    form.setData('require_waybill', checked)
                                }
                            />
                            <BooleanSetting
                                id="allow_gallery_override"
                                checked={form.data.allow_gallery_override}
                                title={stage4Translate("stage4.ui.7fb1bef293f5", stage4Locale)}
                                description={stage4Translate("stage4.ui.2b4fe5913323", stage4Locale)}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'allow_gallery_override',
                                        checked,
                                    )
                                }
                            />
                        </CardContent>
                    </Card>

                    <InputError message={form.errors.branch_id} />

                    <div className="flex justify-end gap-3">
                        <Button asChild type="button" variant="outline">
                            <Link href="/transfers"><Stage4Text k="stage4.ui.1433539c3b8f" /></Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? stage4Translate("stage4.ui.f16f7f9512ac", stage4Locale)
                                : stage4Translate("stage4.ui.fde73801c681", stage4Locale)}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

function CapturePolicyCard({
    icon,
    title,
    description,
    mode,
    minPhotos,
    modeError,
    minPhotosError,
    onModeChange,
    onMinPhotosChange,
}: {
    icon: ReactNode;
    title: string;
    description: string;
    mode: CaptureMode;
    minPhotos: number;
    modeError?: string;
    minPhotosError?: string;
    onModeChange: (value: CaptureMode) => void;
    onMinPhotosChange: (value: number) => void;
}) {
    const { locale: stage4Locale } = useAppLocale();

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    {icon}
                    {title}
                </CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
                <div className="space-y-2">
                    <Label><Stage4Text k="stage4.ui.e31676f5381c" /></Label>
                    <Select
                        value={mode}
                        onValueChange={(value) =>
                            onModeChange(value as CaptureMode)
                        }
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {captureOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {stage4Translate(option.label, stage4Locale)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <p className="text-xs leading-5 text-muted-foreground">
                        {(() => {
                            const option = captureOptions.find((item) => item.value === mode);

                            return option ? stage4Translate(option.description, stage4Locale) : null;
                        })()}
                    </p>
                    <InputError message={modeError} />
                </div>

                <div className="space-y-2">
                    <Label><Stage4Text k="stage4.ui.0b16f2b35d84" /></Label>
                    <Input
                        type="number"
                        min={1}
                        max={10}
                        value={minPhotos}
                        onChange={(event) =>
                            onMinPhotosChange(Number(event.target.value))
                        }
                    />
                    <p className="text-xs leading-5 text-muted-foreground"><Stage4Text k="stage4.ui.955e57cd037e" />
                    </p>
                    <InputError message={minPhotosError} />
                </div>
            </CardContent>
        </Card>
    );
}

function BooleanSetting({
    id,
    checked,
    title,
    description,
    onCheckedChange,
}: {
    id: string;
    checked: boolean;
    title: string;
    description: string;
    onCheckedChange: (checked: boolean) => void;
}) {
    return (
        <div className="flex items-start gap-3 rounded-xl border p-4">
            <Checkbox
                id={id}
                checked={checked}
                onCheckedChange={(value) => onCheckedChange(value === true)}
            />
            <div className="min-w-0">
                <Label htmlFor={id}>{title}</Label>
                <p className="mt-1 text-xs leading-5 text-muted-foreground">
                    {description}
                </p>
            </div>
        </div>
    );
}
