<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('sms.admin_email');
        $password = config('sms.admin_password');

        if (! $email || ! $password) {
            $this->command->error('Set SMS_ADMIN_EMAIL and SMS_ADMIN_PASSWORD in .env first.');

            return;
        }

        if (strlen($password) < 14) {
            $this->command->error('SMS_ADMIN_PASSWORD must be at least 14 characters.');

            return;
        }

        // forceFill: role and is_active are not mass-assignable on purpose
        $admin = User::firstOrNew(['email' => $email]);
        $admin->forceFill([
            'name' => config('sms.admin_name'),
            'password' => Hash::make($password),
            'role' => Role::Admin->value,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        $this->command->info("Admin account ready for {$email}.");
    }
}
