<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedBrand('RankMath', 'standard');
        $this->seedBrand('WP Rocket', 'standard');
        $this->seedBrand('Ecosystem Admin', 'ecosystem_admin');
    }

    private function seedBrand(string $name, string $role): void
    {

        $slugName = Str::slug($name, '_');
        $lowerSlugName = Str::lower($slugName);
        $upperSlugName = Str::upper($slugName);

        $plainToken = 'br_' . $lowerSlugName . '_secret_token';

        $hash = hash('sha256', $plainToken);

        DB::table('brands')->insert([
            'name' => $name,
            'role' => $role,
            'api_key_hash' => $hash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command?->info("=== BRAND: {$name} ===");
        $this->command?->line("API KEY: {$plainToken}");
        $this->command?->warn("Save this in your .env as: {$upperSlugName}_BR_TOKEN={$plainToken}");
        $this->command?->line(str_repeat('-', 50));
    }
}
