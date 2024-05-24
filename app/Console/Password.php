<?php

namespace Modules\SystemBase\app\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class Password extends Command
{
    /**
     * The name and signature of the console command.
     * Don't use {--password=:the password or empty to ask} because special chars like '$' will be deleted!
     *
     * @var string
     */
    protected $signature = 'password:generate {--user=:Optional user id or email to assign this password. Otherwise password will be printed.}';

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
    public function handle()
    {
        $pass = $this->secret("Type the password you want to hash:");
        $password = Hash::make($pass);

        if ($id = $this->option('user')) {
            if ($user = app(User::class)->where('id', $id)->first()) {
                $user->password = $password;
                $user->update();
                $this->output->writeln(sprintf("User password was updated by id: %s", $id));
                return Command::SUCCESS;
            }
            if ($user = app(User::class)->where('email', $id)->first()) {
                $user->password = $password;
                $user->update();
                $this->output->writeln(sprintf("User password was updated by email: %s", $id));
                return Command::SUCCESS;
            }
        }

        $this->output->writeln('Generated password hash:');
        $this->output->writeln($password);
        return Command::SUCCESS;
    }
}
