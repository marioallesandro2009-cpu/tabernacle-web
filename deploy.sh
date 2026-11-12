#!/usr/bin/env bash
# Update situs di server cPanel setelah ada perubahan kode.
# Jalankan dari Terminal cPanel:   bash deploy.sh
#
# Yang dilakukan: ambil kode terbaru, pasang dependensi produksi, jalankan migrasi,
# bangun ulang cache, lalu minta antrean memuat kode baru.
set -euo pipefail

cd "$(dirname "$0")"

# Pakai PHP yang sama dengan situs. Ubah jika versi PHP Anda berbeda.
PHP="${PHP_BIN:-/opt/cpanel/ea-php84/root/usr/bin/php}"
[ -x "$PHP" ] || PHP="$(command -v php)"

# Composer: composer global kalau ada, kalau tidak pakai composer.phar di folder ini.
if command -v composer >/dev/null 2>&1; then
  COMPOSER="$PHP $(command -v composer)"
else
  [ -f composer.phar ] || { echo "composer.phar tidak ada. Lihat DEPLOY.md langkah 5."; exit 1; }
  COMPOSER="$PHP composer.phar"
fi

echo "==> PHP: $($PHP -r 'echo PHP_VERSION;')"

if [ -d .git ]; then
  echo "==> Mengambil kode terbaru"
  git pull --ff-only
fi

echo "==> Memasang dependensi (tanpa paket pengembangan)"
# --no-scripts: hosting ini mematikan proc_open, jadi skrip Composer dijalankan manual di bawah
$COMPOSER install --no-dev --optimize-autoloader --no-interaction --no-scripts
$PHP artisan package:discover --ansi

echo "==> Migrasi database"
$PHP artisan migrate --force

# Hanya peran/izin yang dijalankan ulang. Konten awal (ContentSeeder) cukup SEKALI saat
# instalasi pertama; menjalankannya lagi akan memunculkan kembali konten yang sudah Anda hapus.
echo "==> Peran dan izin"
$PHP artisan db:seed --class=RolesSeeder --force
# Teks antarmuka/pengaturan baru dari versi terbaru (hanya yang belum ada, tidak menimpa editan admin)
$PHP artisan db:seed --class=DefaultsSeeder --force

echo "==> Membangun cache"
$PHP artisan optimize:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache || true

echo "==> Izin folder"
chmod -R u+rwX,g+rX storage bootstrap/cache public/uploads || true

$PHP artisan queue:restart || true

echo "Selesai."
