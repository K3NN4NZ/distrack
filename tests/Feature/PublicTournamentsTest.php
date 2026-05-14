<?php

use App\Models\MatchPlayerStat;
use App\Models\MatchScoreLog;
use App\Models\Pitch;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentCrew;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use App\Models\User;

test('public tournament board shows only public tournaments and applies filters', function () {
    $organizer = User::factory()->admin()->create([
        'name' => 'Laguna Disc Committee',
    ]);

    Tournament::query()->create([
        'created_by' => $organizer->id,
        'name' => 'Laguna Sky Clash',
        'slug' => 'laguna-sky-clash',
        'venue' => 'Greenfield Sports Field',
        'description' => 'Flagship public event',
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addDay(),
        'status' => 'registration',
        'city' => 'Santa Rosa',
        'country_name' => 'Philippines',
        'timezone' => 'Asia/Manila',
        'event_type' => 'Tournament',
        'division' => 'Mix',
        'surface' => 'Outdoor',
        'is_public' => true,
    ]);

    Tournament::query()->create([
        'name' => 'Seoul Indoor League',
        'slug' => 'seoul-indoor-league',
        'venue' => 'Gyeongju Sports Hall',
        'description' => 'Indoor league circuit',
        'starts_at' => now()->addWeeks(2),
        'ends_at' => now()->addWeeks(2)->addDay(),
        'status' => 'registration',
        'city' => 'Seoul',
        'country_name' => 'South Korea',
        'timezone' => 'Asia/Seoul',
        'event_type' => 'League',
        'division' => 'Women',
        'surface' => 'Indoor',
        'is_public' => true,
    ]);

    Tournament::query()->create([
        'name' => 'Private Scrimmage',
        'slug' => 'private-scrimmage',
        'venue' => 'Hidden Field',
        'status' => 'draft',
        'is_public' => false,
    ]);

    $response = $this->get(route('tournaments.index', [
        'country' => 'Philippines',
        'division' => 'Mix',
    ]));

    $response
        ->assertOk()
        ->assertSee('Calendar View')
        ->assertSee('Select Year and Month')
        ->assertSee('All Countries')
        ->assertSee('Laguna Sky Clash')
        ->assertSee('Laguna Disc Committee')
        ->assertDontSee('Seoul Indoor League')
        ->assertDontSee('Private Scrimmage');
});

test('public tournament board filters by unified type and month', function () {
    $organizer = User::factory()->admin()->create([
        'name' => 'Filter Curator',
    ]);

    $selectedMonth = now()->addMonths(2)->startOfMonth()->setTime(9, 0);

    Tournament::query()->create([
        'created_by' => $organizer->id,
        'name' => 'Indoor Mix Masters',
        'slug' => 'indoor-mix-masters',
        'venue' => 'North Hall',
        'starts_at' => $selectedMonth->copy()->addDays(4),
        'ends_at' => $selectedMonth->copy()->addDays(5),
        'status' => 'registration',
        'country_name' => 'Philippines',
        'timezone' => 'Asia/Manila',
        'event_type' => 'Tournament',
        'division' => 'Mix',
        'surface' => 'Indoor',
        'is_public' => true,
    ]);

    Tournament::query()->create([
        'created_by' => $organizer->id,
        'name' => 'Outdoor Women Cup',
        'slug' => 'outdoor-women-cup',
        'venue' => 'South Field',
        'starts_at' => $selectedMonth->copy()->addMonth(),
        'ends_at' => $selectedMonth->copy()->addMonth()->addDay(),
        'status' => 'registration',
        'country_name' => 'Philippines',
        'timezone' => 'Asia/Manila',
        'event_type' => 'Tournament',
        'division' => 'Women',
        'surface' => 'Outdoor',
        'is_public' => true,
    ]);

    $this->get(route('tournaments.index', [
        'type' => 'Indoor',
        'month' => $selectedMonth->format('Y-m'),
    ]))
        ->assertOk()
        ->assertSee('Indoor')
        ->assertSee($selectedMonth->format('F Y'))
        ->assertSee('Indoor Mix Masters')
        ->assertDontSee('Outdoor Women Cup');
});

test('public tournament board can render a calendar view for the selected day', function () {
    $organizer = User::factory()->admin()->create([
        'name' => 'Calendar Curator',
    ]);

    $selectedDate = now()->addMonth()->startOfMonth()->addDays(6)->setTime(9, 0);

    Tournament::query()->create([
        'created_by' => $organizer->id,
        'name' => 'Albert Park League Season 2, 2026',
        'slug' => 'albert-park-league-season-2-2026',
        'venue' => 'Albert Park',
        'starts_at' => $selectedDate->copy(),
        'ends_at' => $selectedDate->copy()->addDays(2),
        'status' => 'live',
        'country_name' => 'Australia',
        'city' => 'Melbourne',
        'timezone' => 'Australia/Melbourne',
        'event_type' => 'League',
        'division' => 'Mix',
        'surface' => 'Outdoor',
        'is_public' => true,
    ]);

    Tournament::query()->create([
        'created_by' => $organizer->id,
        'name' => '2026 Kenya Ladies Ultimate League',
        'slug' => '2026-kenya-ladies-ultimate-league',
        'venue' => 'Nairobi Grounds',
        'starts_at' => $selectedDate->copy(),
        'ends_at' => $selectedDate->copy()->addDays(1),
        'status' => 'registration',
        'country_name' => 'Kenya',
        'city' => 'Nairobi',
        'timezone' => 'Africa/Nairobi',
        'event_type' => 'League',
        'division' => 'Women',
        'surface' => 'Outdoor',
        'is_public' => true,
    ]);

    Tournament::query()->create([
        'created_by' => $organizer->id,
        'name' => 'Frisbeginners 5.0',
        'slug' => 'frisbeginners-5-0',
        'venue' => 'Pasig Meadows',
        'starts_at' => $selectedDate->copy()->addDays(3),
        'ends_at' => $selectedDate->copy()->addDays(4),
        'status' => 'registration',
        'country_name' => 'Philippines',
        'city' => 'Pasig',
        'timezone' => 'Asia/Manila',
        'event_type' => 'Tournament',
        'division' => 'Mix',
        'surface' => 'Outdoor',
        'is_public' => true,
    ]);

    $this->get(route('tournaments.index', [
        'view' => 'calendar',
        'month' => $selectedDate->format('Y-m'),
        'day' => $selectedDate->format('Y-m-d'),
    ]))
        ->assertOk()
        ->assertSee('Event Calendar')
        ->assertSee('List View')
        ->assertSee(strtoupper($selectedDate->format('j F Y')))
        ->assertSee('2 events')
        ->assertSee('sorted by country')
        ->assertSeeInOrder([
            'Albert Park League Season 2, 2026',
            '2026 Kenya Ladies Ultimate League',
        ])
        ->assertSee('wire:navigate.preserve-scroll', false);
});

test('public tournament detail tabs show tournament metadata and linked resources', function () {
    $organizer = User::factory()->admin()->create([
        'name' => 'Tournament Admin',
    ]);

    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $organizer->id,
        'name' => 'Public Finals Weekend',
        'slug' => 'public-finals-weekend',
        'venue' => 'Central Field',
        'description' => 'Championship weekend with public schedule data.',
        'starts_at' => now()->addWeeks(3),
        'ends_at' => now()->addWeeks(3)->addDay(),
        'status' => 'live',
        'barangay' => 'Poblacion',
        'city' => 'Makati',
        'province' => 'Metro Manila (NCR)',
        'country_name' => 'Philippines',
        'timezone' => 'Asia/Manila',
        'event_type' => 'Tournament',
        'division' => 'Mix',
        'surface' => 'Outdoor',
        'organizer_items' => [
            ['label' => 'Contact Email', 'value' => 'events@public-finals.test'],
            ['label' => 'Hotline', 'value' => '+63 917 555 0101'],
        ],
        'info_items' => [
            ['label' => 'Tournament hotline', 'value' => '+63 917 555 0199'],
            ['label' => 'Roster cap', 'value' => '26 players per team'],
        ],
        'link_items' => [
            ['label' => 'Registration Form', 'href' => 'https://example.com/register'],
            ['label' => 'Event Handbook', 'href' => 'https://example.com/handbook'],
        ],
        'is_public' => true,
    ]);

    $team = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Disc Hawks',
        'address' => 'Makati City',
        'city' => 'Makati',
        'country_name' => 'Philippines',
        'logo_path' => null,
        'status' => 'active',
    ]);

    Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Pitch Alpha',
        'location' => 'North Wing',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $team->id,
        'status' => 'approved',
        'seed_number' => 1,
        'pool_name' => 'POOL A',
    ]);

    TeamMember::query()->create([
        'team_id' => $team->id,
        'name' => 'Alden Reyes',
        'gender' => 'Male',
        'role' => 'captain',
    ]);

    TeamMember::query()->create([
        'team_id' => $team->id,
        'name' => 'Mika Dela Cruz',
        'gender' => 'Female',
        'role' => 'member',
    ]);

    $this->get(route('tournaments.show', ['tournament' => $tournament, 'tab' => 'info']))
        ->assertOk()
        ->assertSee('Public Finals Weekend')
        ->assertSee('Championship weekend with public schedule data.')
        ->assertSee('Tournament Admin')
        ->assertSee('Contact Email')
        ->assertSee('events@public-finals.test')
        ->assertSee('Poblacion')
        ->assertSee('Metro Manila (NCR)')
        ->assertSee('Tournament hotline')
        ->assertSee('+63 917 555 0199')
        ->assertSee('Roster cap')
        ->assertSee('26 players per team')
        ->assertSee('Registration Form')
        ->assertSee('Event Handbook');

    $this->get(route('tournaments.show', ['tournament' => $tournament, 'tab' => 'teams']))
        ->assertOk()
        ->assertSee('1 registered team')
        ->assertSee('View Members')
        ->assertSee('Disc Hawks')
        ->assertSee('Alden Reyes')
        ->assertSee('Mika Dela Cruz')
        ->assertSee('Makati, Philippines');

    $this->get(route('tournaments.show', ['tournament' => $tournament, 'tab' => 'pitches']))
        ->assertOk()
        ->assertSee('Pitch Alpha');
});

test('public tournament stats tab shows leaderboard filters and applies stats search', function () {
    $organizer = User::factory()->admin()->create([
        'name' => 'Stats Admin',
    ]);

    $homeOwner = User::factory()->create();
    $awayOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $organizer->id,
        'name' => 'Metro Showdown',
        'slug' => 'metro-showdown',
        'venue' => 'Central Grounds',
        'starts_at' => now()->addWeeks(2),
        'ends_at' => now()->addWeeks(2)->addDay(),
        'status' => 'live',
        'city' => 'Pasig',
        'country_name' => 'Philippines',
        'timezone' => 'Asia/Manila',
        'event_type' => 'Tournament',
        'division' => 'Mix',
        'surface' => 'Outdoor',
        'is_public' => true,
    ]);

    $homeTeam = Team::query()->create([
        'owner_user_id' => $homeOwner->id,
        'name' => 'Metro Falcons',
        'address' => 'Pasig City',
        'city' => 'Pasig',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $awayTeam = Team::query()->create([
        'owner_user_id' => $awayOwner->id,
        'name' => 'Coastal Flyers',
        'address' => 'Paranaque City',
        'city' => 'Paranaque',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $homeMale = TeamMember::query()->create([
        'team_id' => $homeTeam->id,
        'name' => 'Marco Santos',
        'nickname' => 'Marco',
        'gender' => 'Male',
        'role' => 'captain',
    ]);

    $homeFemale = TeamMember::query()->create([
        'team_id' => $homeTeam->id,
        'name' => 'Lea Torres',
        'nickname' => 'LT',
        'gender' => 'Female',
        'role' => 'member',
    ]);

    $awayMale = TeamMember::query()->create([
        'team_id' => $awayTeam->id,
        'name' => 'Paolo Reyes',
        'nickname' => 'Paolo',
        'gender' => 'Male',
        'role' => 'captain',
    ]);

    $awayFemale = TeamMember::query()->create([
        'team_id' => $awayTeam->id,
        'name' => 'Mika Javier',
        'nickname' => 'Mika',
        'gender' => 'Female',
        'role' => 'member',
    ]);

    $homeRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $homeTeam->id,
        'status' => 'approved',
        'seed_number' => 1,
    ]);

    $awayRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $awayTeam->id,
        'status' => 'approved',
        'seed_number' => 2,
    ]);

    $match = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'group',
        'match_number' => 1,
        'scheduled_at' => now()->addWeeks(2)->setTime(9, 0),
        'status' => 'completed',
        'home_score' => 9,
        'away_score' => 7,
    ]);

    foreach ([
        ['member' => $homeMale, 'goals' => 4, 'assists' => 1, 'blocks' => 2],
        ['member' => $homeFemale, 'goals' => 2, 'assists' => 5, 'blocks' => 0],
        ['member' => $awayMale, 'goals' => 3, 'assists' => 1, 'blocks' => 3],
        ['member' => $awayFemale, 'goals' => 2, 'assists' => 4, 'blocks' => 1],
    ] as $statLine) {
        MatchPlayerStat::query()->create([
            'match_id' => $match->id,
            'team_member_id' => $statLine['member']->id,
            'goals' => $statLine['goals'],
            'assists' => $statLine['assists'],
            'blocks' => $statLine['blocks'],
        ]);
    }

    $this->get(route('tournaments.show', [
        'tournament' => $tournament,
        'tab' => 'stats',
        'metric' => 'total_offense',
    ]))
        ->assertOk()
        ->assertSee('Mixed')
        ->assertSee('Mix (All)')
        ->assertSee('Mix (Women)')
        ->assertSee('Search by name')
        ->assertSee('data-livewire-navigate-form', false)
        ->assertSee('wire:navigate.preserve-scroll', false)
        ->assertSee('Total O')
        ->assertSee('Blocks')
        ->assertSeeInOrder([
            'Lea Torres (LT)',
            'Mika Javier (Mika)',
            'Marco Santos (Marco)',
        ]);

    $this->get(route('tournaments.show', [
        'tournament' => $tournament,
        'tab' => 'stats',
        'metric' => 'blocks',
    ]))
        ->assertOk()
        ->assertSee('metric=blocks', false)
        ->assertSeeInOrder([
            'Paolo Reyes (Paolo)',
            'Marco Santos (Marco)',
            'Mika Javier (Mika)',
            'Lea Torres (LT)',
        ]);

    $this->get(route('tournaments.show', [
        'tournament' => $tournament,
        'tab' => 'stats',
        'gender' => 'women',
        'metric' => 'blocks',
        'search' => 'Mika',
    ]))
        ->assertOk()
        ->assertSee('Mika Javier (Mika)')
        ->assertDontSee('Lea Torres (LT)');

    $this->get(route('tournaments.show', [
        'tournament' => $tournament,
        'tab' => 'stats',
        'gender' => 'women',
        'metric' => 'assists',
    ]))
        ->assertOk()
        ->assertSee('Lea Torres (LT)')
        ->assertSee('Mika Javier (Mika)')
        ->assertDontSee('Marco Santos (Marco)')
        ->assertDontSee('Paolo Reyes (Paolo)');

    $this->get(route('tournaments.show', [
        'tournament' => $tournament,
        'tab' => 'stats',
        'gender' => 'women',
        'search' => 'Mika',
    ]))
        ->assertOk()
        ->assertSee('Mika Javier (Mika)')
        ->assertDontSee('Lea Torres (LT)');

    $this->get(route('tournaments.show', [
        'tournament' => $tournament,
        'tab' => 'mvp',
        'gender' => 'women',
    ]))
        ->assertOk()
        ->assertSee('Division')
        ->assertSee('Mixed')
        ->assertSee('Mix (Women)')
        ->assertSee('Name')
        ->assertSee('Team')
        ->assertSee('Gender')
        ->assertSee('Lea Torres')
        ->assertSee('Mika Javier')
        ->assertDontSee('Marco Santos')
        ->assertDontSee('Paolo Reyes');
});

test('public tournament stats tab paginates leaderboard results', function () {
    $organizer = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $organizer->id,
        'name' => 'Leaderboard Classic',
        'slug' => 'leaderboard-classic',
        'venue' => 'North Field',
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addDay(),
        'status' => 'live',
        'city' => 'Quezon City',
        'country_name' => 'Philippines',
        'timezone' => 'Asia/Manila',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $homeTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Alpha Stack',
        'address' => 'Quezon City',
        'city' => 'Quezon City',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $awayTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Beta Cup',
        'address' => 'Makati City',
        'city' => 'Makati',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $homeRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $homeTeam->id,
        'status' => 'approved',
        'seed_number' => 1,
    ]);

    $awayRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $awayTeam->id,
        'status' => 'approved',
        'seed_number' => 2,
    ]);

    $match = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'group',
        'match_number' => 1,
        'scheduled_at' => now()->addWeek()->setTime(10, 0),
        'status' => 'completed',
        'home_score' => 22,
        'away_score' => 0,
    ]);

    foreach (range(1, 22) as $index) {
        $member = TeamMember::query()->create([
            'team_id' => $homeTeam->id,
            'name' => sprintf('Player %02d', $index),
            'nickname' => sprintf('P%02d', $index),
            'gender' => $index % 2 === 0 ? 'Female' : 'Male',
            'role' => 'member',
        ]);

        MatchPlayerStat::query()->create([
            'match_id' => $match->id,
            'team_member_id' => $member->id,
            'goals' => 23 - $index,
            'assists' => 0,
            'blocks' => 0,
        ]);
    }

    $this->get(route('tournaments.show', [
        'tournament' => $tournament,
        'tab' => 'stats',
    ]))
        ->assertOk()
        ->assertSee('Player 01 (P01)')
        ->assertSee('Player 20 (P20)')
        ->assertDontSee('Player 21 (P21)')
        ->assertSee('page=2');

    $this->get(route('tournaments.show', [
        'tournament' => $tournament,
        'tab' => 'stats',
        'page' => 2,
    ]))
        ->assertOk()
        ->assertSee('Player 21 (P21)')
        ->assertSee('Player 22 (P22)')
        ->assertDontSee('Player 01 (P01)');
});

test('public tournament stats tab omits registration tier nicknames like Full from display names', function () {
    $organizer = User::factory()->admin()->create();
    $owner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $organizer->id,
        'name' => 'Roster Label Cup',
        'slug' => 'roster-label-cup',
        'venue' => 'Test Field',
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addDay(),
        'status' => 'live',
        'city' => 'Manila',
        'country_name' => 'Philippines',
        'timezone' => 'Asia/Manila',
        'division' => 'Open',
        'is_public' => true,
    ]);

    $team = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Tier Hawks',
        'address' => 'Manila',
        'city' => 'Manila',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $member = TeamMember::query()->create([
        'team_id' => $team->id,
        'name' => 'Jordan Cruz',
        'nickname' => 'Full',
        'gender' => 'Male',
        'role' => 'member',
    ]);

    $registration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $team->id,
        'status' => 'approved',
        'seed_number' => 1,
    ]);

    $otherTeam = Team::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'Bench Owls',
        'address' => 'Manila',
        'city' => 'Manila',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $otherRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $otherTeam->id,
        'status' => 'approved',
        'seed_number' => 2,
    ]);

    $match = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'home_registration_id' => $registration->id,
        'away_registration_id' => $otherRegistration->id,
        'stage' => 'group',
        'match_number' => 1,
        'scheduled_at' => now()->addWeek()->setTime(9, 0),
        'status' => 'completed',
        'home_score' => 5,
        'away_score' => 3,
    ]);

    MatchPlayerStat::query()->create([
        'match_id' => $match->id,
        'team_member_id' => $member->id,
        'goals' => 2,
        'assists' => 1,
        'blocks' => 0,
    ]);

    $this->get(route('tournaments.show', [
        'tournament' => $tournament,
        'tab' => 'stats',
        'metric' => 'goals',
    ]))
        ->assertOk()
        ->assertSee('Jordan Cruz')
        ->assertSee('Tier Hawks')
        ->assertDontSee('Jordan Cruz (Full)');

    $this->get(route('tournaments.show', [
        'tournament' => $tournament,
        'tab' => 'stats',
        'metric' => 'assists',
        'search' => 'full',
    ]))
        ->assertOk()
        ->assertSee('Jordan Cruz')
        ->assertDontSee('Jordan Cruz (Full)');
});

test('private tournaments are not accessible on the public detail page', function () {
    $tournament = Tournament::query()->create([
        'name' => 'Closed Event',
        'slug' => 'closed-event',
        'venue' => 'Restricted Venue',
        'status' => 'draft',
        'is_public' => false,
    ]);

    $this->get(route('tournaments.show', $tournament))->assertNotFound();
});

test('public tournament detail exposes the added tournament tabs', function () {
    $organizer = User::factory()->admin()->create([
        'name' => 'Tournament Director',
    ]);

    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $organizer->id,
        'name' => 'Tabs Showcase Cup',
        'slug' => 'tabs-showcase-cup',
        'venue' => 'Sunrise Fields',
        'registration_deadline' => now()->subDays(2),
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'status' => 'completed',
        'city' => 'Cebu City',
        'country_name' => 'Philippines',
        'timezone' => 'Asia/Manila',
        'division' => 'Open',
        'is_public' => true,
    ]);

    $pitch = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Field Alpha',
        'location' => 'Main grounds',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $homeTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Sky Raiders',
        'address' => 'Cebu City',
        'city' => 'Cebu City',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $awayTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Wave Breakers',
        'address' => 'Mandaue City',
        'city' => 'Mandaue City',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $thirdTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Solar Surge',
        'address' => 'Lapu-Lapu City',
        'city' => 'Lapu-Lapu City',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $fourthTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Tide Runners',
        'address' => 'Talisay City',
        'city' => 'Talisay City',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $homeCaptain = TeamMember::query()->create([
        'team_id' => $homeTeam->id,
        'name' => 'Toni Captain',
        'nickname' => 'Toni',
        'gender' => 'Male',
        'age' => 27,
        'address' => 'Cebu City',
        'role' => 'captain',
    ]);

    $homeSpirit = TeamMember::query()->create([
        'team_id' => $homeTeam->id,
        'name' => 'Ina Spirit',
        'nickname' => 'Ina',
        'gender' => 'Female',
        'age' => 26,
        'address' => 'Cebu City',
        'role' => 'spirit_captain',
    ]);

    TeamMember::query()->create([
        'team_id' => $awayTeam->id,
        'name' => 'Jules Captain',
        'nickname' => 'Jules',
        'gender' => 'Male',
        'age' => 28,
        'address' => 'Mandaue City',
        'role' => 'captain',
    ]);

    TeamMember::query()->create([
        'team_id' => $awayTeam->id,
        'name' => 'Mia Spirit',
        'nickname' => 'Mia',
        'gender' => 'Female',
        'age' => 24,
        'address' => 'Mandaue City',
        'role' => 'spirit_captain',
    ]);

    $homeRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $homeTeam->id,
        'status' => 'approved',
        'seed_number' => 1,
        'bracket_code' => 'CHAMP',
        'bracket_rank' => 'A1',
        'pool_name' => 'GROUP A',
    ]);

    $awayRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $awayTeam->id,
        'status' => 'approved',
        'seed_number' => 2,
        'bracket_code' => 'CHAMP',
        'bracket_rank' => 'A2',
        'pool_name' => 'GROUP A',
    ]);

    $thirdRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $thirdTeam->id,
        'status' => 'approved',
        'seed_number' => 3,
        'bracket_code' => 'CHAMP',
        'bracket_rank' => 'B1',
        'pool_name' => 'GROUP B',
    ]);

    $fourthRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $fourthTeam->id,
        'status' => 'approved',
        'seed_number' => 4,
        'bracket_code' => 'CHAMP',
        'bracket_rank' => 'B2',
        'pool_name' => 'GROUP B',
    ]);

    $groupMatch = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitch->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'pool_play',
        'round_label' => 'Group A',
        'match_number' => 1,
        'scheduled_at' => now()->subHours(4),
        'status' => 'completed',
        'home_score' => 11,
        'away_score' => 8,
        'notes' => json_encode([
            'spirit_scores' => [
                'home_received' => [
                    'rules' => 3,
                    'fouls' => 2,
                    'fair' => 2,
                    'attitude' => 2,
                    'communication' => 2,
                ],
                'away_received' => [
                    'rules' => 3,
                    'fouls' => 3,
                    'fair' => 3,
                    'attitude' => 3,
                    'communication' => 3,
                ],
            ],
        ], JSON_THROW_ON_ERROR),
    ]);

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitch->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'semifinal',
        'round_label' => 'Semifinal',
        'match_number' => 2,
        'scheduled_at' => now()->addHours(3),
        'status' => 'scheduled',
    ]);

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitch->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'reseeding',
        'round_label' => 'Reseeding',
        'match_number' => 3,
        'scheduled_at' => now()->subHours(2),
        'status' => 'completed',
        'home_score' => 13,
        'away_score' => 12,
    ]);

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitch->id,
        'home_registration_id' => $thirdRegistration->id,
        'away_registration_id' => $fourthRegistration->id,
        'stage' => 'consolation',
        'round_label' => 'Consolation',
        'match_number' => 4,
        'scheduled_at' => now()->subHour(),
        'status' => 'completed',
        'home_score' => 9,
        'away_score' => 7,
    ]);

    MatchPlayerStat::query()->create([
        'match_id' => $groupMatch->id,
        'team_member_id' => $homeCaptain->id,
        'goals' => 4,
        'assists' => 2,
        'blocks' => 1,
    ]);

    MatchPlayerStat::query()->create([
        'match_id' => $groupMatch->id,
        'team_member_id' => $homeSpirit->id,
        'goals' => 1,
        'assists' => 3,
        'blocks' => 0,
    ]);

    TournamentCrew::query()->create([
        'tournament_id' => $tournament->id,
        'category' => 'Tournament Admins',
        'title' => 'Tournament Admin',
        'name' => 'Crew Chief',
        'sort_order' => 10,
    ]);

    TournamentCrew::query()->create([
        'tournament_id' => $tournament->id,
        'category' => 'Scorekeepers',
        'title' => 'Head Scorekeeper',
        'name' => 'Mina Park',
        'sort_order' => 20,
    ]);

    $this->get(route('tournaments.show', ['tournament' => $tournament, 'tab' => 'spirit']))
        ->assertOk()
        ->assertSee('Average Spirit Score of Each Team')
        ->assertSee('Games Rated')
        ->assertSee('Avg')
        ->assertSee('Rules')
        ->assertSee('Comm.')
        ->assertSee('Wave Breakers')
        ->assertSee('Sky Raiders')
        ->assertSeeInOrder([
            'Wave Breakers',
            'Sky Raiders',
        ])
        ->assertSee('wire:navigate.preserve-scroll', false);

    $this->get(route('tournaments.show', [
        'tournament' => $tournament,
        'tab' => 'spirit',
        'spirit_sort' => 'team',
    ]))
        ->assertOk()
        ->assertSeeInOrder([
            'Sky Raiders',
            'Wave Breakers',
        ]);

    $this->get(route('tournaments.show', ['tournament' => $tournament, 'tab' => 'group']))
        ->assertOk()
        ->assertSee('Pool play overview')
        ->assertSee('Games Played')
        ->assertSee('Pts')
        ->assertSee('Group Games')
        ->assertSee('Group A')
        ->assertSee('Group B')
        ->assertSee('Reseeding')
        ->assertSee('Consolation')
        ->assertSee('Sky Raiders');

    $this->get(route('tournaments.show', [
        'tournament' => $tournament,
        'tab' => 'group',
        'pool' => 'RESEEDING',
    ]))
        ->assertOk()
        ->assertSee('Reseeding')
        ->assertSee('Sky Raiders')
        ->assertSee('Wave Breakers');

    $this->get(route('tournaments.show', [
        'tournament' => $tournament,
        'tab' => 'group',
        'pool' => 'consolation',
    ]))
        ->assertOk()
        ->assertSee('Consolation')
        ->assertSee('Solar Surge')
        ->assertSee('Tide Runners');

    $this->get(route('tournaments.show', ['tournament' => $tournament, 'tab' => 'bracket']))
        ->assertOk()
        ->assertSee('Bracket overview')
        ->assertSee('Pool')
        ->assertSee('Sky Raiders')
        ->assertSee('Semifinal');

    $this->get(route('tournaments.show', ['tournament' => $tournament, 'tab' => 'mvp']))
        ->assertOk()
        ->assertSee('Division')
        ->assertSee('Name')
        ->assertSee('Team')
        ->assertSee('Gender')
        ->assertSee('Toni Captain')
        ->assertSee('Sky Raiders');

    $this->get(route('tournaments.show', ['tournament' => $tournament, 'tab' => 'standings']))
        ->assertOk()
        ->assertSee('Division')
        ->assertSee('Open')
        ->assertSee('Sky Raiders')
        ->assertSee('Philippines')
        ->assertSee('wire:navigate', false);

    $this->get(route('tournaments.teams.show', ['tournament' => $tournament, 'team' => $homeTeam]))
        ->assertOk()
        ->assertSee('Back')
        ->assertSee('Total Players: 2')
        ->assertSee('Roster deadline has passed.')
        ->assertSee('Overall Stats Summary')
        ->assertSee('data-stat-donut', false)
        ->assertSee('data-chart-metric="goals"', false)
        ->assertSee('Toni Captain')
        ->assertSee('Ina Spirit')
        ->assertSee('wire:navigate', false)
        ->assertSee('wire:navigate.preserve-scroll', false);

    $this->get(route('tournaments.teams.show', [
        'tournament' => $tournament,
        'team' => $homeTeam,
        'sort' => 'assists',
    ]))
        ->assertOk()
        ->assertSeeInOrder([
            'Ina Spirit',
            'Toni Captain',
        ]);

    $this->get(route('tournaments.teams.show', ['tournament' => $tournament, 'team' => $homeTeam, 'view' => 'games-played']))
        ->assertOk()
        ->assertSee('Win vs Loss')
        ->assertSee('Points For vs Points Against')
        ->assertSee('Wins: 2 (100.00%)')
        ->assertSee('For: 24 (54.55%)')
        ->assertSee('Against: 20 (45.45%)')
        ->assertSee('Wave Breakers')
        ->assertSee('GROUP GAME')
        ->assertSee('ENDED');

    $this->get(route('tournaments.teams.show', ['tournament' => $tournament, 'team' => $homeTeam, 'view' => 'spirit']))
        ->assertOk()
        ->assertSee('Overall Spirit Score: 11.00')
        ->assertSee('Spirit Scores Received')
        ->assertSee('Wave Breakers')
        ->assertSee('Rules')
        ->assertSee('Fouls')
        ->assertSee('Comm.')
        ->assertSee('11');

    $this->get(route('tournaments.show', ['tournament' => $tournament, 'tab' => 'crew']))
        ->assertOk()
        ->assertSee('Event and team crew')
        ->assertSee('Tournament Admins')
        ->assertSee('Scorekeepers')
        ->assertSee('Crew Chief')
        ->assertSee('Mina Park');
});

test('public tournament board can switch between upcoming and past events', function () {
    Tournament::query()->create([
        'name' => 'Future Open',
        'slug' => 'future-open',
        'venue' => 'North Field',
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addDay(),
        'status' => 'registration',
        'is_public' => true,
    ]);

    Tournament::query()->create([
        'name' => 'Historic Classic',
        'slug' => 'historic-classic',
        'venue' => 'South Field',
        'starts_at' => now()->subWeeks(3),
        'ends_at' => now()->subWeeks(3)->addDay(),
        'status' => 'completed',
        'is_public' => true,
    ]);

    $this->get(route('tournaments.index'))
        ->assertOk()
        ->assertSee('Future Open')
        ->assertDontSee('Historic Classic');

    $this->get(route('tournaments.index', ['period' => 'past']))
        ->assertOk()
        ->assertSee('Historic Classic')
        ->assertDontSee('Future Open');
});

test('public tournament schedule can filter group and bracket matches', function () {
    $organizer = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $organizer->id,
        'name' => 'Schedule Filter Showcase',
        'slug' => 'schedule-filter-showcase',
        'venue' => 'South Grounds',
        'starts_at' => now()->addDays(5),
        'ends_at' => now()->addDays(6),
        'status' => 'live',
        'city' => 'Cebu City',
        'country_name' => 'Philippines',
        'timezone' => 'Asia/Manila',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $pitch = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Field 1',
        'location' => 'Central grounds',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $groupTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Group Flyers',
        'address' => 'Cebu City',
        'city' => 'Cebu City',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $bracketTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Bracket Breakers',
        'address' => 'Mandaue City',
        'city' => 'Mandaue City',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $groupOpponent = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Pool Opponents',
        'address' => 'Lapu-Lapu City',
        'city' => 'Lapu-Lapu City',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $bracketOpponent = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Final Four',
        'address' => 'Talisay City',
        'city' => 'Talisay City',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $groupRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $groupTeam->id,
        'status' => 'approved',
        'seed_number' => 1,
    ]);

    $groupOpponentRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $groupOpponent->id,
        'status' => 'approved',
        'seed_number' => 2,
    ]);

    $bracketRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $bracketTeam->id,
        'status' => 'approved',
        'seed_number' => 3,
    ]);

    $bracketOpponentRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $bracketOpponent->id,
        'status' => 'approved',
        'seed_number' => 4,
    ]);

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitch->id,
        'home_registration_id' => $groupRegistration->id,
        'away_registration_id' => $groupOpponentRegistration->id,
        'stage' => 'pool_play',
        'round_label' => 'Group A',
        'match_number' => 1,
        'scheduled_at' => now()->addDays(5)->setTime(8, 15),
        'status' => 'completed',
        'home_score' => 8,
        'away_score' => 5,
    ]);

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitch->id,
        'home_registration_id' => $bracketRegistration->id,
        'away_registration_id' => $bracketOpponentRegistration->id,
        'stage' => 'semifinal',
        'round_label' => 'Semifinal 1',
        'match_number' => 2,
        'scheduled_at' => now()->addDays(6)->setTime(10, 0),
        'status' => 'scheduled',
    ]);

    $this->get(route('tournaments.show', ['tournament' => $tournament, 'tab' => 'schedule']))
        ->assertOk()
        ->assertSee('View Timetable')
        ->assertSee('Group')
        ->assertSee('Bracket')
        ->assertSee('Group Flyers')
        ->assertSee('Bracket Breakers');

    $this->get(route('tournaments.show', ['tournament' => $tournament, 'tab' => 'schedule', 'stage' => 'group']))
        ->assertOk()
        ->assertSee('GROUP GAME')
        ->assertSee('Group Flyers')
        ->assertDontSee('Bracket Breakers');

    $this->get(route('tournaments.show', ['tournament' => $tournament, 'tab' => 'schedule', 'stage' => 'bracket']))
        ->assertOk()
        ->assertSee('SEMIFINAL')
        ->assertSee('Bracket Breakers')
        ->assertDontSee('Group Flyers');
});

test('public tournament schedule renders the timetable overlay layout', function () {
    $organizer = User::factory()->admin()->create();
    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $organizer->id,
        'name' => 'Timetable Overlay Cup',
        'slug' => 'timetable-overlay-cup',
        'venue' => 'City Sports Complex',
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(4),
        'status' => 'live',
        'city' => 'Cebu City',
        'country_name' => 'Philippines',
        'timezone' => 'Asia/Manila',
        'division' => 'Mixed',
        'is_public' => true,
    ]);

    $fieldOne = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Field 1',
        'location' => 'North Zone',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $fieldTwo = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Field 2',
        'location' => 'South Zone',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $alpha = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Alpha Seven',
        'address' => 'Cebu City',
        'city' => 'Cebu City',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $beta = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Beta Line',
        'address' => 'Mandaue City',
        'city' => 'Mandaue City',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $gamma = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Gamma Crew',
        'address' => 'Lapu-Lapu City',
        'city' => 'Lapu-Lapu City',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $delta = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Delta Squad',
        'address' => 'Talisay City',
        'city' => 'Talisay City',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $alphaRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $alpha->id,
        'status' => 'approved',
        'seed_number' => 4,
    ]);

    $betaRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $beta->id,
        'status' => 'approved',
        'seed_number' => 12,
    ]);

    $gammaRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $gamma->id,
        'status' => 'approved',
        'seed_number' => 6,
    ]);

    $deltaRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $delta->id,
        'status' => 'approved',
        'seed_number' => 9,
    ]);

    $slotStart = now()->addDays(3)->setTime(8, 15);

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $fieldOne->id,
        'home_registration_id' => $alphaRegistration->id,
        'away_registration_id' => $betaRegistration->id,
        'stage' => 'pool_play',
        'round_label' => 'Group A',
        'match_number' => 1,
        'scheduled_at' => $slotStart,
        'status' => 'scheduled',
    ]);

    TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $fieldTwo->id,
        'home_registration_id' => $gammaRegistration->id,
        'away_registration_id' => $deltaRegistration->id,
        'stage' => 'semifinal',
        'round_label' => 'SF1',
        'match_number' => 2,
        'scheduled_at' => $slotStart,
        'status' => 'scheduled',
    ]);

    $scheduleResponse = $this->get(route('tournaments.show', ['tournament' => $tournament, 'tab' => 'schedule']));

    $scheduleResponse
        ->assertOk()
        ->assertSee('View Timetable')
        ->assertSee('timetable=1', false);

    $this->get(route('tournaments.show', ['tournament' => $tournament, 'tab' => 'schedule', 'timetable' => 1]))
        ->assertOk()
        ->assertSee('Timetable')
        ->assertSee('Close')
        ->assertSee('Time')
        ->assertSee('Field 1')
        ->assertSee('Field 2')
        ->assertSee('08:15')
        ->assertSee('09:15')
        ->assertSee('Alpha Seven')
        ->assertSee('Gamma Crew');
});

test('public schedule cards link to a public match detail page', function () {
    $organizer = User::factory()->admin()->create([
        'name' => 'Match Detail Admin',
    ]);

    $teamOwner = User::factory()->create();

    $tournament = Tournament::query()->create([
        'created_by' => $organizer->id,
        'name' => 'Match Detail Weekend',
        'slug' => 'match-detail-weekend',
        'venue' => 'Central Arena',
        'starts_at' => now()->addDays(8),
        'ends_at' => now()->addDays(9),
        'status' => 'live',
        'city' => 'Cagayan de Oro',
        'country_name' => 'Philippines',
        'timezone' => 'Asia/Manila',
        'division' => 'Mix',
        'is_public' => true,
    ]);

    $pitch = Pitch::query()->create([
        'tournament_id' => $tournament->id,
        'name' => 'Field 1',
        'location' => 'Main oval',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $homeTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Saigon Monsoon Ultimate',
        'address' => 'Cagayan de Oro',
        'city' => 'Cagayan de Oro',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $awayTeam = Team::query()->create([
        'owner_user_id' => $teamOwner->id,
        'name' => 'Black Panthers',
        'address' => 'Bukidnon',
        'city' => 'Malaybalay',
        'country_name' => 'Philippines',
        'status' => 'active',
    ]);

    $homeCaptain = TeamMember::query()->create([
        'team_id' => $homeTeam->id,
        'name' => 'Le Ho Phat Tai',
        'nickname' => 'Tai',
        'gender' => 'Male',
        'age' => 25,
        'address' => 'Cagayan de Oro',
        'role' => 'captain',
    ]);

    TeamMember::query()->create([
        'team_id' => $awayTeam->id,
        'name' => 'Tu Chau',
        'nickname' => 'Tu',
        'gender' => 'Female',
        'age' => 24,
        'address' => 'Bukidnon',
        'role' => 'spirit_captain',
    ]);

    $homeRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $homeTeam->id,
        'status' => 'approved',
        'seed_number' => 1,
    ]);

    $awayRegistration = TournamentRegistration::query()->create([
        'tournament_id' => $tournament->id,
        'team_id' => $awayTeam->id,
        'status' => 'approved',
        'seed_number' => 4,
    ]);

    $match = TournamentMatch::query()->create([
        'tournament_id' => $tournament->id,
        'pitch_id' => $pitch->id,
        'home_registration_id' => $homeRegistration->id,
        'away_registration_id' => $awayRegistration->id,
        'stage' => 'semifinal',
        'round_label' => 'SF1-1v4',
        'match_number' => 1,
        'scheduled_at' => now()->addDays(8)->setTime(13, 0),
        'status' => 'completed',
        'home_score' => 9,
        'away_score' => 3,
    ]);

    MatchPlayerStat::query()->create([
        'match_id' => $match->id,
        'team_member_id' => $homeCaptain->id,
        'goals' => 3,
        'assists' => 2,
        'blocks' => 1,
    ]);

    MatchScoreLog::query()->create([
        'match_id' => $match->id,
        'sequence' => 1,
        'team_registration_id' => $homeRegistration->id,
        'team_member_id' => $homeCaptain->id,
        'minute' => 4,
        'home_score' => 1,
        'away_score' => 0,
    ]);

    $scheduleUrl = route('tournaments.matches.show', ['tournament' => $tournament, 'match' => $match]);

    $this->get(route('tournaments.show', ['tournament' => $tournament, 'tab' => 'schedule', 'stage' => 'bracket']))
        ->assertOk()
        ->assertSee($scheduleUrl, false)
        ->assertSee('Saigon Monsoon Ultimate');

    $this->get($scheduleUrl)
        ->assertOk()
        ->assertSee('Back to schedule')
        ->assertSee('Saigon Monsoon Ultimate')
        ->assertSee('Black Panthers')
        ->assertSee('Summary')
        ->assertSee('Score Breakdown')
        ->assertSee('1 - 0');

    $this->get(route('tournaments.matches.show', ['tournament' => $tournament, 'match' => $match, 'tab' => 'stats']))
        ->assertOk()
        ->assertSee('Team Stats')
        ->assertSee('Points')
        ->assertSee('Scoring Plays');

    $this->get(route('tournaments.matches.show', ['tournament' => $tournament, 'match' => $match, 'tab' => 'spirit']))
        ->assertOk()
        ->assertSee('The tournament is still in progress.')
        ->assertSee('Spirit Score');

    $this->get(route('tournaments.matches.show', ['tournament' => $tournament, 'match' => $match, 'tab' => 'mvp']))
        ->assertOk()
        ->assertSee('The tournament is still in progress.')
        ->assertSee('MVP results');
});
