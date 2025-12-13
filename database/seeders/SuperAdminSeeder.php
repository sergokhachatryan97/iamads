<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if super_admin role exists
        $superAdminRole = Role::firstWhere('name', 'super_admin');
        
        if (!$superAdminRole) {
            $this->command->warn('super_admin role does not exist. Please run RolesAndPermissionsSeeder first.');
            return;
        }

        // Check if a super_admin user already exists
        $existingSuperAdmin = User::role('super_admin')->first();
        
        if ($existingSuperAdmin) {
            $this->command->info('Super admin user already exists: ' . $existingSuperAdmin->email);
            return;
        }

        // Create the first super_admin user
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'), // Using bcrypt via Hash::make()
            'email_verified_at' => now(),
        ]);

        // Assign super_admin role
        $superAdmin->assignRole('super_admin');

        $this->command->info('Super admin user created successfully!');
        $this->command->info('Email: superadmin@example.com');
        $this->command->info('Password: password');
        $this->command->warn('Please change the password after first login!');
    }
}
