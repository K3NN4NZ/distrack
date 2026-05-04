<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    /**
     * Show all teams to administrator accounts.
     */
    public function index(Request $request): View
    {
        $teams = Team::query()
            ->with([
                'owner',
                'members' => fn ($query) => $query
                    ->whereIn('role', ['captain', 'spirit_captain'])
                    ->orderByRaw("case when role = 'captain' then 0 when role = 'spirit_captain' then 1 else 2 end"),
            ])
            ->withCount(['members', 'registrations'])
            ->withExists([
                'members as members_with_match_stats' => fn ($query) => $query->whereHas('matchStats'),
            ])
            ->latest()
            ->get();

        $selectedTeam = $teams->firstWhere('id', $request->integer('selected_team'))
            ?? $teams->first();

        $owners = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);

        return view('admin.teams.index', [
            'teams' => $teams,
            'selectedTeam' => $selectedTeam,
            'owners' => $owners,
        ]);
    }

    /**
     * Create a team from the admin directory.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->teamRules());

        $team = Team::query()->create($this->buildTeamPayload($request, $validated));

        return redirect()
            ->route('admin.teams.index', ['selected_team' => $team->id])
            ->with('status', 'team-created');
    }

    /**
     * Update a team from the admin directory.
     */
    public function update(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate($this->teamRules('edit_'));

        $team->update($this->buildTeamPayload($request, $validated, $team, 'edit_'));

        return redirect()
            ->route('admin.teams.index', ['selected_team' => $team->id])
            ->with('status', 'team-updated');
    }

    /**
     * Delete a team if it is not already tied to tournament records.
     */
    public function destroy(Team $team): RedirectResponse
    {
        if ($team->hasRecordedActivity()) {
            return redirect()
                ->route('admin.teams.index', ['selected_team' => $team->id])
                ->with('status', 'team-delete-blocked');
        }

        $team->deleteStoredLogo();
        $team->delete();

        return redirect()
            ->route('admin.teams.index')
            ->with('status', 'team-deleted');
    }

    /**
     * Team validation rules for create and edit flows.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function teamRules(string $prefix = ''): array
    {
        $field = fn (string $name): string => $prefix.$name;

        return [
            $field('owner_user_id') => ['required', 'integer', 'exists:users,id'],
            $field('name') => ['required', 'string', 'max:255'],
            $field('address') => ['required', 'string', 'max:500'],
            $field('city') => ['required', 'string', 'max:255'],
            $field('province') => ['required', 'string', 'max:255'],
            $field('country_name') => ['nullable', 'string', 'max:255'],
            $field('status') => ['required', Rule::in(['active', 'inactive', 'archived'])],
            $field('logo') => ['nullable', Rule::imageFile(allowSvg: true)->max(2048)],
            $field('remove_logo') => ['nullable', 'boolean'],
        ];
    }

    /**
     * Build a normalized team payload from validated input.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function buildTeamPayload(Request $request, array $validated, ?Team $team = null, string $prefix = ''): array
    {
        $field = fn (string $name): string => $prefix.$name;
        $logoPath = $team?->logo_path;
        $uploadedLogo = $request->file($field('logo'));

        if ($uploadedLogo) {
            $team?->deleteStoredLogo();
            $logoPath = $uploadedLogo->store('team-logos', 'public');
        } elseif ($team && $request->boolean($field('remove_logo'))) {
            $team->deleteStoredLogo();
            $logoPath = null;
        }

        return [
            'owner_user_id' => $validated[$field('owner_user_id')],
            'name' => trim((string) $validated[$field('name')]),
            'address' => trim((string) $validated[$field('address')]),
            'city' => trim((string) $validated[$field('city')]),
            'province' => trim((string) $validated[$field('province')]),
            'country_name' => $this->normalizeNullableString($validated[$field('country_name')] ?? null) ?? 'Philippines',
            'status' => $validated[$field('status')],
            'logo_path' => $uploadedLogo ? $logoPath : ($team ? $logoPath : null),
        ];
    }

    /**
     * Normalize optional text fields before persistence.
     */
    protected function normalizeNullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
