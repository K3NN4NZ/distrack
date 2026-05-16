<?php

namespace App\Support;

use App\Models\TeamMember;
use Illuminate\Support\Collection;

final class MatchScoreSheetRosterGroups
{
    /**
     * Gender blocks for match score sheets — same grouping as {@see resources/views/admin/tournaments/scoring.blade.php}.
     *
     * @param  Collection<int, TeamMember>  $members
     * @return Collection<int, array{label: string, roster: Collection<int, TeamMember>}>
     */
    public static function fromMembers(Collection $members): Collection
    {
        $sheetMaleMembers = $members->filter(fn ($member) => strtolower((string) $member->gender) === 'male')->values();
        $sheetFemaleMembers = $members->filter(fn ($member) => strtolower((string) $member->gender) === 'female')->values();
        $sheetOtherMembers = $members
            ->filter(fn ($member) => ! in_array(strtolower((string) $member->gender), ['male', 'female'], true))
            ->values();

        $sheetGenderGroups = collect([
            ['label' => __('MALE'), 'roster' => $sheetMaleMembers],
            ['label' => __('FEMALE'), 'roster' => $sheetFemaleMembers],
        ]);

        if ($sheetOtherMembers->isNotEmpty()) {
            $sheetGenderGroups->push([
                'label' => __('OTHER'),
                'roster' => $sheetOtherMembers,
            ]);
        }

        return $sheetGenderGroups;
    }
}
