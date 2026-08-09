<?php // app/Console/Commands/MakeAdminCommand.php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeAdminCommand extends Command
{
    protected $signature = 'shop:make-admin
                            {--name= : The operator\'s name}
                            {--email= : The operator\'s email address}
                            {--password= : The operator\'s password}';

    protected $description = 'Create an operator account that can access the admin panel';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password');

        if (User::where('email', $email)->exists()) {
            $this->error("A user with the email {$email} already exists.");

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_operator' => true,
        ]);

        $this->info("Operator {$email} created. Sign in at /admin.");

        return self::SUCCESS;
    }
}
