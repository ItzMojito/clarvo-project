<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(QuadraticCurriculumSeeder::class);

        // Akun demo memakai kata sandi yang tertulis di kode, jadi jangan dibuat di server publik.
        if (! app()->isProduction()) {
            $this->call(ClassDemoSeeder::class);
        }
    }
}
