<?php

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
                Route::get('/{tournament}/matches/{match}/pdf', 'exportMatchScoringPdf')->name('matches.pdf');
            });

            Route::middleware('can:access-admin')->group(function () {
                Route::post('/', 'storeTournament')->name('store');
                Route::put('/{tournament}', 'updateTournament')->name('update');
                Route::delete('/{tournament}', 'destroyTournament')->name('destroy');
                Route::post('/pitches', 'storePitch')->name('pitches.store');
                Route::put('/pitches/{pitch}', 'updatePitch')->name('pitches.update');
                Route::delete('/pitches/{pitch}', 'destroyPitch')->name('pitches.destroy');
                Route::post('/registrations/seed', 'seedRegistrations')->name('registrations.seed');
                Route::patch('/registrations/seeding', 'updateRegistrationSeeding')->name('registrations.seeding.update');
                Route::post('/{tournament}/bracket-ranking/apply', 'applyBracketRanking')->name('bracket-ranking.apply');
                Route::post('/registrations', 'storeRegistration')->name('registrations.store');
                Route::post('/matches/round-robin', 'generateRoundRobinMatches')->name('matches.round-robin.generate');
                Route::post('/{tournament}/matches/small-day1-schedule/sync', 'syncSmallDayOneRoundRobinSchedule')->name('matches.small-day1-schedule.sync');
                Route::patch('/{tournament}/matches/small-day1-slot-status', 'updateSmallDayOneRoundRobinSlotStatus')->name('matches.small-day1-slot-status.update');
                Route::post('/{tournament}/matches/small-day2-schedule/sync', 'syncSmallDayTwoRoundRobinSchedule')->name('matches.small-day2-schedule.sync');
                Route::patch('/{tournament}/matches/small-day2-slot-status', 'updateSmallDayTwoRoundRobinSlotStatus')->name('matches.small-day2-slot-status.update');
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
        ->prefix('admin/teams')
        ->name('admin.teams.')
        ->controller(AdminTeamController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('/{team}', 'update')->name('update');
            Route::delete('/{team}', 'destroy')->name('destroy');
        });
});

require __DIR__.'/settings.php';
