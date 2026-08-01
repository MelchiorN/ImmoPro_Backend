<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Planification automatique ─────────────────────────────────────────────────

// Vérification SLA : dossiers sans agent ou sans progression
Schedule::command('sla:check')->everyFifteenMinutes();

// Rappels de visite : agent + propriétaire (email + push + in-app)
Schedule::command('visites:rappels')->everyFifteenMinutes();
