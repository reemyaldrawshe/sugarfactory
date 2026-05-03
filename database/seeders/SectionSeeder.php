<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Section::query()->create([
            'ar_name' => 'مواد خام',
            'en_name' => 'Raw Materials',
        ]);
        Section::query()->create([
            'ar_name' => 'تغليف',
            'en_name' => 'Packaging',
        ]);
        Section::query()->create([
            'ar_name' => 'منتج نهائي',
            'en_name' => 'Finished Product',
        ]);
    }
}
