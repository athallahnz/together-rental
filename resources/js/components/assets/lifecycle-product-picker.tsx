import { useId, useMemo, useState } from 'react';
import { Check, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

type Product = {
    id: number;
    sku: string;
    name: string;
    replacement_value: string;
};
export type ProductAssetCounts = Record<string, BranchAssetCount[]>;

type BranchAssetCount = { branch_id: number; count: number };
type Branch = { id: number; code: string; name: string };
type AssetFilter = 'all' | 'recorded' | 'empty';

type Props = {
    assetCounts: ProductAssetCounts;
    branches: Branch[];
    branchId: string;
    products: Product[];
    value: string;
    locale: 'id' | 'en';
    onSelect: (product: Product) => void;
};

export function LifecycleProductPicker({
    products,
    assetCounts,
    branches,
    branchId,
    value,
    locale,
    onSelect,
}: Props) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [filter, setFilter] = useState<AssetFilter>('all');
    const branch = branches.find((item) => String(item.id) === branchId);
    const inputId = useId();
    const en = locale === 'en';
    const selected = products.find((product) => String(product.id) === value);
    const countAtBranch = (product: Product) =>
        assetCounts[product.id]?.find(
            (row) => String(row.branch_id) === branchId,
        )?.count ?? 0;
    const results = useMemo(() => {
        const words = query
            .toLocaleLowerCase()
            .trim()
            .split(/\s+/)
            .filter(Boolean);

        return products.filter((product) =>
            words.every((word) =>
                `${product.sku} ${product.name}`
                    .toLocaleLowerCase()
                    .includes(word),
            ),
        );
    }, [products, query]).filter((product) => {
        if (!branch || filter === 'all') {
            return true;
        }

        const hasAssets = countAtBranch(product) > 0;

        return filter === 'recorded' ? hasAssets : !hasAssets;
    });
    const selectedCount = selected ? countAtBranch(selected) : 0;

    const branchSummary = (product: Product) =>
        branches
            .flatMap((item) => {
                const count = assetCounts[product.id]?.find(
                    (row) => row.branch_id === item.id,
                )?.count;

                return count ? [`${item.code}: ${count}`] : [];
            })
            .join(' · ');

    return (
        <>
            <Dialog
                open={open}
                onOpenChange={(next) => {
                    setOpen(next);

                    if (next) {
                        setQuery('');
                        setFilter('all');
                    }
                }}
            >
                <DialogTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        className="h-auto min-h-10 w-full justify-start text-left whitespace-normal"
                    >
                        <Search className="shrink-0" />
                        <span>
                            {selected
                                ? `${selected.sku} — ${selected.name}`
                                : en
                                  ? 'Search and select a product'
                                  : 'Cari dan pilih produk'}
                        </span>
                    </Button>
                </DialogTrigger>
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>
                            {en
                                ? 'Select serialized product'
                                : 'Pilih produk serialized'}
                        </DialogTitle>
                        <DialogDescription>
                            {en
                                ? 'Search by product name or SKU. Use arrow keys or Tab to select a result.'
                                : 'Cari berdasarkan nama produk atau SKU. Gunakan tombol panah atau Tab untuk memilih hasil.'}
                        </DialogDescription>
                    </DialogHeader>
                    <label htmlFor={inputId} className="sr-only">
                        {en ? 'Product name or SKU' : 'Nama produk atau SKU'}
                    </label>
                    <Input
                        id={inputId}
                        autoFocus
                        value={query}
                        placeholder={
                            en ? 'Type a name or SKU…' : 'Ketik nama atau SKU…'
                        }
                        onChange={(event) => setQuery(event.target.value)}
                    />
                    {branch && (
                        <div
                            className="flex flex-wrap gap-2"
                            role="group"
                            aria-label={
                                en
                                    ? 'Filter by recorded assets'
                                    : 'Filter aset tercatat'
                            }
                        >
                            <p className="w-full text-sm font-medium">
                                {en ? 'Acquisition branch' : 'Cabang perolehan'}
                                : {branch.name}
                            </p>
                            {(
                                [
                                    ['all', en ? 'All' : 'Semua'],
                                    [
                                        'recorded',
                                        en ? 'Has assets' : 'Sudah ada aset',
                                    ],
                                    [
                                        'empty',
                                        en ? 'No assets yet' : 'Belum ada aset',
                                    ],
                                ] satisfies [AssetFilter, string][]
                            ).map(([option, label]) => (
                                <Button
                                    key={option}
                                    type="button"
                                    size="sm"
                                    variant={
                                        filter === option
                                            ? 'default'
                                            : 'outline'
                                    }
                                    aria-pressed={filter === option}
                                    onClick={() => setFilter(option)}
                                >
                                    {label}
                                </Button>
                            ))}
                        </div>
                    )}
                    <p
                        className="text-xs text-muted-foreground"
                        aria-live="polite"
                    >
                        {results.length} {en ? 'products' : 'produk'}
                    </p>
                    <div
                        className="max-h-80 overflow-y-auto rounded-md border p-1"
                        onKeyDown={(event) => {
                            if (
                                event.key !== 'ArrowDown' &&
                                event.key !== 'ArrowUp'
                            ) {
                                return;
                            }

                            const buttons = Array.from(
                                event.currentTarget.querySelectorAll<HTMLButtonElement>(
                                    'button',
                                ),
                            );
                            const index = buttons.indexOf(
                                document.activeElement as HTMLButtonElement,
                            );
                            const next =
                                event.key === 'ArrowDown'
                                    ? Math.min(buttons.length - 1, index + 1)
                                    : Math.max(0, index - 1);
                            buttons[next]?.focus();
                            event.preventDefault();
                        }}
                    >
                        {results.map((product) => (
                            <Button
                                key={product.id}
                                type="button"
                                variant="ghost"
                                className="h-auto min-h-12 w-full justify-between text-left whitespace-normal"
                                aria-pressed={String(product.id) === value}
                                onClick={() => {
                                    onSelect(product);
                                    setOpen(false);
                                }}
                            >
                                <span>
                                    <span className="block font-medium">
                                        {product.name}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {product.sku}
                                    </span>
                                    {branch && (
                                        <span className="mt-1 block text-xs font-medium">
                                            {branch.code}:{' '}
                                            {countAtBranch(product)}{' '}
                                            {en ? 'active units' : 'unit aktif'}
                                        </span>
                                    )}
                                    {branchSummary(product) && (
                                        <span className="block text-xs text-muted-foreground">
                                            {en ? 'By branch' : 'Per cabang'}:{' '}
                                            {branchSummary(product)}
                                        </span>
                                    )}
                                </span>
                                {String(product.id) === value && (
                                    <Check className="shrink-0" />
                                )}
                            </Button>
                        ))}
                        {results.length === 0 && (
                            <p className="p-6 text-center text-sm text-muted-foreground">
                                {en
                                    ? 'No matching products.'
                                    : 'Produk tidak ditemukan.'}
                            </p>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
            {selected && branch && (
                <div
                    className="rounded-md border bg-muted/40 p-3 text-xs"
                    role="status"
                >
                    <p className="font-medium">
                        {branch.name}: {selectedCount}{' '}
                        {en ? 'active units recorded' : 'unit aktif tercatat'}
                    </p>
                    {branchSummary(selected) && (
                        <p className="mt-1 text-muted-foreground">
                            {en ? 'By branch' : 'Per cabang'}:{' '}
                            {branchSummary(selected)}
                        </p>
                    )}
                    {selectedCount > 0 && (
                        <p className="mt-1 text-muted-foreground">
                            {en
                                ? 'Check the physical unit and serial number before recording another unit.'
                                : 'Cek unit fisik dan nomor seri sebelum mencatat unit tambahan.'}
                        </p>
                    )}
                </div>
            )}
        </>
    );
}
