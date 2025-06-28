<?php

/**
 * Database Seeder - Seeds the database with initial data
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/database/seeders/DatabaseSeeder.php
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('Starting database seeding...');
        
        // Call individual seeders in order
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            OrderSeeder::class,
            ReviewSeeder::class,
            CartWishlistSeeder::class,
        ]);
        
        $this->command->info('Database seeding completed successfully!');
    }
}