<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create Admin account
        User::create([
            'name'     => 'Admin',
            'email'    => 'admin@cojan.com',
            'password' => Hash::make('admin123'),
            'role'     => 'admin',
            'phone'    => '09000000000',
            'address'  => 'Cojan Catering Services, Baguio City',
        ]);

        // Create sample Customer account
        User::create([
            'name'     => 'John Customer',
            'email'    => 'customer@cojan.com',
            'password' => Hash::make('customer123'),
            'role'     => 'customer',
            'phone'    => '09111111111',
            'address'  => 'Sample Address, Baguio City',
        ]);

        // Create sample Delivery Personnel account
        User::create([
            'name'     => 'Juan Delivery',
            'email'    => 'delivery@cojan.com',
            'password' => Hash::make('delivery123'),
            'role'     => 'delivery',
            'phone'    => '09222222222',
            'address'  => 'Sample Address, Baguio City',
        ]);
    }
}