<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class GameContentSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('game:import');
        $this->command->getOutput()->write(Artisan::output());
    }
}
