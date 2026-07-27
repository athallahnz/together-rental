# Module 6.2 — Brand Visual & Faceted Catalog Filter

Module ini memperluas Catalog Intelligence dengan pencarian visual berdasarkan
canonical brand dan canonical model.

## Prinsip data

- Brand dan model merupakan master global perusahaan.
- Produk, stok, harga, dan aset tetap dapat memiliki cakupan cabang.
- Logo hanya metadata visual dan tidak pernah menjadi identitas relasi.
- Filter selalu menggunakan `catalog_brand_id` dan `catalog_model_id`.
- Model yang ditampilkan selalu dibatasi oleh brand yang sedang dipilih.

## Fitur

### Brand visual management

Company-scoped catalog manager dapat:

- mengunggah logo brand;
- mengganti logo tanpa meninggalkan file lama;
- menghapus logo;
- mengatur urutan tampil brand;
- melihat jumlah produk dan canonical model per brand.

Logo disimpan pada private application flow ke disk `public` di:

```text
catalog/brands/
```

Format upload yang diterima adalah PNG, JPG, dan WebP maksimal 2 MB. SVG tidak
diterima untuk mencegah script injection dari file vektor yang tidak tepercaya.

### Faceted filter

Halaman katalog menyediakan:

- filter cepat berupa logo brand;
- select brand dengan jumlah produk;
- select model yang aktif setelah brand dipilih;
- kombinasi filter brand, model, kategori, tracking, status, dan pencarian;
- filter tersimpan pada query string sehingga tetap aktif saat pagination atau
  refresh.

Pencarian teks mencakup SKU, nama produk, legacy brand/model, canonical
brand/model, serta alias canonical.

### Fallback visual

Jika logo belum diunggah atau gagal dimuat, UI menampilkan inisial brand. Produk
tanpa canonical brand menampilkan fallback `TB` (Tanpa brand).

## Authorization dan audit

- Semua user dengan `products.view` dapat melihat filter.
- Upload, penggantian, dan penghapusan logo memerlukan `products.manage` serta
  company-scoped role.
- Brand dari perusahaan lain ditolak dengan HTTP 403.
- Perubahan dicatat sebagai:
  - `catalog.brand.visual.updated`
  - `catalog.brand.logo.removed`

## Verifikasi

```powershell
herd php artisan migrate
herd php artisan test --filter=CatalogIntelligenceTest
herd php artisan test --filter=CatalogManagementTest
npm run format:check
npm run types:check
npm run lint:check
npm run build
herd php artisan test
```

Pastikan symbolic link storage sudah tersedia:

```powershell
herd php artisan storage:link
```

