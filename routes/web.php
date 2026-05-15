<?php

use App\Http\Controllers\Admin\AdminTeamRosterController;
use App\Http\Controllers\Admin\RoundRobinScheduleController;
use App\Http\Controllers\Admin\TeamController as AdminTeamController;
use App\Http\Controllers\Admin\TournamentController;
use App\Http\Controllers\PhilippineLocationController;
use App\Http\Controllers\PublicTournamentController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::controller(PublicTournamentController::class)->group(function () {
    Route::get('/', 'index')->name('home');
    Route::get('tournaments', 'index')->name('tournaments.index');
    Route::get('tournaments/{tournament:slug}', 'show')->name('tournaments.show');
    Route::get('tournaments/{tournament:slug}/teams/{team}', 'showTeam')->name('tournaments.teams.show');
    Route::get('tournaments/{tournament:slug}/matches/{match}', 'showMatch')->name('tournaments.matches.show');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('admin', function () {
        $user = auth()->user();

        return $user->isAdmin()
            ? redirect()->route('admin.tournaments.index')
            : redirect()->route('admin.tournaments.list');
    })->middleware('can:access-scoring')->name('admin.index');

    Route::prefix('api/philippine-locations')
        ->name('locations.')
        ->controller(PhilippineLocationController::class)
        ->group(function () {
            Route::get('/provinces', 'provinces')->name('provinces');
            Route::get('/cities', 'cities')->name('cities');
            Route::get('/barangays', 'barangays')->name('barangays');
        });

    Route::middleware('can:manage-teams')->group(function () {
        Route::livewire('teams', 'pages::teams.index')->name('teams.index');

        Route::controller(TeamController::class)->group(function () {
            Route::get('captain/tournaments', 'tournaments')->name('captain.tournaments.index');
            Route::post('teams', 'store')->name('teams.store');
            Route::post('teams/members', 'storeMember')->name('teams.members.store');
            Route::delete('teams/members/{teamMember}', 'destroyMember')->name('teams.members.destroy');
            Route::post('captain/tournaments/registrations', 'storeTournamentRegistration')->name('captain.tournaments.registrations.store');
        });
    });

    Route::prefix('admin/tournaments')
        ->name('admin.tournaments.')
        ->controller(TournamentController::class)
        ->group(function () {
            Route::middleware('can:access-scoring')->group(function () {
                Route::get('/list', 'list')->name('list');
                Route::get('/', 'index')->name('index');
            });

            Route::middleware(['can:enter-scores', 'scorekeeper.owns-assigned-match-pitch'])->group(function () {
                Route::get('/matches/{match}', 'redirectToMatchScoring')->name('matches.scoring.shortcut');
                Route::get('/{tournament}/matches/{match}/scoring', 'showMatchScoring')->name('matches.scoring');
                Route::post('/{tournament}/matches/{match}/scoring', 'storeMatchScoreLog')->name('matches.scoring.store');
                Route::delete('/{tournament}/matches/{match}/scoring/{scoreLog}', 'destroyMatchScoreLog')->name('matches.scoring.destroy');
                Route::patch('/{tournament}/matches/{match}/scoring/player-stats', 'updateMatchPlayerStat')->name('matches.scoring.player-stats.update');
                Route::post('/{tournament}/matches/{match}/scoring/spirit', 'storeMatchSpiritScores')->name('matches.scoring.spirit.store');
                Route::patch('/{tournament}/matches/{match}/spirit-scores', 'patchMatchSpiritScores')->name('matches.scoring.spirit-scores.patch');
                Route::get('/{tournament}/matches/{match}/pdf', 'exportMatchScoringPdf')->name('matches.pdf');
                Route::get('/{tournament}/matches/{match}/spirit-pdf', 'exportMatchSpiritScoringPdf')->name('matches.spirit-pdf');
            });

            Route::middleware('can:access-admin')->group(function () {
                Route::post('/', 'storeTournament')->name('store');
                Route::put('/{tournament}', 'updateTournament')->name('update');
                Route::delete('/{tournament}', 'destroyTournament')->name('destroy');
                Route::post('/pitches', 'storePitch')->name('pitches.store');
                Route::put('/pitches/{pitch}', 'updatePitch')->name('pitches.update');
                Route::delete('/pitches/{pitch}', 'destroyPitch')->name('pitches.destroy');
                Route::patch('/{tournament}/seeds', 'updateManualTournamentSeeds')->name('seeds.update');
                Route::post('/{tournament}/seeds/fill-empty', 'fillEmptyTournamentSeeds')->name('seeds.fill-empty');
                Route::patch('/registrations/seeding', 'updateRegistrationSeeding')->name('registrations.seeding.update');
                Route::post('/{tournament}/bracket-ranking/apply', 'applyBracketRanking')->name('bracket-ranking.apply');
                Route::post('/{tournament}/round-robin/schedules', [RoundRobinScheduleController::class, 'store'])->name('round-robin.schedules.store');
                Route::patch('/{tournament}/round-robin/schedules/{slot}', [RoundRobinScheduleController::class, 'update'])->name('round-robin.schedules.update')->where('slot', '([0-9A-HJKMNP-TV-Z]{26}|m\d+-m\d+)');
                Route::patch('/{tournament}/round-robin/schedules/{slot}/status', [RoundRobinScheduleController::class, 'updateRowStatus'])->name('round-robin.schedules.status')->where('slot', '([0-9A-HJKMNP-TV-Z]{26}|m\d+-m\d+)');
                Route::delete('/{tournament}/round-robin/schedules/{slot}', [RoundRobinScheduleController::class, 'destroy'])->name('round-robin.schedules.destroy')->where('slot', '([0-9A-HJKMNP-TV-Z]{26}|m\d+-m\d+)');
                Route::patch('/{tournament}/round-robin/schedules/{slot}/restore', [RoundRobinScheduleController::class, 'restore'])->name('round-robin.schedules.restore')->where('slot', '([0-9A-HJKMNP-TV-Z]{26}|m\d+-m\d+)');
                Route::post('/registrations', 'storeRegistration')->name('registrations.store');
                Route::post('/matches/round-robin', 'generateRoundRobinMatches')->name('matches.round-robin.generate');
                Route::patch('/{tournament}/matches/{match}/status', 'updateRoundRobinMatchStatus')->name('matches.status.update');
                Route::post('/matches/crossover/generate', 'generateCrossoverSchedule')->name('matches.crossover.generate');
                Route::post('/{tournament}/matches/quarter-finals/generate', 'generateQuarterFinalMatches')->name('matches.quarter-finals.generate');
                Route::get('/{tournament}/pooling-board', 'poolingBoard')->name('pooling-board');
                Route::post('/{tournament}/pooling/auto', 'applyAutomaticPooling')->name('pooling.auto');
                Route::post('/{tournament}/pooling/manual', 'saveManualPooling')->name('pooling.manual');
                Route::post('/{tournament}/pooling/clear', 'clearPoolingAssignments')->name('pooling.clear');
                Route::post('/{tournament}/matches/crossover/clear-pitch-assignments', 'clearCrossoverPitchAssignments')->name('matches.crossover.clear-pitch-assignments');
                Route::post('/matches', 'storeMatch')->name('matches.store');
                Route::put('/matches/{match}', 'updateMatch')->name('matches.update');
                Route::post('/matches/{match}/crossover-unassign-pitch', 'unassignCrossoverMatchPitch')->name('matches.crossover-unassign-pitch');
                Route::delete('/matches/{match}', 'destroyMatch')->name('matches.destroy');
                Route::post('/crews', 'storeCrew')->name('crews.store');
            });
        });

    Route::middleware('can:access-admin')
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::get('teams', [AdminTeamController::class, 'index'])->name('teams.index');
            Route::post('teams', [AdminTeamController::class, 'store'])->name('teams.store');
            Route::get('teams/{team}', [AdminTeamController::class, 'show'])->name('teams.show');
            Route::get('teams/{team}/edit', [AdminTeamController::class, 'edit'])->name('teams.edit');
            Route::match(['put', 'patch'], 'teams/{team}', [AdminTeamController::class, 'update'])->name('teams.update');
            Route::delete('teams/{team}', [AdminTeamController::class, 'destroy'])->name('teams.destroy');

            Route::get('teams/{team}/roster', [AdminTeamRosterController::class, 'index'])->name('teams.roster.index');
            Route::post('teams/{team}/roster/upload', [AdminTeamRosterController::class, 'upload'])->name('teams.roster.upload');
            Route::post('teams/{team}/roster', [AdminTeamRosterController::class, 'store'])->name('teams.roster.store');
            Route::patch('teams/{team}/roster/{teamMember}', [AdminTeamRosterController::class, 'update'])->name('teams.roster.update');
            Route::delete('teams/{team}/roster/{teamMember}', [AdminTeamRosterController::class, 'destroy'])->name('teams.roster.destroy');
        });
});

require __DIR__.'/settings.php';
