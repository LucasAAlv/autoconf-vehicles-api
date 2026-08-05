<?php

use Illuminate\Support\Facades\Schema;

it('uses pgsql as the default database connection', function () {
    expect(config('database.default'))->toBe('pgsql');
});

it('has every table the baseline migrations create', function () {
    foreach (['migrations', 'users', 'sessions', 'personal_access_tokens'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Expected table [{$table}] to exist.");
    }
});

it('does not have tables removed from the Laravel skeleton', function () {
    // No password-reset flow in scope, QUEUE_CONNECTION=sync and CACHE_STORE=file
    // in the real app, so cache/queue/password-reset tables are dead weight.
    foreach (['password_reset_tokens', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse("Expected table [{$table}] not to exist.");
    }
});
