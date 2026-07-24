<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeSuperadmin extends Command
{
    protected $signature = 'game:superadmin {email} {--password=} {--name=Dewa Pencipta} {--job=Dewa Pencipta}';

    protected $description = 'Buat akun baru atau jadikan user sebagai superadmin (Dewa Pencipta).';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->option('password');

        $user = User::where('email', $email)->first();

        if (! $user) {
            if (! $password) {
                $this->error('User belum ada — sertakan --password untuk membuat akun baru.');

                return self::FAILURE;
            }
            $user = new User;
            $user->email = $email;
            $user->name = $this->option('name');
            $user->password = $password; // di-hash otomatis oleh cast
            $user->email_verified_at = now();
        } elseif ($password) {
            $user->password = $password;
        }

        $user->role = 'superadmin';
        $user->job = $this->option('job');
        $user->save();

        $this->info("Superadmin siap: {$user->email} — job: {$user->job}");

        return self::SUCCESS;
    }
}
