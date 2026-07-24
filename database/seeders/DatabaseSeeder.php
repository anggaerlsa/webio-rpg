<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Akun demo sengaja GENERIK (domain .test) — jangan pernah memasukkan email
     * atau sandi asli ke dalam seeder. Ganti sandi sesudah deploy.
     */
    public function run(): void
    {
        // Import game content (quests, nodes, monsters, items, skills, spells) from JSON files.
        $this->call(GameContentSeeder::class);

        // Dewa (superadmin) — akses Panel Dewa di /admin.
        // Catatan: `role` & `email_verified_at` tidak mass-assignable, jadi diset eksplisit.
        $admin = User::firstOrCreate(
            ['email' => 'admin@webio.test'],
            ['name' => 'Admin Webio', 'password' => 'admin123', 'job' => 'Dewa Pencipta'],
        );
        $admin->role = 'superadmin';
        $admin->email_verified_at ??= now();
        $admin->save();

        // Dua akun pemain demo (dua akun memudahkan uji chat & pertemanan).
        foreach ([1, 2] as $n) {
            $player = User::firstOrCreate(
                ['email' => "player{$n}@webio.test"],
                ['name' => "Pemain {$n}", 'password' => 'player123'],
            );
            $player->email_verified_at ??= now();
            $player->save();
        }
    }
}
