<?php

use App\Support\TournamentPooling;

/**
 * Optional overrides — never required for the Pooling tab UI.
 *
 * `defaults_by_division.`*: Same schema as `tournaments.pooling_rules` when you want
 * division-wide templates (otherwise Pooling uses automatic winner/loser split).
 *
 * @see TournamentPooling::getPoolingRules()
 */
return [
    'defaults_by_division' => [
    ],
];
