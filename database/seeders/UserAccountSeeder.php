<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserAccountSeeder extends Seeder
{
    /**
     * Seed one user for each supported platform account type.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        User::query()
            ->where('role', 'player')
            ->update(['role' => 'captain']);

        if (
            User::query()->where('email', 'player@distrack.test')->exists()
            && ! User::query()->where('email', 'captain@distrack.test')->exists()
        ) {
            User::query()
                ->where('email', 'player@distrack.test')
                ->update([
                    'name' => 'DISCTRACK Captain',
                    'email' => 'captain@distrack.test',
                    'role' => 'captain',
                    'email_verified_at' => now(),
                    'password' => $password,
                ]);
        }

        foreach ($this->accounts() as $account) {
            User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'role' => $account['role'],
                    'email_verified_at' => now(),
                    'password' => $password,
                ],
            );
        }
    }

    /**
     * Default seeded platform accounts by user role.
     *
     * @return list<array{name: string, email: string, role: string}>
     */
    protected function accounts(): array
    {
        return [
            [
                'name' => 'DISCTRACK Admin',
                'email' => 'admin@distrack.test',
                'role' => 'admin',
                'password' => 'password',
            ],
            [
                'name' => 'DISCTRACK Scorekeeper',
                'email' => 'scorekeeper@distrack.test',
                'role' => 'scorekeeper',
            ],
            [
                'name' => 'DISCTRACK Captain',
                'email' => 'captain@distrack.test',
                'role' => 'captain',
            ],
        ];
    }
}
