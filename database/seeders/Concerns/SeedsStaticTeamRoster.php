<?php

namespace Database\Seeders\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

trait SeedsStaticTeamRoster
{
    /**
     * Persist a team row plus roster rows using the real `teams` / `team_members` schema.
     *
     * Registration tier from your lists is stored in `team_members.nickname` (there is no `status` column on members).
     * Display roles (Captain, Spirit Captain, Player) are mapped to `captain`, `spirit_captain`, and `member`.
     *
     * @param  list<array{name: string, gender: string, role: string, status?: string, registration?: string}>  $members
     */
    protected function seedTeamWithRoster(string $teamName, array $members): void
    {
        $ownerUserId = User::query()->where('email', 'captain@distrack.test')->value('id');

        if ($ownerUserId === null) {
            throw new RuntimeException('Run UserAccountSeeder before team roster seeders (captain@distrack.test is missing).');
        }

        $now = now();

        $teamId = DB::table('teams')->insertGetId([
            'owner_user_id' => $ownerUserId,
            'name' => $teamName,
            'address' => 'Philippines',
            'city' => null,
            'province' => null,
            'country_name' => 'Philippines',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($members as $member) {
            $registration = $member['registration'] ?? $member['status'] ?? null;
            $roleLabel = $member['role'] ?? 'Player';
            $dbRole = $this->mapDisplayRoleToDbRole($roleLabel);

            DB::table('team_members')->insert([
                'team_id' => $teamId,
                'user_id' => $dbRole === 'captain' ? $ownerUserId : null,
                'name' => $member['name'],
                'nickname' => $registration,
                'gender' => $member['gender'],
                'age' => null,
                'address' => null,
                'role' => $dbRole,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    protected function mapDisplayRoleToDbRole(string $label): string
    {
        $key = strtolower(trim($label));

        return match ($key) {
            'captain' => 'captain',
            'spirit captain' => 'spirit_captain',
            'player', 'member' => 'member',
            default => 'member',
        };
    }
}
