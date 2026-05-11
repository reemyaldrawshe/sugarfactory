<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            SectionSeeder::class,
            UnitSeeder::class,
            ItemSeeder::class,
             ShipmentSeeder::class,
             BOMSeeder::class,
             ProductionOrderSeeder::class,
            
        ]);
    }
}
