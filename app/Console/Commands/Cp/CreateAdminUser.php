<?php

namespace App\Console\Commands\Cp;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminUser extends Command
{
    /** @var string */
    protected $signature = 'cp:admin {--email=} {--name=} {--password=}';

    /** @var string */
    protected $description = 'Create or update the single control panel admin user';

    public function handle(): int
    {
        $email = $this->option('email') ?: text('Email address', required: true);
        $name = $this->option('name') ?: text('Name', default: 'Taylor Drayson');
        $plainPassword = $this->option('password') ?: password('Password', required: true);

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $plainPassword],
        );

        $this->info("Admin user saved: {$user->email}");

        return self::SUCCESS;
    }
}
