# Together Rental: promosi staging ke production Rumahweb

Workflow ini **manual**. `main` tetap otomatis diterbitkan ke staging; production hanya berubah saat workflow `deploy-production` dijalankan dengan SHA lengkap yang masih menjadi `main` **dan** tercatat sebagai rilis staging aktif. Aset `public/build` disalin dari rilis staging dengan SHA itu. Database, APP_KEY, `.env`, storage, admin, dan document root production terpisah.

## 1. Persiapan cPanel (satu kali)

1. Pastikan document root domain utama adalah `/home/togr7678/public_html`, HTTPS `togetherkamera.com` valid, dan **PHP web domain utama** dipilih 8.4 melalui MultiPHP Manager. PHP CLI yang digunakan skrip adalah `/opt/cpanel/ea-php84/root/usr/bin/php`.
2. Buat database MySQL dan user **baru khusus production** melalui cPanel, lalu beri hak pada database itu. Jangan pakai `togr7678_tr_stg`. Catat nama database persis untuk variable GitHub `PRODUCTION_DB_NAME`.
3. Buat folder dan `.env` tersendiri di luar document root:

   ```bash
   TR_ROOT="$HOME/together-rental-production"
   TR_SHA=$(cat "$HOME/together-rental-staging-automation/current_sha")
   mkdir -p "$TR_ROOT/shared/storage/app/public" \
       "$TR_ROOT/shared/storage/app/private" \
       "$TR_ROOT/shared/storage/framework/cache/data" \
       "$TR_ROOT/shared/storage/framework/sessions" \
       "$TR_ROOT/shared/storage/framework/views" \
       "$TR_ROOT/shared/storage/logs"
   umask 077
   cp "$HOME/together-rental-release-${TR_SHA:0:12}/.env.example" "$TR_ROOT/shared/.env"
   chmod 600 "$TR_ROOT/shared/.env"
   nano "$TR_ROOT/shared/.env"
   ```

   Atur paling sedikit `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://togetherkamera.com`, `DB_CONNECTION=mysql`, host/port/database/user/password production, `APP_TIMEZONE=Asia/Jakarta`, `APP_LOCALE=id`, `INITIAL_BRANCH_CODE=PNG`, `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`. Siapkan pengiriman email production yang sesuai; `MAIL_MAILER=log` hanya mencatat email ke log.

   Setelah menyimpan `.env`, buat **APP_KEY baru** tanpa menampilkannya di terminal:

   ```bash
   TR_PHP=/opt/cpanel/ea-php84/root/usr/bin/php
   "$TR_PHP" -r '
       $p=$argv[1]; $s=file_get_contents($p);
       if(!preg_match("/^APP_KEY=.*$/m",$s)) throw new RuntimeException("APP_KEY line missing");
       $s=preg_replace("/^APP_KEY=.*$/m","APP_KEY=base64:".base64_encode(random_bytes(32)),$s,1);
       file_put_contents($p,$s,LOCK_EX);
       echo "Production APP_KEY generated\n";
   ' "$TR_ROOT/shared/.env"
   chmod 600 "$TR_ROOT/shared/.env"
   ```

   Jangan unggah `.env`, password database, atau private key ke GitHub/chat.

## 2. Persiapkan source production tanpa mengubah halaman aktif

Setelah perubahan workflow ini masuk `main` dan **staging sudah menunjukkan `STAGING DEPLOY PASS` untuk SHA baru**, gunakan SHA lengkap dari `$HOME/together-rental-staging-automation/current_sha`. Login SSH Rumahweb:

```bash
TR_SHA=$(cat "$HOME/together-rental-staging-automation/current_sha")
TR_DB_NAME='ISI_NAMA_DATABASE_PRODUCTION'
TR_PHP=/opt/cpanel/ea-php84/root/usr/bin/php
TR_SCRIPT="$HOME/together-rental-release-${TR_SHA:0:12}/scripts/deploy-production-release.sh"
bash "$TR_SCRIPT" "$TR_SHA" prepare "$TR_DB_NAME"
```

`PRODUCTION PREPARE PASS` menandakan source dan aset staging siap di release terpisah. Halaman sementara domain utama belum berubah. Bila folder rilis parsial tertinggal akibat error Composer, periksa isinya sebelum menyiapkan ulang; skrip tidak menghapusnya otomatis.

## 3. Inisialisasi database production yang masih kosong

Pastikan database production **kosong** dan backup melalui cPanel/phpMyAdmin sudah disimpan. Jangan menyalin data UAT/staging. Jalankan dari release production:

```bash
TR_RELEASE="$HOME/together-rental-production/releases/${TR_SHA:0:12}"
cd "$TR_RELEASE"
"$TR_PHP" -r '
    require "vendor/autoload.php";
    $app=require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $db=Illuminate\Support\Facades\DB::connection();
    $name=$db->selectOne("SELECT DATABASE() AS name")->name;
    if($name!==$argv[1] || count($db->select("SHOW TABLES"))!==0) throw new RuntimeException("DB tidak sesuai atau tidak kosong");
    echo "PRODUCTION EMPTY DB PASS: ".$name.PHP_EOL;
' "$TR_DB_NAME"
"$TR_PHP" artisan migrate --force --no-interaction
"$TR_PHP" artisan db:seed --force --no-interaction
```

Jika database sudah berisi tabel, **hentikan** langkah inisialisasi dan rencanakan migrasi/backup data yang sesuai. Skrip deploy juga menolak rilis dengan migrasi yang masih pending; migrasi masa depan harus diproses terpisah, setelah backup production.

Buat satu pengguna admin production dengan email dan password tersendiri. Contoh tanpa memasukkan password ke riwayat perintah:

```bash
read -r -p 'Nama admin production: ' TR_ADMIN_NAME
read -r -p 'Email admin production: ' TR_ADMIN_EMAIL
read -r -s -p 'Password admin production (minimal 16 karakter): ' TR_ADMIN_PASSWORD; printf '\n'
printf '%s\n' "$TR_ADMIN_PASSWORD" | "$TR_PHP" -r '
    require "vendor/autoload.php";
    $app=require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if(Illuminate\Support\Facades\DB::selectOne("SELECT DATABASE() AS db")->db!==$argv[1]) throw new RuntimeException("Wrong DB");
    if(App\Models\User::query()->exists()) throw new RuntimeException("Users already exist");
    $password=rtrim(stream_get_contents(STDIN),"\r\n");
    if(strlen($password)<16 || !filter_var($argv[3],FILTER_VALIDATE_EMAIL)) throw new RuntimeException("Invalid input");
    App\Models\User::create(["name"=>$argv[2],"email"=>$argv[3],"password"=>$password]);
    echo "ADMIN USER CREATED\n";
' "$TR_DB_NAME" "$TR_ADMIN_NAME" "$TR_ADMIN_EMAIL"
unset TR_ADMIN_PASSWORD
"$TR_PHP" artisan rental:bootstrap-admin "$TR_ADMIN_EMAIL" --branch=PNG --no-interaction
bash "$TR_SCRIPT" "$TR_SHA" check "$TR_DB_NAME"
```

Hasil terakhir harus `PRODUCTION PRECHECK PASS`. Periksa pula backup database, sertifikat TLS, alamat pengirim email, dan PHP web 8.4.

## 4. Jadwal proses aplikasi

Skrip `prepare` memasang pembungkus cron pada `$HOME/together-rental-production/bin/production-cron.sh`. Daftarkan di cPanel **Cron Jobs** sebelum pembukaan. Kedua baris dijalankan setiap menit (gunakan path HOME akun yang sebenarnya):

```text
* * * * * /bin/bash /home/togr7678/together-rental-production/bin/production-cron.sh schedule >> /home/togr7678/logs/tr-production-schedule.log 2>&1
* * * * * /bin/bash /home/togr7678/together-rental-production/bin/production-cron.sh queue >> /home/togr7678/logs/tr-production-queue.log 2>&1
```

Sebelum rilis pertama, wrapper menolak berjalan karena belum ada `current_sha`; itu diharapkan. Setelah rilis, scheduler menghasilkan notifikasi berkala dan queue memproses email/impor. Pastikan `flock` tersedia (`command -v flock`) untuk mencegah queue cron tumpang tindih. Pantau log dan `failed_jobs` setelah aktivasi.

## 5. GitHub Environment dan promosi

Buat **Environment `production`** pada repo dan batasi ke `main`. Aktifkan required reviewers jika tersedia. **Environment secrets**: `PROD_SSH_HOST`, `PROD_SSH_USER`, `PROD_SSH_KEY` (private key deploy khusus), `PROD_SSH_KNOWN_HOSTS` (host key yang diverifikasi). **Repository variables**: `PROD_SSH_PORT=2223` (atau port aktual), `PRODUCTION_DB_NAME` (nama database persis), `PRODUCTION_DEPLOY_ENABLED=true` setelah langkah 1–4 lulus. Uji SSH key dengan `BatchMode=yes` dari lokal sebelum memasang secret.

Di GitHub **Actions > deploy-production > Run workflow**, pilih `main`, isi SHA lengkap rilis staging yang diterima, dan ketik `PROMOTE_TO_PRODUCTION`. Workflow memeriksa SHA masih di `main`, lalu script server memeriksa SHA staging aktif, database production, migrasi, admin, aset, dan webroot sebelum mengganti halaman sementara. GitHub Environment dapat menahan akses secret sampai reviewer menyetujui.

**Jangan langsung membuka production** bila `PRODUCTION PRECHECK PASS` belum tampil. Satu commit baru setelah UAT akan menjadi SHA baru: tunggu staging menerbitkan commit itu terlebih dahulu, lalu promosikan SHA yang sama.

## 6. Verifikasi dan rollback

Hasil workflow: `PRODUCTION DEPLOY PASS: <SHA>` dan HTTP `/up`, `/login` 200. Periksa di browser desktop/HP: landing dan katalog publik ID/EN, login admin, booking, bukti pembayaran, invoice/receipt PDF, unggah gambar, logout; periksa cron dan log. Domain staging harus tetap mengarah ke database staging.

Script mencetak `BACKUP_ID backup-...`. Jika perlu kembali ke halaman sementara/rilis sebelumnya sebelum ada perubahan data yang bergantung pada kode baru:

```bash
bash "$HOME/tr-production-incoming/deploy-production-release.sh" \
    "$TR_SHA" rollback "$TR_DB_NAME" 'BACKUP_ID_DARI_OUTPUT'
```

Rollback ini mengembalikan `index.php`, `index.html`, `.htaccess`, dan manifest webroot saja; **tidak** membatalkan migrasi atau transaksi database. Skrip menolak rollback jika berkas produksi telah berubah setelah cutover. Aset hash lama dan release lama dibiarkan untuk pemeriksaan.
