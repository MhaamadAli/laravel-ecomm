<?php

/**
 * User Seeder - Creates initial admin and test users
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/database/seeders/UserSeeder.php
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@ecommerce.local',
            'password' => Hash::make('password'),
            'phone' => '+1234567890',
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
        ]);

        // Create test regular user
        User::create([
            'name' => 'Test User',
            'email' => 'user@ecommerce.local',
            'password' => Hash::make('password'),
            'phone' => '+1987654321',
            'role' => User::ROLE_USER,
            'email_verified_at' => now(),
        ]);

        // Create additional admin users
        User::factory(2)->create([
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
        ]);

        // Create verified regular users
        User::factory(20)->create([
            'role' => User::ROLE_USER,
            'email_verified_at' => now(),
        ]);

        // Create some unverified users
        User::factory(5)->create([
            'role' => User::ROLE_USER,
            'email_verified_at' => null,
        ]);

        $this->command->info('Users seeded successfully!');
    }
}