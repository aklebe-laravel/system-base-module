<?php

namespace Modules\SystemBase\app\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandResult;

class Password extends Command
{
    /**
     * The name and signature of the console command.
     * Don't use {--password=:the password or empty to ask} because special chars like '$' will be deleted!
     *
     * @var string
     */
    protected $signature = 'password:generate {--user=:Optional user id or email to assign this password. Otherwise a new password will be printed.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $password = $this->secret("Type the password you want to hash:");

        if ($id = $this->option('user')) {
            if ($user = app(User::class)->where('id', $id)->first()) {
                $user->password = $password;
                $user->update();
                $this->output->writeln(sprintf("User password was updated by id: %s", $id));
                return CommandResult::SUCCESS;
            }
            if ($user = app(User::class)->where('email', $id)->first()) {
                $user->password = $password;
                $user->update();
                $this->output->writeln(sprintf("User password was updated by email: %s", $id));
                return CommandResult::SUCCESS;
            }
        }

        $this->output->writeln('Generated password hash:');
        $this->output->writeln($password);
        return CommandResult::SUCCESS;
    }
}
