<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedProduct(1, 'rankmath', 'Rank Math');
        $this->seedProduct(2, 'wp-rocket', 'WP Rocket');
        $this->seedProduct(1, 'content-ai', 'Content AI');
    }

    private function seedProduct(int $brandId, string $code, string $name): void
    {
        $slugName = Str::slug($name, '_');
        $lowerSlugName = Str::lower($slugName);
        $upperSlugName = Str::upper($slugName);

        $plainToken = 'prd_' . $lowerSlugName . '_' . Str::random(32);
        $hash = hash('sha256', $plainToken);

        DB::table('products')->insert([
            'brand_id' => $brandId,
            'code' => $code,
            'name' => $name,
            'product_token_hash' => $hash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command?->info("=== PRODUCT: {$name} ({$code}) ===");
        $this->command?->warn("API KEY: {$plainToken}");
        $this->command?->line("Save this in your .env as: {$upperSlugName}_PRD_TOKEN={$plainToken}");
        $this->command?->line(str_repeat('-', 50));
    }
}
