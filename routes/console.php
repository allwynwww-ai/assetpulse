<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Container start-up seeding (see docker/entrypoint.sh). Idempotent: does nothing once any user exists.
 *  - DEMO_SEED=true                 -> full demo data set (DatabaseSeeder)
 *  - ADMIN_EMAIL + ADMIN_PASSWORD   -> just a platform Super Admin, who then creates companies in the UI
 */
Artisan::command('app:seed-if-empty', function () {
    if (\App\Models\User::query()->withoutGlobalScopes()->exists()) {
        $this->info('Database already has users - skipping seed.');

        return 0;
    }
    if (filter_var(env('DEMO_SEED', false), FILTER_VALIDATE_BOOL)) {
        $this->info('Empty database - seeding demo data (DEMO_SEED=true)...');

        return $this->call('db:seed', ['--force' => true]);
    }
    $email = env('ADMIN_EMAIL');
    $password = env('ADMIN_PASSWORD');
    if ($email && $password) {
        $u = \App\Models\User::create([
            'company_id' => null, 'role' => \App\Enums\Role::SuperAdmin->value, 'name' => 'Platform Super Admin',
            'email' => $email, 'password' => \Illuminate\Support\Facades\Hash::make($password),
        ]);
        $u->forceFill(['email_verified_at' => now()])->save();
        $this->info("Empty database - created Super Admin {$email}.");

        return 0;
    }
    $this->warn('Empty database and neither DEMO_SEED=true nor ADMIN_EMAIL/ADMIN_PASSWORD set - nobody can log in yet.');

    return 0;
})->purpose('Seed demo data or a Super Admin when the database has no users');
