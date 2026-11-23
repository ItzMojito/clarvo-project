# TUNTAS – Socratic AI Math Tutor (Prototype)

Prototipe aplikasi web TUNTAS berbasis Laravel + Livewire 4 dengan pemisahan antara tutor Sokratik AI, verifikasi simbolik deterministik SymPy, dan Bayesian Knowledge Tracing (BKT).

## Prasyarat
- **PHP 8.3+** (ekstensi `pdo_mysql`, `curl`, `openssl`, `mbstring`)
- **Composer** & **Node.js (LTS)**
- **MySQL** (misalnya via XAMPP)
- **Python 3.10+** (untuk layanan verifikasi simbolik SymPy di folder `sympy-service`)

## Cara Menjalankan

### 1. Konfigurasi Environment
```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
```
Isi konfigurasi database MySQL (`tuntas_db`), `SYMPY_API_SECRET` (string acak), serta `GEMINI_API_KEY` (opsional, dari Google AI Studio Free Tier) pada file `.env`.

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

### 4. Jalankan Aplikasi Web Laravel
```bash
npm run build
php artisan serve
```
Akses aplikasi melalui browser:
- **Learner View (Halaman Siswa)**: `http://127.0.0.1:8000/`
- **Teacher View (Halaman Guru)**: `http://127.0.0.1:8000/teacher`

### 5. Menjalankan Automated Testing
Untuk memverifikasi logika BKT dan pelindung anti-kebocoran Sokratik:
```bash
php artisan test
```

## Arsitektur & Kepatuhan Privasi
1. **Privasi Siswa**: Siswa hanya diidentifikasi dengan kode (`S01`–`S06`), tanpa data pribadi (PII), tanpa penyimpanan teks jawaban mentah di database, dan riwayat obrolan hanya hidup di memori komponen Livewire.
2. **Pedagogi Sokratik**: Kunci jawaban tidak pernah dikirimkan ke LLM. Balasan diparafrase melalui Gemini via header `x-goog-api-key` dan disaring ketat dengan fungsi `isSafe()`.
3. **Verifikasi Deterministik**: Perhitungan matematika diverifikasi langsung oleh SymPy (bukan tebakan probabilitas LLM).
4. **Bayesian Knowledge Tracing (BKT)**: Mengatur pembukaan gerbang stepper 6 langkah berdasarkan probabilitas penguasaan $P(L) \ge 0.85$.
