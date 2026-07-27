# Catalog Intelligence

Catalog Intelligence adalah lapisan normalisasi master produk pada Database
Rental Management V2.

## Prinsip

- deterministic first;
- AI optional;
- human approval;
- non-destructive canonical grouping;
- company scoped;
- transactional execute;
- auditable dan rollback-safe.

## Status Kandidat

| Status | Arti |
| --- | --- |
| `pending` | suggestion deterministik menunggu keputusan |
| `needs_review` | confidence di bawah threshold |
| `approved` | siap dieksekusi |
| `rejected` | tidak akan dieksekusi |
| `executed` | sudah diterapkan dan diverifikasi |
| `rolled_back` | hasil execute telah dikembalikan |

## Menambah Alias Brand

Kamus awal ditanam melalui `RentalFoundationSeeder`. Alias baru juga dapat
terbentuk ketika admin memasukkan brand manual dan mengeksekusi kandidat.

Satu alias disimpan sebagai nilai asli dan `normalized_alias`. Pencarian selalu
memakai nilai normalized agar tidak sensitif terhadap kapitalisasi dan spasi.

## Integrasi AI Mendatang

Buat implementasi dari:

```php
App\Domain\Catalog\Intelligence\CatalogAiSuggestionProvider
```

Provider wajib mengembalikan struktur:

```php
[
    'brand' => 'Sony',
    'model' => 'FX30',
    'variant' => null,
    'confidence' => 88,
    'reasoning' => ['FX30 dikenali sebagai kamera Sony Cinema Line.'],
]
```

Kemudian bind provider tersebut pada container. Jangan memberi provider akses
untuk menulis langsung ke database.

## Guardrail Rollback

Rollback hanya mengembalikan produk jika canonical brand/model saat ini masih
sama dengan hasil run. Produk yang telah diedit setelah execute dilewati agar
perubahan manual terbaru tidak tertimpa.
