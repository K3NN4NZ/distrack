<?php

namespace App\Http\Controllers;

use App\Models\TeamMember;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    /**
     * Show the captain team and roster management page.
     */
    public function index(Request $request): View
    {
        $teams = $request->user()
            ->ownedTeams()
            ->with(['members' => fn ($query) => $query->withCount('matchStats')])
            ->withCount(['members', 'registrations'])
            ->latest()
            ->get();

        $selectedTeam = $teams->firstWhere('id', $request->integer('team'))
            ?? $teams->first();

        return view('teams.index', [
            'teams' => $teams,
            'selectedTeam' => $selectedTeam,
        ]);
    }

    /**
     * Show captains the tournaments currently open for team registration.
     */
    public function tournaments(Request $request): View
    {
        $ownedTeams = $request->user()
            ->ownedTeams()
            ->withCount(['members', 'registrations'])
            ->orderBy('name')
            ->get();

        $tournaments = Tournament::query()
            ->where('status', 'registration')
            ->where(function ($query): void {
                $query->whereNull('registration_deadline')
                    ->orWhere('registration_deadline', '>=', now());
            })
            ->with([
                'registrations' => fn ($query) => $query
                    ->with('team:id,name,owner_user_id')
                    ->orderBy('id'),
            ])
            ->withCount('registrations')
            ->upcomingFirst()
            ->get();

        return view('teams.tournaments', [
            'ownedTeams' => $ownedTeams,
            'tournaments' => $tournaments,
        ]);
    }

    /**
     * Create a new team owned by the authenticated user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string', 'max:255'],
            'country_name' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', Rule::imageFile(allowSvg: true)->max(2048)],
        ]);

        $team = $request->user()->ownedTeams()->create([
            'name' => $validated['name'],
            'address' => $validated['address'],
            'city' => $validated['city'],
            'province' => $validated['province'],
            'country_name' => $validated['country_name'] ?: 'Philippines',
            'logo_path' => $request->file('logo')?->store('team-logos', 'public'),
        ]);

        return redirect()
            ->route('teams.index', ['team' => $team->id])
            ->with('status', 'team-created');
    }

    /**
     * Add a roster member to one of the authenticated user's teams.
     */
    public function storeMember(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'name' => ['required', 'string', 'max:255'],
            'nickname' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', 'max:50'],
            'age' => ['required', 'integer', 'min:1', 'max:99'],
            'address' => ['required', 'string', 'max:500'],
            'role' => ['required', Rule::in(['captain', 'spirit_captain', 'member'])],
        ]);

        $team = $request->user()
            ->ownedTeams()
            ->findOrFail($validated['team_id']);

        TeamMember::create([
            'team_id' => $team->id,
            'name' => $validated['name'],
            'nickname' => $validated['nickname'],
            'gender' => $validated['gender'],
            'age' => $validated['age'],
            'address' => $validated['address'],
            'role' => $validated['role'],
        ]);

        return redirect()
            ->route('teams.index', ['team' => $team->id])
            ->with('status', 'member-created');
    }

    /**
     * Delete a roster member from one of the authenticated user's teams.
     */
    public function destroyMember(Request $request, TeamMember $teamMember): RedirectResponse
    {
        $team = $request->user()
            ->ownedTeams()
            ->findOrFail($teamMember->team_id);

        if ($teamMember->matchStats()->exists()) {
            return redirect()
                ->route('teams.index', ['team' => $team->id])
                ->with('status', 'member-delete-blocked');
        }

        $teamMember->delete();

        return redirect()
            ->route('teams.index', ['team' => $team->id])
            ->with('status', 'member-deleted');
    }

    /**
     * Register one of the authenticated captain's teams into an available tournament.
     */
    public function storeTournamentRegistration(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
                'team_id' => ['required', 'integer', 'exists:teams,id'],
            ],
            [
                'team_id.required' => 'Choose one of your teams before registering.',
            ],
        );

        $team = $request->user()
            ->ownedTeams()
            ->findOrFail($validated['team_id']);

        $tournament = Tournament::query()
            ->whereKey($validated['tournament_id'])
            ->where('status', 'registration')
            ->where(function ($query): void {
                $query->whereNull('registration_deadline')
                    ->orWhere('registration_deadline', '>=', now());
            })
            ->firstOrFail();

        if (TournamentRegistration::query()
            ->where('tournament_id', $tournament->id)
            ->where('team_id', $team->id)
            ->exists()
        ) {
            return redirect()
                ->route('captain.tournaments.index')
                ->with('status', 'team-already-registered');
        }

        TournamentRegistration::create([
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'status' => 'pending',
            'seed_number' => null,
            'bracket_code' => null,
            'bracket_rank' => null,
            'pool_name' => null,
        ]);

        return redirect()
            ->route('captain.tournaments.index')
            ->with('status', 'team-registered');
    }
}
