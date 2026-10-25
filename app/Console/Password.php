<?php

namespace Modules\SystemBase\app\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Command\Command as CommandResult;

/**
 * Asks for a password and either assigns it to a user or prints its hash.
 */
class Password extends Command
{
    /**
     * The name and signature of the console command.
     * The password is asked interactively (secret) on purpose:
     * a {--password=} option would lose special chars like '$' in the shell.
     *
     * @var string
     */
    protected $signature = 'password:generate {--user= : Optional user id or email to assign this password. Otherwise the password hash is printed.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hash a password or assign it to a user';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $password = (string) $this->secret("Type the password you want to hash:");
        if ($password === '') {
            $this->error('Empty password.');

            return CommandResult::FAILURE;
        }

        if ($id = $this->option('user')) {
            // by id first, then by email
            foreach (['id', 'email'] as $column) {
                if ($user = app('user')->where($column, $id)->first()) {
                    // the user model hashes the password (cast 'hashed')
                    $user->password = $password;
                    $user->update();
                    $this->output->writeln(sprintf("User password was updated by %s: %s", $column, $id));

                    return CommandResult::SUCCESS;
                }
            }

            $this->error(sprintf("User not found: %s", $id));

            return CommandResult::FAILURE;
        }

        $this->output->writeln('Generated password hash:');
        $this->output->writeln(Hash::make($password));

        return CommandResult::SUCCESS;
    }
}
