# Panduan demo Clarvo

Panduan ini untuk presentasi di laptop Windows. Clarvo berjalan tanpa internet; hanya Gemini (opsional) yang membutuhkannya. Tanpa Gemini, umpan balik tampil persis seperti yang ditulis Clarvo.

## Sehari sebelum demo

1. Pastikan instalasi di README (bagian Setup dan Running) sudah selesai, termasuk `venv` di folder `sympy-service`.
2. Bangun aset tampilan:

   ```powershell
   npm run build
   ```

3. Isi ulang data demo. Perintah ini menghapus semua data lalu membuat kelas demo `Class X-2`, akun guru demo, dan pelajar `S06` yang menguasai prasyarat dua hari lalu:

   ```powershell
   php artisan clarvo:demo-reset
   ```

## Tiga puluh menit sebelum demo

1. Jalankan kedua layanan sekaligus dari folder proyek:

   ```powershell
   .\scripts\start-demo.ps1
   ```

   Skrip membuka dua jendela (layanan SymPy di port 8001 dan aplikasi web di port 8000), lalu menjalankan pemeriksaan.

2. Baca hasil `php artisan clarvo:check`. Semua baris harus `OK`, kecuali Gemini yang boleh `MATI`. Jika ada `GAGAL`, ikuti keterangan di baris tersebut.
3. Buka http://127.0.0.1:8000/ dan tutup tab lain yang tidak diperlukan.

## Alur demo (sekitar tujuh menit)

1. **Beranda.** Tunjukkan logo, slogan, dan tiga cara masuk: siswa dengan kode kelas, guru, dan individual.
2. **Cek ulang di sesi berikutnya.** Masuk sebagai siswa dengan kode kelas `Class X-2` dan kode pelajar `S06`. Sesi dibuka dengan Gerbang 1 (angka baru) karena penguasaan prasyarat `S06` terakhir dibuktikan dua hari lalu. Jawab dengan benar; langkah berikutnya, bentuk faktor, terbuka.
3. **Petunjuk dan ulasan.** Tekan **Petunjuk** untuk menunjukkan petunjuk bertahap tanpa kunci jawaban. Kirim jawaban yang salah untuk menunjukkan ulasan singkat sebelum angka baru.
4. **Keyboard matematika.** Ketuk kotak jawaban, lalu masukkan jawaban lewat keyboard bawaan Clarvo.
5. **Dasbor guru.** Keluar, lalu masuk sebagai guru demo (alamat dan kata sandinya ada di `database/seeders/ClassDemoSeeder.php`). Tunjukkan baris `S02` dan `S04` yang ditandai perlu bantuan, konsep terlemah, dan filter.
6. **Mode individual.** Buka pendaftaran individual dan tunjukkan pernyataan usia atau persetujuan orang tua/wali.

Untuk mengulang demo dari awal, jalankan lagi `php artisan clarvo:demo-reset`.

## Jika ada masalah saat demo

| Gejala | Tindakan |
|---|---|
| Jawaban dibalas "tidak bisa dihubungi" | Layanan SymPy berhenti. Jawaban tersebut tidak dinilai, jadi tidak ada data yang rusak. Tutup jendela SymPy, jalankan ulang `.\scripts\start-demo.ps1`, lalu kirim ulang jawaban. |
| Halaman tampil tanpa gaya | Aset belum dibangun. Jalankan `npm run build`, lalu muat ulang halaman. |
| Pelajar `S06` tidak dibuka dengan cek ulang | `S06` sudah dipakai pada demo sebelumnya. Jalankan `php artisan clarvo:demo-reset`. |
| Gemini tidak aktif karena tidak ada internet | Lanjutkan saja. Umpan balik tetap tampil dari Clarvo. |

## Tes di ponsel asli

1. Sambungkan laptop dan ponsel ke Wi-Fi yang sama.
2. Jalankan skrip dengan opsi ponsel. Jika Windows meminta izin firewall, izinkan untuk jaringan pribadi saja:

   ```powershell
   .\scripts\start-demo.ps1 -Phone
   ```

3. Cari alamat IP laptop dengan `ipconfig` (baris `IPv4 Address` pada adaptor Wi-Fi), lalu buka `http://<alamat IP>:8000/` di peramban ponsel.
4. Periksa hal berikut dan catat yang gagal:
   - Ketuk kotak soal: keyboard matematika terbuka dan keyboard ponsel tidak muncul.
   - Tekan **abc**: keyboard ponsel muncul untuk mengetik pertanyaan.
   - Semua tombol mudah diketuk dengan satu jari, termasuk chip contoh soal.
   - Tidak ada bagian halaman yang terpotong atau bisa digeser ke samping.
   - Pendaftaran individual tidak bisa dikirim sebelum pernyataan usia dicentang.
   - Putar ponsel ke posisi mendatar dan ulangi pemeriksaan keyboard.
5. Setelah selesai, tutup kedua jendela layanan. Mode `-Phone` membuka aplikasi untuk perangkat lain di jaringan yang sama, jadi jangan biarkan berjalan di Wi-Fi umum.
