<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        User::create([
            'last_name'  => 'Admin',
            'first_name' => 'Super',
            'telephone'      => '90145234',
            'email'      => 'admin@gmail.com',
            'country'      => 'TOGO',
            'city'      => 'Lomé',
            'password'   => Hash::make('password'),
            'role'    => 'admin',
            
        ]);
    }
}
