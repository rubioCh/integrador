<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::query()->updateOrCreate(
            ['email' => 'carlos91rubio@gmail.com'],
            [
                'username' => 'charly91rubio',
                'first_name' => 'Carlos',
                'last_name' => 'Rubio',
                'name' => 'Carlos Rubio',
                'password' => Hash::make('ch_rubio2026'),
            ]
        );

        $role = Role::query()->firstOrCreate(
            ['slug' => 'superadmin'],
            [
                'name' => 'Super Admin',
                'description' => 'Top-level role with global bypass permissions',
            ]
        );

        $superAdmin->roles()->syncWithoutDetaching([$role->id]);
    }
}
