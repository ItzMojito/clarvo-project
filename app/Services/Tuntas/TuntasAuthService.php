<?php

namespace App\Services\Tuntas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TuntasAuthService
{
    private const SESSION_KEY = 'tuntas_auth';
    public const WELCOME_SESSION_KEY = 'clarvo_just_logged_in';

    public function current(): ?array
    {
        $auth = session(self::SESSION_KEY);
        return is_array($auth) ? $auth : null;
    }

    public function ensureWorkspaceAuth(): array
    {
        $current = $this->current();
        if ($current) {
            return $current;
        }

        // Pengunjung langsung ke /workspace otomatis masuk Mode Individual Tamu (100% pribadi)
        return $this->enterGuestIndividual();
    }

    public function logout(): void
    {
        session()->forget([self::SESSION_KEY, self::WELCOME_SESSION_KEY]);
    }

    private function establishAuthSession(array $auth): void
    {
        session([
            self::SESSION_KEY         => $auth,
            self::WELCOME_SESSION_KEY => true,
        ]);
    }

    public function registerStudent(string $classCode, string $learnerCode, ?string $pin = null): array
    {
        $code = $this->normalizeCode($learnerCode);
        $class = $this->normalizeClassName($classCode);

        if ($code === '' || strlen($code) > 20 || !preg_match('/^[A-Z0-9_-]+$/', $code)) {
            return ['ok' => false, 'error' => 'Kode Pelajar wajib berupa huruf/angka (misal S06), maksimal 20 karakter.'];
        }

        if ($class === '') {
            return ['ok' => false, 'error' => 'Kode Kelas wajib diisi (misal 10A atau X-2).'];
        }

        $exists = DB::table('learners')->where('code', $code)->first();
        if ($exists) {
            return ['ok' => false, 'error' => "Kode Pelajar {$code} sudah terdaftar. Silakan masuk atau gunakan kode lain."];
        }

        $pinTrim = trim((string) $pin);
        $learnerId = DB::table('learners')->insertGetId([
            'code'       => $code,
            'class_name' => $class,
            'is_private' => false,
            'pin_hash'   => $pinTrim !== '' ? Hash::make($pinTrim) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auth = [
            'role'       => 'student',
            'learner_id' => $learnerId,
            'code'       => $code,
            'class_name' => $class,
            'name'       => null,
            'is_private' => false,
        ];
        $this->establishAuthSession($auth);

        return ['ok' => true, 'auth' => $auth];
    }

    public function loginStudent(string $classCode, string $learnerCode, ?string $pin = null): array
    {
        $code = $this->normalizeCode($learnerCode);
        $classInput = mb_strtolower(trim($classCode));

        if ($code === '' || $classInput === '') {
            return ['ok' => false, 'error' => 'Masukkan Kode Kelas dan Kode Pelajar anonimmu.'];
        }

        $learner = DB::table('learners')
            ->where('code', $code)
            ->where('is_private', false)
            ->first();

        if (!$learner || !$this->classMatches($learner->class_name, $classInput)) {
            return ['ok' => false, 'error' => 'Kombinasi Kode Kelas dan Kode Pelajar tidak ditemukan.'];
        }

        if ($learner->pin_hash && !Hash::check(trim((string) $pin), $learner->pin_hash)) {
            return ['ok' => false, 'error' => 'PIN akses tidak sesuai untuk kode pelajar ini.'];
        }

        $auth = [
            'role'       => 'student',
            'learner_id' => (int) $learner->id,
            'code'       => $learner->code,
            'class_name' => $learner->class_name,
            'name'       => null,
            'is_private' => false,
        ];
        $this->establishAuthSession($auth);

        return ['ok' => true, 'auth' => $auth];
    }

    public function registerTeacher(string $name, string $email, string $classCode, string $password): array
    {
        $cleanName = trim($name);
        $cleanEmail = mb_strtolower(trim($email));
        $class = $this->normalizeClassName($classCode);

        if ($cleanName === '' || !filter_var($cleanEmail, FILTER_VALIDATE_EMAIL) || $class === '') {
            return ['ok' => false, 'error' => 'Lengkapi nama, email valid, dan kode kelas yang diajar.'];
        }

        if (strlen($password) < 6) {
            return ['ok' => false, 'error' => 'Kata sandi minimal 6 karakter.'];
        }

        if (DB::table('users')->where('email', $cleanEmail)->exists()) {
            return ['ok' => false, 'error' => 'Email tersebut sudah terdaftar. Silakan masuk ke akunmu.'];
        }

        $auth = DB::transaction(function () use ($cleanName, $cleanEmail, $class, $password) {
            $simCode = 'SIM-' . strtoupper(Str::random(6));
            $simLearnerId = DB::table('learners')->insertGetId([
                'code'       => $simCode,
                'class_name' => $class,
                'is_private' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $userId = DB::table('users')->insertGetId([
                'name'       => $cleanName,
                'email'      => $cleanEmail,
                'password'   => Hash::make($password),
                'role'       => 'teacher',
                'class_name' => $class,
                'learner_id' => $simLearnerId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'role'       => 'teacher',
                'user_id'    => $userId,
                'learner_id' => $simLearnerId,
                'code'       => $simCode,
                'class_name' => $class,
                'name'       => $cleanName,
                'is_private' => true,
            ];
        });

        $this->establishAuthSession($auth);
        return ['ok' => true, 'auth' => $auth];
    }

    public function loginTeacher(string $email, string $password): array
    {
        $cleanEmail = mb_strtolower(trim($email));
        $user = DB::table('users')
            ->where('email', $cleanEmail)
            ->where('role', 'teacher')
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return ['ok' => false, 'error' => 'Email atau kata sandi Guru tidak valid.'];
        }

        $simLearnerId = $user->learner_id ?: $this->ensurePrivateLearnerForUser($user->id, 'SIM', $user->class_name ?? 'Class X-2');
        $code = DB::table('learners')->where('id', $simLearnerId)->value('code') ?? ('SIM-' . $user->id);

        $auth = [
            'role'       => 'teacher',
            'user_id'    => (int) $user->id,
            'learner_id' => (int) $simLearnerId,
            'code'       => $code,
            'class_name' => $user->class_name ?? 'Class X-2',
            'name'       => $user->name,
            'is_private' => true,
        ];
        $this->establishAuthSession($auth);

        return ['ok' => true, 'auth' => $auth];
    }

    public function registerIndividual(string $name, string $email, string $password): array
    {
        $cleanName = trim($name);
        $cleanEmail = mb_strtolower(trim($email));

        if ($cleanName === '' || !filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Masukkan nama panggilan dan email yang valid.'];
        }

        if (strlen($password) < 6) {
            return ['ok' => false, 'error' => 'Kata sandi minimal 6 karakter.'];
        }

        if (DB::table('users')->where('email', $cleanEmail)->exists()) {
            return ['ok' => false, 'error' => 'Email sudah terdaftar. Silakan masuk melalui tab Masuk.'];
        }

        $auth = DB::transaction(function () use ($cleanName, $cleanEmail, $password) {
            $privCode = 'IND-' . strtoupper(Str::random(6));
            $learnerId = DB::table('learners')->insertGetId([
                'code'       => $privCode,
                'class_name' => 'Mandiri',
                'is_private' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $userId = DB::table('users')->insertGetId([
                'name'       => $cleanName,
                'email'      => $cleanEmail,
                'password'   => Hash::make($password),
                'role'       => 'individual',
                'class_name' => null,
                'learner_id' => $learnerId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'role'       => 'individual',
                'user_id'    => $userId,
                'learner_id' => $learnerId,
                'code'       => $privCode,
                'class_name' => 'Mandiri',
                'name'       => $cleanName,
                'is_private' => true,
            ];
        });

        $this->establishAuthSession($auth);
        return ['ok' => true, 'auth' => $auth];
    }

    public function loginIndividual(string $email, string $password): array
    {
        $cleanEmail = mb_strtolower(trim($email));
        $user = DB::table('users')
            ->where('email', $cleanEmail)
            ->where('role', 'individual')
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return ['ok' => false, 'error' => 'Email atau kata sandi akun Individual tidak sesuai.'];
        }

        $learnerId = $user->learner_id ?: $this->ensurePrivateLearnerForUser($user->id, 'IND', 'Mandiri');
        $code = DB::table('learners')->where('id', $learnerId)->value('code') ?? ('IND-' . $user->id);

        $auth = [
            'role'       => 'individual',
            'user_id'    => (int) $user->id,
            'learner_id' => (int) $learnerId,
            'code'       => $code,
            'class_name' => 'Mandiri',
            'name'       => $user->name,
            'is_private' => true,
        ];
        $this->establishAuthSession($auth);

        return ['ok' => true, 'auth' => $auth];
    }

    public function enterGuestIndividual(string $name = 'Tamu'): array
    {
        $privCode = 'IND-' . strtoupper(Str::random(6));
        $learnerId = DB::table('learners')->insertGetId([
            'code'       => $privCode,
            'class_name' => 'Mandiri',
            'is_private' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auth = [
            'role'       => 'individual',
            'user_id'    => null,
            'learner_id' => $learnerId,
            'code'       => $privCode,
            'class_name' => 'Mandiri',
            'name'       => $name,
            'is_private' => true,
        ];
        $this->establishAuthSession($auth);

        return $auth;
    }

    private function ensurePrivateLearnerForUser(int $userId, string $prefix, string $className): int
    {
        $code = $prefix . '-' . strtoupper(Str::random(6));
        $learnerId = DB::table('learners')->insertGetId([
            'code'       => $code,
            'class_name' => $className,
            'is_private' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('users')->where('id', $userId)->update(['learner_id' => $learnerId, 'updated_at' => now()]);
        return $learnerId;
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    private function normalizeClassName(string $raw): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return '';
        }
        return preg_match('/^class\s+/i', $trimmed) ? $trimmed : "Class {$trimmed}";
    }

    private function classMatches(string $storedClass, string $inputLower): bool
    {
        $storedLower = mb_strtolower(trim($storedClass));
        if ($storedLower === $inputLower) {
            return true;
        }
        $strip = static fn (string $s) => trim(preg_replace('/^(class|kelas)\s+/i', '', $s));
        return $strip($storedLower) === $strip($inputLower);
    }
}
