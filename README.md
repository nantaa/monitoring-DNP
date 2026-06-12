# DNP Riksa Uji Monitor

DNP Riksa Uji Monitor adalah aplikasi sistem informasi terintegrasi untuk melacak dan mengelola seluruh siklus operasional Riksa Uji K3 di PT DNP. Aplikasi ini dikembangkan untuk berjalan sebagai sistem *self-hosted* pada *bare-metal* Linux VPS tanpa ketergantungan pada layanan pihak ketiga.

Sistem ini mengelola alur kerja dari tahap PO/SPK oleh Marketing hingga proses teknis di lapangan oleh Inspektur, pengurusan Surat Keterangan (Suket) ke Disnaker, hingga penagihan oleh bagian Finance.

## 🏗️ Arsitektur Sistem
Aplikasi ini dibangun menggunakan tumpukan teknologi modern berkinerja tinggi (*monolithic stack*):

- **Backend:** Laravel 11 (PHP 8.3.6)
- **Frontend:** React 18 (TypeScript), Inertia.js, Tailwind CSS
- **Database:** PostgreSQL 18.0
- **Authentication:** Laravel Session / Sanctum
- **Environment:** Node.js, Vite (Asset bundling)

## 📦 Kebutuhan Sistem Lokal (Development)
Untuk menjalankan aplikasi ini di lingkungan lokal (seperti WAMP/XAMPP), pastikan Anda memiliki:
1. **PHP >= 8.3.6**
2. **PostgreSQL >= 14**
3. **Node.js >= 18** dan **npm**
4. Composer
5. Ekstensi PHP yang diaktifkan (buka `php.ini`):
   - `extension=pdo_pgsql`
   - `extension=pgsql`

## 🚀 Panduan Instalasi (Setup)

Ikuti langkah-langkah di bawah ini untuk mengatur dan menjalankan proyek di lingkungan pengembangan lokal Anda:

### 1. Kloning Repositori
```bash
git clone https://github.com/deltaindo/monitoring.git
cd monitoring/dnp-app
```

### 2. Konfigurasi Environment (`.env`)
Salin file environment contoh dan sesuaikan parameter database:
```bash
cp .env.example .env
```
Buka file `.env` dan pastikan pengaturan koneksi database PostgreSQL Anda sudah benar:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=dnp_monitor
DB_USERNAME=postgres
DB_PASSWORD="Admin123#" # Gunakan tanda kutip ganda jika terdapat karakter spesial
```

### 3. Instalasi Dependensi
Instal paket PHP melalui Composer dan paket JavaScript melalui npm:
```bash
# Pastikan Anda menggunakan PHP 8.3.6
C:\wamp64\bin\php\php8.3.6\php.exe composer.phar install
# Atau secara global:
composer install

# Install JS dependencies
npm install
```

### 4. Setup Kunci Aplikasi & Database
Hasilkan *application key* untuk Laravel dan jalankan migrasi beserta *seeder* data demo:
```bash
php artisan key:generate
php artisan migrate:fresh --seed
```

### 5. Kompilasi Aset Frontend (Vite)
Kompilasi komponen React dan file CSS menggunakan Vite.
Untuk mode pengembangan (*Hot Module Replacement*):
```bash
npm run dev
```
Untuk produksi (pembuatan berkas siap rilis):
```bash
npm run build
```

### 6. Menjalankan Server Lokal
Mulai server *development* Laravel:
```bash
php artisan serve
```
Akses aplikasi melalui peramban di `http://127.0.0.1:8000`.

## 🔐 Demo Akun
Sistem ini menggunakan *Role-Based Access Control* (RBAC). Migrasi awal (`DatabaseSeeder`) telah menyuntikkan beberapa akun untuk digunakan sebagai uji coba. Semua akun demo menggunakan kata sandi: **`password`**

- **Manager (Kadiv RU):** `terzha@dnp.co.id`
- **Admin Dokumen & Penjadwalan:** `admin@dnp.co.id`
- **Marketing:** `andini@dnp.co.id`
- **Inspektur / Tim Ahli:** `rendi@dnp.co.id`
- **Finance (Keuangan):** `finance@dnp.co.id`

> **Catatan:** Tampilan *dashboard* dan fungsionalitas yang tersedia akan menyesuaikan secara dinamis bergantung pada peran (*role*) akun yang digunakan untuk masuk.

## 📂 Struktur Repositori Utama
- `/dnp-app` — Direktori *source code* utama aplikasi Laravel + React.
- `/docs` — Menyimpan dokumen perancangan arsitektur sistem, aturan pemindahan SaaS, pedoman operasional, serta berkas simulasi antarmuka (prototipe).

## 🛠️ Fitur Utama Terintegrasi
- **7-Stage Workflow Pipeline**: Pelacakan otomatis pekerjaan (Marketing -> Adm. Dokumen -> Penjadwalan -> Pelaksanaan -> LHPP -> Suket -> Penagihan).
- **Inspektur Recommendation Service**: Sistem pintar bawaan untuk mencocokkan beban kerja (workload), spesialisasi, masa berlaku SKP (Surat Keterangan Penunjukan), dan domisili dalam menugaskan pekerjaan.
- **Audit Trails**: Fitur keamanan basis data untuk mencatat secara otomatis seluruh mutasi data (`INSERT`, `UPDATE`, `DELETE`) tanpa kecuali.
- **Kanban Board UI**: Visualisasi data tugas per etape (*stage*) secara *real-time* dengan antarmuka yang sangat responsif.

---
Dikembangkan oleh PT DNP.
