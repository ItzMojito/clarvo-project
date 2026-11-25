<?php

namespace App\Services\Tuntas;

class AdaptiveTheoryService
{
    /**
     * Menentukan tingkat kedalaman teori berdasarkan penguasaan BKT P(L) dan riwayat kesulitan siswa.
     * - baby     : bahasa paling sederhana / analogi sehari-hari (saat P(L) turun / gagal beruntun / minta bantuan)
     * - standard : teori ringkas terstruktur tingkat SMP/SMA
     * - advanced : teori analitik lebih menantang (saat penguasaan P(L) naik tinggi secara mandiri)
     */
    public function resolveTier(
        float $pL,
        int $failedAttempts = 0,
        int $hintLevel = 0,
        bool $justDropped = false
    ): string {
        if ($pL < 0.30 || $failedAttempts >= 2 || $hintLevel >= 2 || ($pL <= 0.30 && $failedAttempts >= 1) || ($justDropped && $pL < 0.55)) {
            return 'baby';
        }

        if ($pL >= 0.65 && $failedAttempts === 0 && !$justDropped) {
            return 'advanced';
        }

        return 'standard';
    }

    public function tierLabel(string $tier): string
    {
        return match ($tier) {
            'baby'     => 'Teori Dasar · Bahasa Sederhana',
            'advanced' => 'Teori Lanjutan · Analisis Mendalam',
            default    => 'Teori Singkat · Terstruktur',
        };
    }

    /**
     * Menghasilkan pengantar teori sebelum pertanyaan diberikan sesuai tingkat penguasaan P(L).
     */
    public function buildTheoryIntro(string $requiredForm, array $params, string $tier): string
    {
        $family = $params['family'] ?? 'quadratic';
        if ($family !== 'quadratic') {
            return $this->nonQuadraticTheory($family, $params, $tier);
        }

        $c = (int) ($params['c'] ?? 0);
        $b = (int) ($params['b'] ?? 0);

        if ($requiredForm === 'integers_pair') {
            return $this->integersTheory($b, $c, $tier);
        }

        if ($requiredForm === 'factored_form') {
            return $this->factoringTheory($tier);
        }

        return $this->rootsTheory($tier);
    }

    /**
     * Penjelasan tambahan saat jawaban matematika siswa masih keliru, menyesuaikan arah turunnya P(L).
     */
    public function buildWrongAnswerScaffold(string $requiredForm, array $params, string $tier): string
    {
        $family = $params['family'] ?? 'quadratic';
        if ($family !== 'quadratic') {
            return $this->nonQuadraticScaffold($family, $requiredForm, $params, $tier);
        }

        $c = (int) ($params['c'] ?? 0);
        $b = (int) ($params['b'] ?? 0);

        if ($tier === 'baby') {
            return match ($requiredForm) {
                'integers_pair' => $c < 0
                    ? 'Kita pakai cara paling gampang ya: karena target hasil kalinya bertanda negatif, dua angkanya wajib beda kubu — satu angka positif dan satu angka negatif. Cari pasangan yang selisihnya pas dengan target jumlah.'
                    : ($b < 0
                        ? 'Cara paling gampangnya begini: hasil kalinya positif tetapi hasil jumlahnya negatif, berarti kedua angka rahasianya sama-sama negatif (ibarat utang ditambah utang).'
                        : 'Cara gampangnya: kedua angka sama-sama positif, cukup cari dua angka kecil yang kalau dikalikan dan dijumlahkan cocok dengan target.'),
                'factored_form' => 'Kita sederhanakan ya: tulis dua tanda kurung berdampingan berisi variabel x lalu masukkan kedua angka tadi beserta tandanya masing-masing tanpa dikalikan balik.',
                default         => 'Cara paling mudah: lihat masing-masing kurung secara terpisah, lalu tebak angka apa yang membuat isi di dalam satu kurung itu bernilai nol.',
            };
        }

        if ($tier === 'advanced') {
            return match ($requiredForm) {
                'integers_pair' => 'Tinjau kembali dekomposisi faktor bilangan bulat dari konstanta target; pastikan keseimbangan tanda aljabar memenuhi relasi penjumlahan dan perkalian sekaligus.',
                'factored_form' => 'Pastikan kedua faktor linear (x + p)(x + q) konsisten mengekspresikan identitas ekspansi tanpa perubahan tanda suku tengah maupun konstanta.',
                default         => 'Evaluasi kembali akar linear dari masing-masing faktor berdasarkan Sifat Hasil Kali Nol agar kedua persamaan linear bernilai nol.',
            };
        }

        return match ($requiredForm) {
            'integers_pair' => $c < 0
                ? 'Ingat aturan tanda: hasil kali negatif berasal dari satu bilangan positif dan satu bilangan negatif.'
                : 'Perhatikan tanda hasil kali dan jumlahnya; gunakan pasangan faktor bilangan bulat dari target perkalian.',
            'factored_form' => 'Tuliskan kembali dalam bentuk perkalian dua faktor linear tanpa menjabarkan ulang.',
            default         => 'Samakan setiap faktor kurung dengan nol lalu selesaikan untuk x.',
        };
    }

    /**
     * Tangga 3 petunjuk adaptif sesuai penguasaan (wajib diawali "Petunjuk N:" agar konsisten).
     */
    public function buildAdaptiveHint(string $requiredForm, int $level, array $params, string $tier): string
    {
        $family = $params['family'] ?? 'quadratic';
        if ($family !== 'quadratic') {
            return $this->nonQuadraticHint($family, $requiredForm, $level, $params, $tier);
        }

        $c = (int) ($params['c'] ?? 0);
        $b = (int) ($params['b'] ?? 0);

        if ($tier === 'baby') {
            return match ($requiredForm) {
                'integers_pair' => match ($level) {
                    1 => $c < 0
                        ? 'Petunjuk 1: Bayangkan kamu butuh dua angka yang kalau dikali hasilnya negatif. Itu tandanya satu angka harus bertanda positif dan satunya bertanda negatif.'
                        : ($b < 0
                            ? 'Petunjuk 1: Bayangkan dua angka yang sama-sama negatif. Kalau dikali hasilnya jadi positif, dan kalau dijumlahkan hasilnya tetap negatif.'
                            : 'Petunjuk 1: Bayangkan dua angka positif sederhana yang kalau dikali dan ditambah menghasilkan angka yang diminta.'),
                    2 => $c < 0
                        ? 'Petunjuk 2: Karena tandanya berbeda (satu positif dan satu negatif), coba kurangkan besar kedua angkanya agar hasilnya cocok dengan target penjumlahan.'
                        : 'Petunjuk 2: Coba daftar pasangan angka kecil yang hasil kalinya pas, lalu tes satu per satu hasil penjumlahannya.',
                    default => 'Petunjuk 3: Pilih angka yang lebih besar nilainya mengikuti tanda target penjumlahan, lalu pastikan hasil kali keduanya tepat.',
                },
                'factored_form' => match ($level) {
                    1 => 'Petunjuk 1: Bayangkan dua kantong kurung (x ...)(x ...). Isi titik-titik itu dengan dua angka yang memenuhi syarat kali dan jumlah.',
                    2 => 'Petunjuk 2: Jika angkanya positif tulis tanda tambah di dalam kurung, dan jika angkanya negatif tulis tanda kurang.',
                    default => 'Petunjuk 3: Biarkan kedua kurung tetap berdampingan sebagai bentuk perkalian, tidak perlu dihitung ulang menjadi x².',
                },
                default => match ($level) {
                    1 => 'Petunjuk 1: Dua kurung dikalikan hasilnya nol berarti salah satu kurung itu isinya harus habis menjadi nol.',
                    2 => 'Petunjuk 2: Ambil satu kurung saja dulu, lalu pikirkan angka pengganti x supaya hasilnya tepat nol.',
                    default => 'Petunjuk 3: Tanda bilangan akar selalu kebalikan dari tanda yang tertulis di dalam kurung, lalu pisahkan kedua hasilnya dengan koma.',
                },
            };
        }

        if ($tier === 'advanced') {
            return match ($requiredForm) {
                'integers_pair' => match ($level) {
                    1 => 'Petunjuk 1: Analisis paritas tanda dari konstanta c; selisih tanda menentukan apakah kita menjumlahkan besaran atau mengambil selisih mutlaknya.',
                    2 => 'Petunjuk 2: Gunakan faktorisasi prima konstanta c untuk mendaftar seluruh pasangan pembagi bulat yang mungkin sebelum menyaring relasi jumlah b.',
                    default => 'Petunjuk 3: Verifikasi bahwa pasangan terpilih memenuhi sistem simetri penjumlahan dan perkalian Vieta secara eksak.',
                },
                'factored_form' => match ($level) {
                    1 => 'Petunjuk 1: Dekomposisi polinomial monik x² + bx + c menjadi produk faktor linear (x + p)(x + q) bergantung pada ketepatan tanda p dan q.',
                    2 => 'Petunjuk 2: Verifikasi suku tengah (p + q)x dan suku konstan pq sesuai identitas distributif.',
                    default => 'Petunjuk 3: Nyatakan jawaban secara ringkas sebagai hasil kali kanonik dua faktor linear tanpa ekspansi ulang.',
                },
                default => match ($level) {
                    1 => 'Petunjuk 1: Terapkan Sifat Hasil Kali Nol pada medan bilangan riil: hasil kali faktor bernilai nol jika dan hanya jika minimal satu faktor nol.',
                    2 => 'Petunjuk 2: Selesaikan kedua persamaan linear terpisah dan pastikan invers aditif konstantanya berlawanan tanda.',
                    default => 'Petunjuk 3: Periksa substitusi balik kedua nilai x tersebut ke persamaan kuadrat awal hingga menghasilkan nol.',
                },
            };
        }

        return match ($requiredForm) {
            'integers_pair' => match ($level) {
                1 => 'Petunjuk 1: Periksa tanda hasil kalinya lebih dulu. Hasil kali positif berasal dari dua bilangan bertanda sama, sedangkan hasil kali negatif berasal dari tanda berlawanan.',
                2 => 'Petunjuk 2: Bandingkan dengan tanda hasil jumlahnya untuk menentukan apakah kedua bilangan bernilai positif, negatif, atau campuran.',
                default => 'Petunjuk 3: Daftar pasangan faktor dari hasil kalinya, lalu cek pasangan mana yang jumlahnya tepat sama dengan target.',
            },
            'factored_form' => match ($level) {
                1 => 'Petunjuk 1: Bentuk faktor dari x² + bx + c selalu berbentuk perkalian dua kurung (x + p)(x + q).',
                2 => 'Petunjuk 2: Cari dua bilangan p dan q yang hasil kalinya sama dengan konstanta dan jumlahnya sama dengan koefisien x.',
                default => 'Petunjuk 3: Pastikan menuliskan dua tanda kurung berdampingan tanpa menjabarkannya kembali.',
            },
            default => match ($level) {
                1 => 'Petunjuk 1: Jika hasil kali dua kurung sama dengan nol, maka minimal salah satu kurung bernilai nol.',
                2 => 'Petunjuk 2: Samakan masing-masing kurung dengan nol, lalu pindahkan konstanta ke ruas kanan.',
                default => 'Petunjuk 3: Perhatikan perubahan tanda saat memindahkan konstanta melewati tanda sama dengan.',
            },
        };
    }

    /**
     * Materi Ulasan Singkat (panel Remedial) yang dinamis menyesuaikan konsep, tanda soal, dan tingkat kesulitan.
     */
    public function buildRemedialReview(string $requiredForm, array $params, string $tier): array
    {
        $family = $params['family'] ?? 'quadratic';
        if ($family !== 'quadratic') {
            return $this->nonQuadraticRemedial($family, $requiredForm, $params);
        }

        $c = (int) ($params['c'] ?? 0);
        $b = (int) ($params['b'] ?? 0);

        if ($requiredForm === 'integers_pair') {
            if ($c < 0) {
                return [
                    'badge' => 'ULASAN SINGKAT · BAHASA SEDERHANA',
                    'title' => 'Aturan Tanda: Hasil Kali Negatif',
                    'body'  => 'Kalau dua angka dikalikan lalu hasilnya negatif (minus), dua angka itu pasti beda jenis: yang satu positif (+) dan satunya lagi negatif (−). Saat dijumlahkan, angka yang nilainya lebih besar akan menentukan tanda hasil akhirnya.',
                ];
            }

            if ($b < 0) {
                return [
                    'badge' => 'ULASAN SINGKAT · BAHASA SEDERHANA',
                    'title' => 'Aturan Tanda: Hasil Kali Positif, Jumlah Negatif',
                    'body'  => 'Bilangan negatif dikali negatif menghasilkan positif, sedangkan dua bilangan negatif bila dijumlahkan tetap menghasilkan negatif (ibarat utang ditambah utang). Jadi kedua bilanganmu sama-sama negatif.',
                ];
            }

            return [
                'badge' => 'ULASAN SINGKAT · BAHASA SEDERHANA',
                'title' => 'Aturan Tanda: Hasil Kali & Jumlah Positif',
                'body'  => 'Jika hasil kali dan hasil jumlahnya sama-sama positif, maka kedua bilangan rahasiamu bernilai positif. Cukup daftarkan pasangan faktor perkaliannya sampai jumlahnya cocok.',
            ];
        }

        if ($requiredForm === 'factored_form') {
            return [
                'badge' => 'ULASAN SINGKAT · KONSEP FAKTOR',
                'title' => 'Menyusun Dua Kurung Faktor',
                'body'  => 'Bentuk faktor berarti menulis persamaan sebagai perkalian dua kurung (x + p)(x + q). Masukkan kedua angka yang hasil kalinya sama dengan konstanta akhir dan jumlahnya sama dengan angka di depan x.',
            ];
        }

        return [
            'badge' => 'ULASAN SINGKAT · AKAR PERSAMAAN',
            'title' => 'Menemukan Nilai x dari Kurung Faktor',
            'body'  => 'Supaya perkalian dua kurung sama dengan nol, salah satu kurungnya harus bernilai nol. Ambil nilai x yang tandanya berlawanan dengan angka di dalam masing-masing kurung.',
        ];
    }

    private function integersTheory(int $b, int $c, string $tier): string
    {
        if ($tier === 'baby') {
            if ($c < 0) {
                return 'Teori Singkat (Bahasa Sederhana): Sebelum memecahkan soal ini, bayangkan kamu mencari dua angka rahasia. Karena target hasil kalinya bertanda negatif, dua angka itu pasti berbeda jenis — satu positif dan satu negatif. Angka yang lebih besar menentukan apakah jumlahnya nanti positif atau negatif.';
            }
            if ($b < 0) {
                return 'Teori Singkat (Bahasa Sederhana): Bayangkan kamu punya dua angka rahasia. Kalau dua angka bertanda negatif dikalikan hasilnya menjadi positif, tapi kalau keduanya digabung hasilnya tetap negatif — persis seperti utang ditambah utang!';
            }
            return 'Teori Singkat (Bahasa Sederhana): Kita cukup mencari dua angka positif yang kalau dikalikan tepat mengenai target kali, dan kalau digabung tepat mengenai target jumlah.';
        }

        if ($tier === 'advanced') {
            return 'Teori Analitik: Berdasarkan identitas ekspansi (x + p)(x + q) = x² + (p + q)x + pq, memfaktorkan trinomial monik menuntut dekomposisi konstanta menjadi pasangan bilangan bulat p dan q yang memenuhi relasi Vieta untuk jumlah dan hasil kali sekaligus. Mari uji ketepatan analisis tandamu.';
        }

        return 'Teori Singkat: Untuk memfaktorkan persamaan kuadrat berbentuk x² + bx + c = 0, langkah pertamanya adalah mencari dua bilangan bulat yang hasil kalinya sama dengan konstanta akhir dan hasil jumlahnya sama dengan koefisien x. Perhatikan tanda positif atau negatifnya terlebih dahulu sebelum mencoba angka.';
    }

    private function factoringTheory(string $tier): string
    {
        if ($tier === 'baby') {
            return 'Teori Singkat (Bahasa Sederhana): Menulis bentuk faktor itu seperti menaruh dua angka rahasia tadi ke dalam dua kantong kurung (x ...)(x ...). Kalau angkanya positif pakai tanda tambah (+), kalau negatif pakai tanda kurang (−).';
        }

        if ($tier === 'advanced') {
            return 'Teori Lanjutan: Menurut faktorisasi tunggal polinomial monik atas himpunan bilangan bulat, setiap trinomial yang dapat difaktorkan terurai tepat menjadi hasil kali dua faktor linear (x + p)(x + q). Verifikasi korespondensi koefisiennya tanpa menjabarkannya kembali.';
        }

        return 'Teori Singkat: Setelah menemukan dua bilangan bulat p dan q yang memenuhi hasil kali dan jumlah, bentuk kuadrat x² + bx + c dapat ditulis menjadi perkalian dua faktor linear (x + p)(x + q).';
    }

    private function rootsTheory(string $tier): string
    {
        if ($tier === 'baby') {
            return 'Teori Singkat (Bahasa Sederhana): Kalau dua angka di dalam kurung dikalikan hasilnya nol, berarti minimal salah satu kurungnya harus bernilai nol! Pikirkan nilai x yang membuat masing-masing kurung habis menjadi nol.';
        }

        if ($tier === 'advanced') {
            return 'Teori Lanjutan: Menerapkan Sifat Hasil Kali Nol (Zero-Product Property) pada struktur bilangan riil, persamaan (x + p)(x + q) = 0 terpecah menjadi dua persamaan linear homogen x + p = 0 atau x + q = 0 yang menghasilkan akar-akar uniknya.';
        }

        return 'Teori Singkat: Pada bentuk faktor (x + p)(x + q) = 0, sifat hasil kali nol menyatakan bahwa minimal salah satu kurung bernilai nol. Selesaikan masing-masing kurung untuk memperoleh nilai-nilai x.';
    }

    private function nonQuadraticTheory(string $family, array $params, string $tier): string
    {
        if ($family === 'modulo') {
            return match ($tier) {
                'baby' => 'Teori Singkat (Bahasa Sederhana): Modulus (a mod m) artinya mencari SISA setelah dibagi rata! Bayangkan kamu membagi permen ke beberapa kotak dengan isi sama banyak; sisa permen yang tidak muat menjadi satu kotak penuh itulah jawaban modulus.',
                'advanced' => 'Teori Analitik: Pada kongruensi modular a ≡ r (mod m) dengan 0 ≤ r < m, algoritma pembagian Euclidean menyatakan a = m·q + r. Tentukan hasil bagi bulat q terlebih dahulu untuk mengisolasi residu r secara eksak.',
                default => 'Teori Singkat: Operasi modulus a mod m menghasilkan sisa dari pembagian bilangan bulat a oleh m. Langkah pertamanya adalah mencari berapa kali m dapat masuk utuh ke dalam a, lalu menghitung selisih sisanya.',
            };
        }

        if ($family === 'division') {
            return match ($tier) {
                'baby' => 'Teori Singkat (Bahasa Sederhana): Pembagian a ÷ b berarti memecah a menjadi kelompok-kelompok kecil berjumlah b. Hitung dulu berapa kelompok penuh yang terbentuk sebelum mencatat sisa angka yang tersisa.',
                'advanced' => 'Teori Analitik: Menurut teorema algoritma pembagian pada himpunan bilangan bulat, setiap pasangan (a, b) dengan b > 0 memiliki hasil bagi q dan sisa r tunggal yang memenuhi a = b·q + r dan 0 ≤ r < b.',
                default => 'Teori Singkat: Pada pembagian a ÷ b, hasil bagi bulat menunjukkan berapa kali pembagi b masuk utuh ke dalam a tanpa melebihinya, sedangkan sisa pembagian adalah selisih dari kelipatan tersebut.',
            };
        }

        if ($family === 'linear') {
            return match ($tier) {
                'baby' => 'Teori Singkat (Bahasa Sederhana): Bayangkan persamaan linear seperti timbangan kiri dan kanan yang harus tetap seimbang. Pindahkan angka biasa ke sisi sebelah dengan membalik tandanya (+ jadi −, − jadi +), baru bagi supaya tinggal x sendiri.',
                'advanced' => 'Teori Analitik: Persamaan linear satu variabel ax + b = c diselesaikan melalui transformasi ekuivalen menggunakan invers penjumlahan untuk mengisolasi suku variabel dan invers perkalian untuk memperoleh solusi rasional tunggal x.',
                default => 'Teori Singkat: Untuk menyelesaikan persamaan linear ax + b = c, kumpulkan semua konstanta di ruas kanan (tanda berbalik saat melewati tanda sama dengan), lalu bagilah kedua ruas dengan koefisien x.',
            };
        }

        $subtype = $params['subtype'] ?? 'arithmetic';
        return match ($subtype) {
            'percent' => match ($tier) {
                'baby' => 'Teori Singkat (Bahasa Sederhana): Persen (%) artinya "per seratus". Supaya gampang, kalikan dulu angka persen dengan bilangannya, setelah itu tinggal dibagi seratus!',
                'advanced' => 'Teori Lanjutan: Operasi persentase p% dari N merepresentasikan proporsi rasional (p / 100) × N. Hitung produk pembilang secara eksak sebelum reduksi desimal oleh penyebut 100.',
                default => 'Teori Singkat: Rumus dasar persentase adalah p% dari N = (p × N) / 100. Kita selesaikan perkalian pembilangnya lebih dulu sebelum membagi dengan seratus.',
            },
            'pythagoras' => match ($tier) {
                'baby' => 'Teori Singkat (Bahasa Sederhana): Pada segitiga siku-siku, sisi miring selalu paling panjang. Caranya: kuadratkan masing-masing sisi tegak, jumlahkan keduanya, lalu cari akarnya!',
                'advanced' => 'Teori Analitik: Berdasarkan Teorema Pythagoras pada ruang Euclidean, kuadrat hipotenusa sama dengan jumlah kuadrat kedua sisi siku-siku (c² = a² + b²).',
                default => 'Teori Singkat: Teorema Pythagoras menyatakan c² = a² + b². Jumlahkan kuadrat kedua sisi penyikunya terlebih dahulu, kemudian tarik akar kuadratnya.',
            },
            'geometry' => match ($tier) {
                'baby' => 'Teori Singkat (Bahasa Sederhana): Kita pecah rumus bangun datar menjadi langkah kecil dulu (seperti menjumlahkan sisi atau mengalikan alas dan tinggi), baru selesaikan rumus utamanya pelan-pelan.',
                'advanced' => 'Teori Lanjutan: Rumus luas dan keliling bangun datar menerapkan prinsip aditif keliling serta produk ortogonal dimensi alas dan tinggi.',
                default => 'Teori Singkat: Untuk rumus bangun datar (luas atau keliling), kerjakan komponen dasar panjang, lebar, alas, atau tinggi secara bertahap sebelum menerapkan pengali rumus akhirnya.',
            },
            'root' => match ($tier) {
                'baby' => 'Teori Singkat (Bahasa Sederhana): Akar kuadrat (√n) itu kebalikan dari pangkat dua: kamu cukup menebak bilangan mana yang kalau dikalikan dengan dirinya sendiri hasilnya tepat sama dengan n.',
                'advanced' => 'Teori Lanjutan: Akar kuadrat utama √n adalah nilai non-negatif tunggal r sehingga r² = n.',
                default => 'Teori Singkat: Mencari √n berarti menemukan bilangan bulat positif r yang memenuhi r × r = n.',
            },
            'power' => match ($tier) {
                'baby' => 'Teori Singkat (Bahasa Sederhana): Pangkat artinya mengalikan angka yang sama secara berulang! Hati-hati, jangan langsung mengalikan basis dengan pangkatnya ya.',
                'advanced' => 'Teori Lanjutan: Eksponensiasi bilangan bulat a^n merupakan produk berulang basis a sebanyak n faktor secara asosiatif.',
                default => 'Teori Singkat: Bentuk pangkat a^n berarti mengalikan bilangan a secara berulang sebanyak n kali, dimulai dari pangkat dua (a × a).',
            },
            default => match ($tier) {
                'baby' => 'Teori Singkat (Bahasa Sederhana): Pada hitungan campur, ada aturan antrean: kerjakan yang di dalam kurung dulu, lalu perkalian/pembagian, baru terakhir tambah dan kurang!',
                'advanced' => 'Teori Lanjutan: Evaluasi ekspresi aritmetika mengikuti hierarki presedens operator (tanda kurung, perkalian/pembagian kiri-ke-kanan, lalu penjumlahan/pengurangan).',
                default => 'Teori Singkat: Ingat urutan prioritas operasi matematika (tanda kurung → kali/bagi → tambah/kurang) agar hasil perhitungannya akurat.',
            },
        };
    }

    private function nonQuadraticScaffold(string $family, string $requiredForm, array $params, string $tier): string
    {
        if ($family === 'modulo') {
            return $tier === 'baby'
                ? 'Cara gampangnya: coba kalikan pembaginya satu per satu sampai paling dekat dengan bilangan awal tanpa melewatinya. Lalu kurangkan bilangan awal dengan hasil kali itu untuk melihat sisanya.'
                : 'Cek ulang kelipatan pembagi m yang paling dekat tetapi tidak melebihi a, lalu hitung selisihnya sebagai sisa bagi.';
        }

        if ($family === 'division') {
            if ($requiredForm === 'quotient_remainder') {
                return $tier === 'baby'
                    ? 'Cara gampangnya: tulis dua angka dipisah koma — angka depan berapa kelompok penuh yang berhasil dibagi, angka belakang berapa sisa yang tertinggal (tulis 0 bila pas tanpa sisa).'
                    : 'Tulis format hasil_bagi, sisa (contoh: 4, 3). Pastikan pembagi × hasil_bagi + sisa sama dengan bilangan awal.';
            }
            return $tier === 'baby'
                ? 'Cara mudahnya: tebak perkalian pembagi dengan angka bulat kecil yang hasilnya mendekati bilangan awal tapi jangan sampai kelewatan!'
                : 'Cari bilangan bulat q terbesar sehingga hasil kali q × pembagi tidak melebihi bilangan yang dibagi.';
        }

        if ($family === 'linear') {
            return $tier === 'baby'
                ? 'Pelan-pelan ya: tiap angka yang melompat melewati tanda sama dengan (=) harus ganti tanda (+ menjadi −, dan − menjadi +), baru setelah itu dibagi dengan angka di depan x.'
                : 'Periksa kembali tanda saat memindahkan konstanta ke ruas kanan, lalu pastikan membagi dengan koefisien x secara tepat.';
        }

        return $tier === 'baby'
            ? 'Kita cek pelan-pelan ya: fokus pada satu langkah perhitungan kecil sesuai yang diminta di kotak pertanyaan, jangan terburu-buru melompat ke langkah akhir.'
            : 'Periksa kembali urutan langkah perhitungan rumus (perkalian, pembagian, kuadrat, atau tanda kurung prioritas).';
    }

    private function nonQuadraticHint(string $family, string $requiredForm, int $level, array $params, string $tier): string
    {
        if ($family === 'modulo') {
            if ($tier === 'baby') {
                return match ($level) {
                    1 => 'Petunjuk 1: Coba hitung kelipatan pembaginya (1×, 2×, 3×, ...) sampai mendekati bilangan awal tanpa melebihinya.',
                    2 => 'Petunjuk 2: Setelah dapat kelipatan paling besar yang masih muat, kurangkan bilangan awal dengan kelipatan tersebut.',
                    default => 'Petunjuk 3: Angka selisih yang tersisa (selalu lebih kecil dari pembagi) itulah hasil modulusnya!',
                };
            }
            return match ($level) {
                1 => 'Petunjuk 1: Cari hasil bagi bulat q sehingga m × q ≤ a < m × (q + 1).',
                2 => 'Petunjuk 2: Hitung hasil produk kelipatan utuhnya (m × q), lalu kurangkan dari nilai awal a.',
                default => 'Petunjuk 3: Nilai sisa r = a − (m × q) selalu berada di antara 0 sampai m − 1.',
            };
        }

        if ($family === 'division') {
            if ($tier === 'baby') {
                return match ($level) {
                    1 => 'Petunjuk 1: Pikirkan pembagi dikali angka berapa yang hasilnya paling dekat ke bilangan awal tapi tidak lebih besar.',
                    2 => $requiredForm === 'quotient_remainder'
                        ? 'Petunjuk 2: Setelah dapat hasil baginya, hitung sisa = bilangan awal dikurangi (hasil bagi × pembagi).'
                        : 'Petunjuk 2: Coba cek ulang perkalian pembagi dengan angka tebakanmu agar tidak kelewatan target.',
                    default => $requiredForm === 'quotient_remainder'
                        ? 'Petunjuk 3: Tuliskan dua angka dipisah koma: hasil_bagi, sisa (tulis 0 bila habis dibagi).'
                        : 'Petunjuk 3: Tuliskan cukup satu bilangan bulat hasil bagi yang paling mendekati tanpa melebihi target.',
                };
            }
            return match ($level) {
                1 => 'Petunjuk 1: Gunakan perkalian kebalikan untuk mencari kelipatan pembagi terdekat di bawah bilangan awal.',
                2 => 'Petunjuk 2: Pastikan selisih sisanya lebih kecil dari pembagi dan tidak bernilai negatif.',
                default => $requiredForm === 'quotient_remainder'
                    ? 'Petunjuk 3: Format masukan harus tepat dua bilangan bulat: hasil_bagi, sisa.'
                    : 'Petunjuk 3: Masukkan bilangan bulat hasil baginya tanpa sisa.',
            };
        }

        if ($family === 'linear') {
            return match ($level) {
                1 => $tier === 'baby'
                    ? 'Petunjuk 1: Pindahkan angka tanpa huruf x ke seberang tanda sama dengan, dan jangan lupa ubah tandanya (+ jadi −, atau − jadi +).'
                    : 'Petunjuk 1: Terapkan invers penjumlahan untuk memindahkan suku konstanta ke ruas kanan persamaan.',
                2 => 'Petunjuk 2: Hitung hasil penjumlahan atau pengurangan pada ruas kanan terlebih dahulu hingga tinggal suku x di ruas kiri.',
                default => 'Petunjuk 3: Terakhir, bagilah angka di ruas kanan dengan angka koefisien di depan x.',
            };
        }

        $subtype = $params['subtype'] ?? 'arithmetic';
        return match ($subtype) {
            'percent' => match ($level) {
                1 => 'Petunjuk 1: Ingat bahwa lambang persen (%) berarti per 100.',
                2 => 'Petunjuk 2: Kalikan dahulu persentase dengan nilai pokoknya, baru bagi hasilnya dengan 100.',
                default => 'Petunjuk 3: Periksa posisi koma/desimal bila pembagian dengan 100 menghasilkan bilangan pecahan.',
            },
            'pythagoras' => match ($level) {
                1 => 'Petunjuk 1: Kuadratkan masing-masing kedua sisi penyiku lebih dulu (a × a dan b × b).',
                2 => 'Petunjuk 2: Jumlahkan kedua hasil kuadrat tersebut untuk mendapatkan c².',
                default => 'Petunjuk 3: Tarik akar kuadrat dari jumlah tadi untuk memperoleh sisi miring c.',
            },
            'geometry' => match ($level) {
                1 => 'Petunjuk 1: Perhatikan rumus bangun datar yang ditanyakan (panjang × lebar untuk luas persegi panjang, atau jumlah seluruh sisi untuk keliling).',
                2 => 'Petunjuk 2: Kerjakan operasi awal di dalam kurung atau perkalian alas × tinggi sebelum faktor akhir (× 2 atau ÷ 2).',
                default => 'Petunjuk 3: Pastikan hasil akhir ditulis sebagai satu nilai angka tanpa satuan teks.',
            },
            default => match ($level) {
                1 => $tier === 'baby'
                    ? 'Petunjuk 1: Kerjakan bagian yang paling diprioritaskan dulu (di dalam kurung, pangkat, atau perkalian/pembagian).'
                    : 'Petunjuk 1: Identifikasi sub-ekspresi dengan prioritas operasi tertinggi lebih dulu.',
                2 => 'Petunjuk 2: Hitung selangkah demi selangkah agar tidak tertukar antara operasi penjumlahan dan perkalian.',
                default => 'Petunjuk 3: Periksa kembali perhitungan akhirmu dan tuliskan satu bilangan hasil akhirnya.',
            },
        };
    }

    private function nonQuadraticRemedial(string $family, string $requiredForm, array $params): array
    {
        if ($family === 'modulo') {
            return [
                'badge' => 'ULASAN SINGKAT · MODULUS',
                'title' => 'Memahami Hasil Bagi & Sisa (Modulus)',
                'body'  => 'Dalam operasi a mod m, pertama cari berapa kali m masuk utuh ke dalam a tanpa melebihinya. Setelah itu kurangkan a dengan kelipatan utuh tersebut. Sisa pengurangan itulah nilai modulusnya.',
            ];
        }

        if ($family === 'division') {
            return [
                'badge' => 'ULASAN SINGKAT · PEMBAGIAN',
                'title' => 'Hubungan Hasil Bagi & Sisa',
                'body'  => 'Setiap pembagian bulat a ÷ b mengikuti aturan: bilangan awal = (hasil bagi × pembagi) + sisa, dengan sisa selalu lebih kecil dari pembagi. Jika habis dibagi, tulis sisanya 0.',
            ];
        }

        if ($family === 'linear') {
            return [
                'badge' => 'ULASAN SINGKAT · PERSAMAAN LINEAR',
                'title' => 'Aturan Pindah Ruas & Pembagian Koefisien',
                'body'  => 'Untuk mencari x pada persamaan linear, pindahkan suku konstanta melewati tanda sama dengan sambil membalik tandanya (+ jadi −, − jadi +). Setelah tinggal ax = k, bagilah k dengan a.',
            ];
        }

        return [
            'badge' => 'ULASAN SINGKAT · RUMUS DASAR',
            'title' => 'Urutan & Pemecahan Langkah Rumus',
            'body'  => 'Pecah perhitungan rumus menjadi langkah kecil: hitung operasi prioritas (kurung, pangkat, atau perkalian dasar) terlebih dahulu sebelum melakukan pembagian, penarikan akar, atau penggabungan hasil akhir.',
        ];
    }

    /**
     * Membersihkan pembatas LaTeX ($...$, $$...$$, \(...\)) serta markup Markdown agar teks matematika tampil polos dan natural.
     */
    public static function stripLatexMarkers(string $text): string
    {
        $clean = preg_replace('/\$\$\s*(.+?)\s*\$\$/s', '$1', $text);
        $clean = preg_replace('/\$([^$\n]+?)\$/u', '$1', (string) $clean);
        $clean = preg_replace('/\\\\\(\s*(.+?)\s*\\\\\)/s', '$1', (string) $clean);
        $clean = preg_replace('/\\\\\[\s*(.+?)\s*\\\\\]/s', '$1', (string) $clean);
        $clean = preg_replace('/\*\*([^*\n]+?)\*\*/u', '$1', (string) $clean);
        $clean = preg_replace('/`([^`\n]+?)`/u', '$1', (string) $clean);
        $clean = preg_replace('/\\\\frac\{([^{}]+)\}\{([^{}]+)\}/', '$1/$2', (string) $clean);
        $clean = str_replace(
            ['\\times', '\\cdot', '\\pm', '\\neq', '\\leq', '\\geq', '\\le', '\\ge'],
            ['×', '·', '±', '≠', '≤', '≥', '≤', '≥'],
            (string) $clean
        );

        return preg_replace('/\^\{(\d+)\}/', '^$1', $clean) ?? $clean;
    }

    /**
     * Memformat teks matematika menjadi HTML bersih tanpa menampilkan tanda dolar LaTeX mentah.
     */
    public static function formatMathHtml(string $text): string
    {
        $escaped = e(self::stripLatexMarkers($text));
        $formatted = preg_replace('/([a-zA-Z])\^(\d+)/', '<var class="math-var">$1</var><sup>$2</sup>', $escaped);
        $formatted = preg_replace('/(?<=\d|\(|\s|^)x(?=\s|\)|=|,|\.|;|:|\?|²|$)/', '<var class="math-var">x</var>', (string) $formatted);
        $formatted = str_replace(' - ', ' &minus; ', (string) $formatted);

        return preg_replace('/(?<=[\s(,=]|^)-(\d+)/', '&minus;$1', $formatted) ?? $formatted;
    }
}
