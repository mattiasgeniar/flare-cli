<?php

namespace App\Commands;

use App\Services\CredentialStore;
use App\Services\FlareUrlResolver;
use LaravelZero\Framework\Commands\Command;

class AuthCommand extends Command
{
    protected $signature = 'auth';

    protected $description = 'Show the active Flare authentication context';

    public function handle(CredentialStore $credentials, FlareUrlResolver $urlResolver): int
    {
        $activeHost = $urlResolver->getHostKey();
        $configuredHosts = $credentials->getConfiguredHosts();
        $isConfigured = in_array($activeHost, $configuredHosts, true);

        $this->line("Active base URL: {$urlResolver->getApiBaseUrl()}");
        $this->line("Active host: {$activeHost}");
        $this->line('Active context: '.($isConfigured ? 'configured' : 'missing'));
        $this->newLine();
        $this->line('Stored auth contexts:');

        if ($configuredHosts === []) {
            $this->line('- none');

            return self::SUCCESS;
        }

        foreach ($configuredHosts as $host) {
            $suffix = $host === $activeHost ? ' (active)' : '';

            $this->line("- {$host}{$suffix}");
        }

        return self::SUCCESS;
    }
}
