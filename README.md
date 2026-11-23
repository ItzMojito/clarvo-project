# TUNTAS – Socratic AI Math Tutor (Prototype)

Prototipe aplikasi web TUNTAS berbasis Laravel + Livewire 4 dengan pemisahan antara tutor Sokratik AI, verifikasi simbolik deterministik SymPy, dan Bayesian Knowledge Tracing (BKT).

## Prasyarat
- **PHP 8.4.1+** (paket Symfony 8 di `composer.lock` menolak versi di bawahnya; PHP bawaan XAMPP 8.2 tidak cukup). Ekstensi: `pdo_sqlite`, `curl`, `openssl`, `mbstring`
- **Composer** & **Node.js (LTS)**
- **SQLite** (bawaan `.env.example`; MySQL tetap bisa dipakai lewat `DB_CONNECTION=mysql`)
- **Python 3.10+** (untuk layanan verifikasi simbolik SymPy di folder `sympy-service`)

## Cara Menjalankan

### 1. Konfigurasi Environment
```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
```
Buat berkas database SQLite kosong di `database/database.sqlite`, lalu isi `SYMPY_API_SECRET` (string acak apa saja, wajib: layanan SymPy menolak semua permintaan jika kosong) serta `GEMINI_API_KEY` (opsional, dari Google AI Studio Free Tier) pada file `.env`.

Jika Gemini gagal dengan `cURL error 60` di Windows, arahkan `curl.cainfo` di `php.ini` ke berkas `cacert.pem`. Hanya untuk uji lokal, `GEMINI_VERIFY_TLS=false` mematikan pemeriksaan sertifikat.

### 2. Migrasi & Seeder Database
```bash
php artisan migrate:fresh --seed
```

### 3. Jalankan Layanan Verifikasi SymPy (Python)
Buka terminal baru di folder `sympy-service`:
```bash
cd sympy-service
python -m venv venv
# Windows:
venv\Scripts\activate
# Linux/macOS:
source venv/bin/activate

pip install -r requirements.txt
uvicorn main:app --port 8001
```
Folder `venv` sudah masuk `.gitignore`; jangan di-commit, karena venv hanya berjalan di komputer tempat ia dibuat.

### 4. Jalankan Aplikasi Web Laravel
```bash
npm run build
php artisan serve
```
Akses aplikasi melalui browser:
- **Learner View (Halaman Siswa)**: `http://127.0.0.1:8000/`
- **Teacher View (Halaman Guru)**: `http://127.0.0.1:8000/teacher`

### 5. Menjalankan Automated Testing
Untuk memverifikasi logika BKT, pelindung anti-kebocoran Sokratik, dan alur gerbang:
```bash
php artisan test
```
Tes layanan SymPy (dengan venv aktif, dari folder `sympy-service`):
```bash
python -m unittest -v
```

## Arsitektur & Kepatuhan Privasi
1. **Privasi Siswa**: Siswa hanya diidentifikasi dengan kode (`S01`–`S06`), tanpa data pribadi (PII), tanpa penyimpanan teks jawaban mentah di database, dan riwayat obrolan hanya hidup di memori komponen Livewire.
2. **Pedagogi Sokratik**: Kunci jawaban tidak pernah dikirimkan ke LLM. Balasan diparafrase melalui Gemini via header `x-goog-api-key` dan disaring ketat dengan fungsi `isSafe()`.
3. **Verifikasi Deterministik**: Perhitungan matematika diverifikasi langsung oleh SymPy (bukan tebakan probabilitas LLM).
4. **Bayesian Knowledge Tracing (BKT)**: Mengatur pembukaan gerbang stepper 6 langkah berdasarkan probabilitas penguasaan $P(L) \ge 0.85$.
