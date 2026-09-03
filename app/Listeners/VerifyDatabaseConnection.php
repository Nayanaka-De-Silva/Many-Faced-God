<?php

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;

/**
 * Makes a green /up mean the database is actually reachable.
 *
 * Laravel's health route answers 200 without touching any dependency, so it
 * stayed green through issue #74 while every DB-backed page threw
 * "Connection refused". Letting the PDO failure bubble out of this listener
 * turns /up into a 500, which is what the deploy smoke gate keys off.
 */
class VerifyDatabaseConnection
{
    public function handle(DiagnosingHealth $event): void
    {
        DB::connection()->getPdo();
    }
}
