<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Accounts\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class SuperadminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = (string) config('auth.superadmin.email', 'superadmin@getnow.test');
        $password = (string) config('auth.superadmin.password', 'secret');

        $user = User::where('email', $email)->first();

        if ($user === null) {
            User::create([
                'public_id' => (string) Str::ulid(),
                'name' => 'Superadmin',
                'email' => $email,
                'password' => $password,
                'role' => UserRole::Superadmin,
                'email_verified_at' => now(),
            ]);

            return;
        }

        $user->update([
            'name' => 'Superadmin',
            'role' => UserRole::Superadmin,
        ]);
    }
}
