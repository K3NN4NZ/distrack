<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Controllers\Admin\TournamentController;
use App\Models\TournamentMatch;

/**
 * Status labels and filter chips for admin tournament tabs (Blade — not Livewire).
 *
 * Query tabs are resolved in {@see TournamentController::index}
 * and {@see resources/views/admin/tournaments/index.blade.php} from {@code ?tab=} and {@code ?tournament=}.
 */
final class AdminTournamentTabStatusPresentation
{
    public const TAB_QUARTER_FINAL = 'quarter-final';

    /**
     * Human-readable status option titles keyed by admin tab (query {@code tab=}).
     * Persisted match statuses remain {@code scheduled}, {@code live}, {@code completed}.
     *
     * @return array<string, list<string>>
     */
    public static function statusOptionsByTab(): array
    {
        return [
            self::TAB_QUARTER_FINAL => [
                (string) __('Upcoming'),
                (string) __('Live'),
                (string) __('Completed'),
            ],
        ];
    }

    /**
     * Primary match-status filters for the games dashboard.
     * Filter keys match {@see TournamentMatch::$status} / {@code data-game-status}.
     *
     * @return list<array{filter: string, label: string}>
     */
    public static function gameDashboardPrimaryStatusChips(?string $tab): array
    {
        $byTab = [
            self::TAB_QUARTER_FINAL => [
                ['filter' => 'scheduled', 'label' => __('Upcoming')],
                ['filter' => 'live', 'label' => __('Live')],
                ['filter' => 'completed', 'label' => __('Completed')],
            ],
        ];

        return $byTab[$tab] ?? [
            ['filter' => 'scheduled', 'label' => __('Scheduled')],
            ['filter' => 'live', 'label' => __('Live')],
            ['filter' => 'completed', 'label' => __('Completed')],
        ];
    }

    /**
     * Small Day 2 knockout match card: persisted values are still scheduled/live/completed.
     *
     * @return array<string, string>
     */
    public static function knockoutMatchStatusSelectOptions(): array
    {
        return [
            'scheduled' => __('Upcoming'),
            'live' => __('Live'),
            'completed' => __('Completed'),
        ];
    }

    public static function statusBadgeLabel(string $status, ?string $tab = null): string
    {
        if ($tab === self::TAB_QUARTER_FINAL) {
            return match ($status) {
                'scheduled' => (string) __('Upcoming'),
                'live' => (string) __('Live'),
                'completed' => (string) __('Completed'),
                default => str($status)->headline()->toString(),
            };
        }

        return str($status)->headline()->toString();
    }
}
