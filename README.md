# HNG MRTG Online

Portal monitoring MikroTik dengan status PPPoE, riwayat ping dan trafik, serta laporan PDF. Repository ini berisi source, skema kosong, dan contoh konfigurasi.

## Penyiapan

1. Siapkan PHP 8.3 dengan mysqli, mbstring, DOM, dan Nginx. Salin source ke /var/www/mrtg.
2. Buat akun database khusus. Jalankan sql/schema.sql untuk inventaris router. Buat database mrtg_history dengan charset utf8mb4, lalu impor sql/history-schema.sql dan sql/history-ranges.sql ke database tersebut. Berikan masing-masing akun akses hanya ke database yang diperlukan.
3. Simpan config/router-db.php.example sebagai /etc/hng-mrtg/router-db.php dan config/history.json.example sebagai /etc/hng-mrtg/history.json. Isi kredensial sendiri dan buat secret riwayat dengan openssl rand -hex 32. Atur kepemilikan root:www-data dan izin 0640. Gunakan MRTG_ROUTER_CONFIG atau MRTG_HISTORY_CONFIG jika menyimpan file di lokasi lain.
4. Sesuaikan deploy/nginx/mrtg.conf.example dengan domain dan socket PHP-FPM. Aktifkan HTTPS sebelum login dan jalankan nginx -t sebelum memuat ulang Nginx.
5. Buat pengguna melalui bin/create-user.php. Masukkan password awal dengan perintah read yang disembunyikan, lalu hapus variabelnya setelah selesai.

~~~bash
cd /var/www/mrtg
read -r -s -p 'Kata sandi awal MRTG: ' MRTG_INITIAL_PASSWORD
printf '\n'
export MRTG_INITIAL_PASSWORD
php bin/create-user.php admin
unset MRTG_INITIAL_PASSWORD
~~~

Masukkan inventaris router melalui aplikasi. Setelah menguji akses router dan database, pasang unit deploy/systemd/hng-mrtg-history.service ke /etc/systemd/system dan aktifkan dengan systemctl enable --now hng-mrtg-history.

## Laporan PDF

Salin runtime/pdf/composer.json dan composer.lock ke /opt/hng-mrtg-pdf. Jalankan composer install --no-dev --prefer-dist di direktori itu. Buat /var/cache/hng-mrtg-pdf dengan izin tulis untuk www-data. Jika hanya menggunakan monitoring, bagian PDF dapat disiapkan kemudian.

Konfigurasi produksi, database operasional, dan riwayat monitoring disimpan di luar repository.
