<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentRegistration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Manual small-tournament round robin schedule UI: day headers, slot grouping, and helpers
 * without fixed Berger grids or hardcoded calendar dates.
 */
final class ManualRoundRobinSchedule
{
    public static function normalizeTimezone(?string $timezone): string
    {
        $timezone = trim((string) $timezone);

        if ($timezone === '' || $timezone === 'UTC+8' || $timezone === 'GMT+8') {
            return 'Asia/Manila';
        }

        if (preg_match('/^UTC\+?8(?::00)?$/i', $timezone) === 1) {
            return 'Asia/Manila';
        }

        if (in_array($timezone, timezone_identifiers_list(), true)) {
            return $timezone;
        }

        return 'Asia/Manila';
    }

    public static function tournamentTimezone(Tournament $tournament): string
    {
        $raw = trim((string) ($tournament->timezone ?? ''));

        if ($raw === '') {
            return 'Asia/Manila';
        }

        return self::normalizeTimezone($raw);
    }

    /**
     * Local calendar day for "Day 1" section (tournament start, falling back sensibly when unset).
     */
    public static function roundRobinDayOneLocal(Tournament $tournament): CarbonImmutable
    {
        $tz = self::tournamentTimezone($tournament);
        $base = $tournament->starts_at ?? $tournament->ends_at ?? CarbonImmutable::now($tz);

        return CarbonImmutable::createFromInterface($base)->timezone($tz)->startOfDay();
    }

    /**
     * Local calendar day for "Day 2" section (event end when it differs; otherwise day after Day 1).
     */
    public static function roundRobinDayTwoLocal(Tournament $tournament): CarbonImmutable
    {
        $d1 = self::roundRobinDayOneLocal($tournament);
        $tz = self::tournamentTimezone($tournament);

        if ($tournament->ends_at !== null) {
            $d2 = CarbonImmutable::createFromInterface($tournament->ends_at)->timezone($tz)->startOfDay();

            if (! $d2->equalTo($d1)) {
                return $d2;
            }
        }

        return $d1->addDay();
    }

    public static function dayDateLabel(CarbonImmutable $dayLocal): string
    {
        return $dayLocal->format('M j, Y');
    }

    public static function dayDateIso(CarbonImmutable $dayLocal): string
    {
        return $dayLocal->format('Y-m-d');
    }

    /**
     * @return Collection<int, TournamentRegistration>
     */
    public static function sortedRegistrations(Tournament $tournament): Collection
    {
        return $tournament->registrations
            ->sort(static fn ($left, $right) => [
                $left->seed_number ?? PHP_INT_MAX,
                $left->team?->name ?? '',
                $left->id,
            ] <=> [
                $right->seed_number ?? PHP_INT_MAX,
                $right->team?->name ?? '',
                $right->id,
            ])
            ->values();
    }

    public static function mapRowUiStatusToMatchStatus(string $ui): string
    {
        return match (strtolower(trim($ui))) {
            'live' => TournamentMatch::STATUS_LIVE,
            'completed' => TournamentMatch::STATUS_COMPLETED,
            default => TournamentMatch::STATUS_SCHEDULED,
        };
    }

    public static function mapMatchStatusToRowUi(?string $status): string
    {
        return match (strtolower((string) $status)) {
            TournamentMatch::STATUS_LIVE, 'live' => 'live',
            TournamentMatch::STATUS_COMPLETED, 'completed' => 'completed',
            default => 'upcoming',
        };
    }

    public static function parseRoundNumberFromLabel(?string $label): int
    {
        if ($label === null || $label === '') {
            return 1;
        }

        return preg_match('/(\d+)/', (string) $label, $m) === 1 ? (int) $m[1] : 1;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function activeSlotsForDay(Tournament $tournament, CarbonImmutable $dayLocal): Collection
    {
        return self::slotsForDayFromMatches(
            $tournament,
            $dayLocal,
            $tournament->matches->filter(fn (TournamentMatch $m): bool => $m->stage === 'round_robin'),
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function removedSlotsForDay(Tournament $tournament, CarbonImmutable $dayLocal): Collection
    {
        $matches = TournamentMatch::onlyTrashed()
            ->where('tournament_id', $tournament->id)
            ->where('stage', 'round_robin')
            ->orderByRaw('case when scheduled_at is null then 1 else 0 end')
            ->orderBy('scheduled_at')
            ->orderBy('match_number')
            ->orderBy('id')
            ->get();

        return self::slotsForDayFromMatches($tournament, $dayLocal, $matches);
    }

    /**
     * @param  Collection<int, TournamentMatch>  $matches
     * @return Collection<int, array<string, mixed>>
     */
    public static function slotsForDayFromMatches(
        Tournament $tournament,
        CarbonImmutable $dayLocal,
        Collection $matches,
    ): Collection {
        $tz = self::tournamentTimezone($tournament);
        $dayKey = $dayLocal->format('Y-m-d');

        $onDay = $matches->filter(function (TournamentMatch $m) use ($tz, $dayKey): bool {
            if ($m->scheduled_at === null) {
                return false;
            }

            return $m->scheduled_at->timezone($tz)->format('Y-m-d') === $dayKey;
        })->values();

        $buckets = collect();
        $used = [];

        foreach ($onDay->whereNotNull('schedule_slot_ulid')->groupBy('schedule_slot_ulid') as $ulid => $group) {
            foreach ($group as $m) {
                $used[(int) $m->id] = true;
            }
            $buckets->push(self::normalizeSlotRow($tournament, $group));
        }

        $remaining = $onDay->filter(fn (TournamentMatch $m): bool => ! isset($used[(int) $m->id]))->values();

        foreach (
            $remaining->groupBy(function (TournamentMatch $m) use ($tz): string {
                $start = $m->scheduled_at?->timezone($tz)->format('Y-m-d H:i') ?? '';
                $end = $m->scheduled_ends_at?->timezone($tz)->format('Y-m-d H:i') ?? '';
                $round = (string) ($m->round_label ?? '');

                return $start.'|'.$end.'|'.$round;
            }) as $group
        ) {
            $buckets->push(self::normalizeSlotRow($tournament, $group));
        }

        return $buckets
            ->sortBy(fn (array $row): int => (int) ($row['sort_key'] ?? 0))
            ->values();
    }

    /**
     * @param  Collection<int, TournamentMatch>  $group
     * @return array<string, mixed>
     */
    public static function normalizeSlotRow(Tournament $tournament, Collection $group): array
    {
        $tz = self::tournamentTimezone($tournament);
        $pitchesOrdered = $tournament->pitches->sortBy(['sort_order', 'id'])->values();

        $ordered = $group->sortBy(function (TournamentMatch $m) use ($pitchesOrdered): array {
            $pitchOrder = $pitchesOrdered->pluck('id')->values()->all();
            $idx = array_search((int) ($m->pitch_id ?? 0), $pitchOrder, true);

            return [$idx === false ? 999 : $idx, (int) ($m->match_number ?? 0), (int) $m->id];
        })->values();

        $m1 = $ordered->get(0);
        $m2 = $ordered->get(1);

        $anchor = $m1 ?? $m2;
        $sortKey = $anchor?->scheduled_at?->getTimestamp() ?? 0;

        $ulid = $group->first()?->schedule_slot_ulid;
        $ids = $group->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values();
        $slotRouteKey = filled($ulid)
            ? (string) $ulid
            : 'm'.$ids->implode('-m');

        $timeLabel = self::formatTimeRangeLabel($m1, $m2, $tz);

        $roundLabel = (string) ($anchor?->round_label ?? '—');
        $roundNum = self::parseRoundNumberFromLabel($anchor?->round_label);

        $statuses = $group->pluck('status')->filter()->unique()->values();
        $rowUiStatus = $statuses->count() === 1
            ? self::mapMatchStatusToRowUi((string) $statuses->first())
            : null;

        $removable = self::slotRowIsRemovable($group);

        return [
            'slot_route_key' => $slotRouteKey,
            'schedule_slot_ulid' => filled($ulid) ? (string) $ulid : null,
            'pitch1_match' => $m1,
            'pitch2_match' => $m2,
            'pitch1_game_no' => $m1?->match_number,
            'pitch2_game_no' => $m2?->match_number,
            'pitch1_matchup' => self::matchupLabel($m1),
            'pitch2_matchup' => self::matchupLabel($m2),
            'round' => $roundNum,
            'round_label' => $roundLabel,
            'time_label' => $timeLabel,
            'row_ui_status' => $rowUiStatus,
            'removable' => $removable,
            'sort_key' => $sortKey,
        ];
    }

    /**
     * @param  Collection<int, TournamentMatch>  $group
     */
    public static function slotRowIsRemovable(Collection $group): bool
    {
        foreach ($group as $match) {
            if (! $match instanceof TournamentMatch) {
                continue;
            }

            if (in_array($match->status, [TournamentMatch::STATUS_LIVE, TournamentMatch::STATUS_COMPLETED], true)) {
                return false;
            }

            if ($match->home_score !== null || $match->away_score !== null) {
                return false;
            }

            if ($match->scoreLogs()->exists() || $match->playerStats()->exists() || $match->spiritScores()->exists()) {
                return false;
            }
        }

        return $group->isNotEmpty();
    }

    public static function matchupLabel(?TournamentMatch $match): string
    {
        if ($match === null) {
            return '—';
        }

        $home = $match->homeRegistration?->team?->name ?? '—';
        $away = $match->awayRegistration?->team?->name ?? '—';

        return $home.' vs '.$away;
    }

    public static function formatTimeRangeLabel(?TournamentMatch $m1, ?TournamentMatch $m2, string $tz): string
    {
        $anchor = $m1 ?? $m2;

        if ($anchor === null || $anchor->scheduled_at === null) {
            return '—';
        }

        $start = $anchor->scheduled_at->timezone($tz);
        $end = ($anchor->scheduled_ends_at ?? $m2?->scheduled_ends_at ?? $m1?->scheduled_ends_at);

        $left = $start->format('g:i A');

        if ($end === null) {
            return $left;
        }

        return $left.' – '.$end->timezone($tz)->format('g:i A');
    }

    /**
     * @return array{0: ?TournamentMatch, 1: ?TournamentMatch}
     */
    public static function resolveSlotMatchesFromKey(Tournament $tournament, string $slotKey): array
    {
        $tournament->loadMissing(['pitches' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);

        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $slotKey) === 1) {
            $group = TournamentMatch::query()
                ->where('tournament_id', $tournament->id)
                ->where('stage', 'round_robin')
                ->where('schedule_slot_ulid', $slotKey)
                ->orderBy('match_number')
                ->orderBy('id')
                ->get();

            return self::pairFromOrderedCollection($tournament, $group);
        }

        if (preg_match('/^m(\d+)-m(\d+)$/', $slotKey, $m) === 1) {
            $group = TournamentMatch::query()
                ->where('tournament_id', $tournament->id)
                ->where('stage', 'round_robin')
                ->whereIn('id', [(int) $m[1], (int) $m[2]])
                ->orderBy('match_number')
                ->orderBy('id')
                ->get();

            return self::pairFromOrderedCollection($tournament, $group);
        }

        return [null, null];
    }

    /**
     * @param  Collection<int, TournamentMatch>  $group
     * @return array{0: ?TournamentMatch, 1: ?TournamentMatch}
     */
    public static function pairFromOrderedCollection(Tournament $tournament, Collection $group): array
    {
        if ($group->isEmpty()) {
            return [null, null];
        }

        $row = self::normalizeSlotRow($tournament, $group);

        return [$row['pitch1_match'] ?? null, $row['pitch2_match'] ?? null];
    }

    /**
     * @return array{0: ?TournamentMatch, 1: ?TournamentMatch}
     */
    public static function resolveTrashedSlotMatchesFromKey(Tournament $tournament, string $slotKey): array
    {
        $tournament->loadMissing(['pitches' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);

        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $slotKey) === 1) {
            $group = TournamentMatch::onlyTrashed()
                ->where('tournament_id', $tournament->id)
                ->where('stage', 'round_robin')
                ->where('schedule_slot_ulid', $slotKey)
                ->orderBy('match_number')
                ->orderBy('id')
                ->get();

            return self::pairFromOrderedCollection($tournament, $group);
        }

        if (preg_match('/^m(\d+)-m(\d+)$/', $slotKey, $m) === 1) {
            $group = TournamentMatch::onlyTrashed()
                ->where('tournament_id', $tournament->id)
                ->where('stage', 'round_robin')
                ->whereIn('id', [(int) $m[1], (int) $m[2]])
                ->orderBy('match_number')
                ->orderBy('id')
                ->get();

            return self::pairFromOrderedCollection($tournament, $group);
        }

        return [null, null];
    }

    /**
     * Default field values for the “add schedule row” modal.
     *
     * @return array<string, mixed>
     */
    public static function defaultAddSlotForm(Tournament $tournament): array
    {
        $tournament->loadMissing(['pitches' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);
        $active = $tournament->pitches->where('is_active', true)->sortBy(['sort_order', 'id'])->values();
        $pitches = $active->isNotEmpty() ? $active : $tournament->pitches->sortBy(['sort_order', 'id'])->values();
        $regs = self::sortedRegistrations($tournament);

        $next = TournamentMatch::nextMatchNumberForTournament((int) $tournament->id);

        return [
            'round' => 1,
            'start_time' => '07:00',
            'end_time' => '07:40',
            'match1_match_number' => $next,
            'match2_match_number' => $next + 1,
            'pitch1_pitch_id' => $pitches->first()?->id,
            'pitch2_pitch_id' => ($pitches->get(1) ?? $pitches->first())?->id,
            'match1_home_registration_id' => $regs->get(0)?->id,
            'match1_away_registration_id' => $regs->get(1)?->id ?? $regs->get(0)?->id,
            'match2_home_registration_id' => $regs->get(2)?->id,
            'match2_away_registration_id' => $regs->get(3)?->id,
            'status' => 'upcoming',
        ];
    }

    /**
     * Round robin matches scheduled on a calendar day other than the configured Day 1 / Day 2 headers.
     *
     * @return Collection<int, TournamentMatch>
     */
    public static function roundRobinMatchesOutsideConfiguredDays(Tournament $tournament): Collection
    {
        $tz = self::tournamentTimezone($tournament);
        $d1 = self::roundRobinDayOneLocal($tournament)->format('Y-m-d');
        $d2 = self::roundRobinDayTwoLocal($tournament)->format('Y-m-d');

        return $tournament->matches
            ->filter(fn (TournamentMatch $m): bool => $m->stage === 'round_robin' && $m->scheduled_at !== null)
            ->filter(function (TournamentMatch $m) use ($tz, $d1, $d2): bool {
                $key = $m->scheduled_at->timezone($tz)->format('Y-m-d');

                return $key !== $d1 && $key !== $d2;
            })
            ->sortBy([
                fn (TournamentMatch $m) => $m->scheduled_at?->getTimestamp() ?? 0,
                fn (TournamentMatch $m) => $m->match_number ?? 0,
            ])
            ->values();
    }
}
