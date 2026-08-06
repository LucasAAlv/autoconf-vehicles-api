<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:promote-admin {email : The email of the user to promote}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grant the global is_admin flag to an existing user, identified by email';

    /**
     * Execute the console command.
     *
     * CLI-only, on purpose: this never touches the HTTP surface, so it can't
     * reopen the privilege-escalation path that POST /auth/register closes
     * (is_admin is never settable by an unauthenticated caller).
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        if ($user->is_admin) {
            $this->comment("User [{$email}] is already an admin.");

            return self::SUCCESS;
        }

        // `is_admin` is deliberately absent from User::$fillable (it must
        // never be mass-assignable from request input), so it's set as a
        // direct attribute instead of via update()/fill(), which would
        // silently discard it.
        $user->is_admin = true;
        $user->save();

        $this->info("User [{$email}] promoted to admin.");

        return self::SUCCESS;
    }
}
