<?php

namespace App\Commands;

use App\Services\CredentialStore;
use App\Services\FlareUrlResolver;
use LaravelZero\Framework\Commands\Command;

class LogoutCommand extends Command
{
    protected $signature = 'logout';

    protected $description = 'Clear your stored Flare credentials';

    public function handle(CredentialStore $credentials, FlareUrlResolver $urlResolver): int
    {
        $credentials->flush();

        $this->info("Logged out of {$urlResolver->getHostKey()} successfully.");

        return self::SUCCESS;
    }
}
