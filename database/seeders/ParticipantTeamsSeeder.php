<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ParticipantTeamsSeeder extends Seeder
{
    private const TEAM_COUNT = 10;

    private const MIN_TOTAL_PLAYERS_PER_TEAM = 14;

    private const MAX_TOTAL_PLAYERS_PER_TEAM = 17;

    private const MIN_STANDARD_MEMBERS = 12;

    private const MAX_STANDARD_MEMBERS = 15;

    /**
     * Seed the configured participant teams from the participant list.
     */
    public function run(): void
    {
        $participants = collect($this->participants())->values();
        $teamProfiles = $this->teamProfiles();

        if (count($teamProfiles) !== self::TEAM_COUNT) {
            throw new InvalidArgumentException(sprintf(
                'ParticipantTeamsSeeder expects exactly %d team profiles.',
                self::TEAM_COUNT,
            ));
        }

        $participantCount = $participants->count();
        $minimumParticipants = self::TEAM_COUNT * self::MIN_TOTAL_PLAYERS_PER_TEAM;
        $maximumParticipants = self::TEAM_COUNT * self::MAX_TOTAL_PLAYERS_PER_TEAM;

        if ($participantCount < $minimumParticipants || $participantCount > $maximumParticipants) {
            throw new InvalidArgumentException(sprintf(
                'ParticipantTeamsSeeder expects between %d and %d participants, %d given.',
                $minimumParticipants,
                $maximumParticipants,
                $participantCount,
            ));
        }

        $teamSizes = $this->teamSizes($participantCount);
        $password = Hash::make('password');
        $offset = 0;

        DB::transaction(function () use ($offset, $participants, $teamProfiles, $teamSizes, $password): void {
            $cursor = $offset;

            foreach ($teamProfiles as $index => $profile) {
                $roster = $participants->slice($cursor, $teamSizes[$index])->values();
                $cursor += $teamSizes[$index];

                $captain = $roster->get(0);
                $spiritCaptain = $roster->get(1);

                $captainUser = User::query()->updateOrCreate(
                    ['email' => sprintf('%s@distrack.test', Str::slug($profile['name']).'-captain')],
                    [
                        'name' => $captain['name'],
                        'role' => User::ROLE_CAPTAIN,
                        'email_verified_at' => now(),
                        'password' => $password,
                    ],
                );

                $team = Team::query()->updateOrCreate(
                    ['name' => $profile['name']],
                    [
                        'owner_user_id' => $captainUser->id,
                        'address' => $profile['address'],
                        'city' => $profile['city'],
                        'province' => $profile['province'],
                        'country_name' => $profile['country_name'],
                        'status' => 'active',
                    ],
                );

                $team->members()->delete();

                $this->createMember($team, $captain, 'captain', $captainUser->id);
                $this->createMember($team, $spiritCaptain, 'spirit_captain');

                foreach ($roster->slice(2)->values() as $member) {
                    $this->createMember($team, $member, 'member');
                }
            }
        });
    }

    /**
     * @return list<array{name: string, nickname: ?string, gender: string, age: int, address: string}>
     */
    protected function participants(): array
    {
        $participants = require database_path('seeders/data/team_participants.php');

        return collect($participants)
            ->values()
            ->map(function (array|string $participant, int $index): array {
                if (is_string($participant)) {
                    return [
                        'name' => $participant,
                        'nickname' => Str::of($participant)->before(' ')->limit(20, '')->toString(),
                        'gender' => $index % 2 === 0 ? 'Male' : 'Female',
                        'age' => 18 + ($index % 13),
                        'address' => 'Philippines',
                    ];
                }

                $name = trim((string) ($participant['name'] ?? ''));

                if ($name === '') {
                    throw new InvalidArgumentException('Every participant entry must include a non-empty name.');
                }

                return [
                    'name' => $name,
                    'nickname' => $participant['nickname'] ?? Str::of($name)->before(' ')->limit(20, '')->toString(),
                    'gender' => $participant['gender'] ?? ($index % 2 === 0 ? 'Male' : 'Female'),
                    'age' => (int) ($participant['age'] ?? 18 + ($index % 13)),
                    'address' => $participant['address'] ?? 'Philippines',
                ];
            })
            ->all();
    }

    /**
     * @return list<array{name: string, address: string, city: string, province: string, country_name: string}>
     */
    protected function teamProfiles(): array
    {
        return [
            ['name' => 'Seeded Northstars', 'address' => 'Lahug', 'city' => 'Cebu City', 'province' => 'Cebu', 'country_name' => 'Philippines'],
            ['name' => 'Seeded Skybreakers', 'address' => 'Bagumbayan', 'city' => 'Quezon City', 'province' => 'Metro Manila (NCR)', 'country_name' => 'Philippines'],
            ['name' => 'Seeded Riptide', 'address' => 'Poblacion', 'city' => 'Davao City', 'province' => 'Davao del Sur', 'country_name' => 'Philippines'],
            ['name' => 'Seeded Emberhawks', 'address' => 'Mandurriao', 'city' => 'Iloilo City', 'province' => 'Iloilo', 'country_name' => 'Philippines'],
            ['name' => 'Seeded Stonewind', 'address' => 'Carmen', 'city' => 'Cagayan de Oro', 'province' => 'Misamis Oriental', 'country_name' => 'Philippines'],
            ['name' => 'Seeded Tidebound', 'address' => 'Lagao', 'city' => 'General Santos City', 'province' => 'South Cotabato', 'country_name' => 'Philippines'],
            ['name' => 'Seeded Voltstream', 'address' => 'Punta Princesa', 'city' => 'Cebu City', 'province' => 'Cebu', 'country_name' => 'Philippines'],
            ['name' => 'Seeded Daybreak', 'address' => 'San Miguel', 'city' => 'Iligan City', 'province' => 'Lanao del Norte', 'country_name' => 'Philippines'],
            ['name' => 'Seeded Ironwood', 'address' => 'Poblacion', 'city' => 'Valencia City', 'province' => 'Bukidnon', 'country_name' => 'Philippines'],
            ['name' => 'Seeded Stormcallers', 'address' => 'Santo Nino', 'city' => 'Cagayan de Oro', 'province' => 'Misamis Oriental', 'country_name' => 'Philippines'],
        ];
    }

    /**
     * @return list<int>
     */
    protected function teamSizes(int $participantCount): array
    {
        $sizes = array_fill(0, self::TEAM_COUNT, self::MIN_TOTAL_PLAYERS_PER_TEAM);
        $extraParticipants = $participantCount - array_sum($sizes);

        while ($extraParticipants > 0) {
            foreach ($sizes as $index => $size) {
                if ($extraParticipants === 0) {
                    break;
                }

                if ($size >= self::MAX_TOTAL_PLAYERS_PER_TEAM) {
                    continue;
                }

                $sizes[$index]++;
                $extraParticipants--;
            }
        }

        foreach ($sizes as $size) {
            $memberCount = $size - 2;

            if ($memberCount < self::MIN_STANDARD_MEMBERS || $memberCount > self::MAX_STANDARD_MEMBERS) {
                throw new InvalidArgumentException('Calculated team sizes violate the required roster structure.');
            }
        }

        return $sizes;
    }

    /**
     * @param  array{name: string, nickname: ?string, gender: string, age: int, address: string}  $participant
     */
    protected function createMember(Team $team, array $participant, string $role, ?int $userId = null): TeamMember
    {
        return TeamMember::query()->create([
            'team_id' => $team->id,
            'user_id' => $userId,
            'name' => $participant['name'],
            'nickname' => $participant['nickname'],
            'gender' => $participant['gender'],
            'age' => $participant['age'],
            'address' => $participant['address'],
            'role' => $role,
        ]);
    }
}
// diri sa http://distrack.test/admin/tournaments?tournament=2&tab=round-robin

// include diri sa pitches like mag add ug pitch ayha dayon mag round robin