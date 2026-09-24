<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Fatia 1 semeia só o fundador. O painel abre vazio de propósito:
        // agência, cliente e visita são das fatias seguintes.
        $this->call(FounderSeeder::class);
    }
}
