<?php

namespace App\Services;

class CredentialStore
{
    private string $configPath;

    public function __construct(
        private readonly FlareUrlResolver $urlResolver = new FlareUrlResolver
    ) {
        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? '';

        $this->configPath = "{$home}/.flare/config.json";
    }

    public function getToken(): ?string
    {
        return $this->readTokens()[$this->urlResolver->getHostKey()] ?? null;
    }

    public function setToken(string $token): void
    {
        $this->ensureConfigDirectoryExists();

        $tokens = $this->readTokens();
        $tokens[$this->urlResolver->getHostKey()] = $token;

        $this->writeTokens($tokens);
    }

    public function flush(): void
    {
        if (! file_exists($this->configPath)) {
            return;
        }

        $this->ensureConfigDirectoryExists();

        $tokens = $this->readTokens();
        unset($tokens[$this->urlResolver->getHostKey()]);

        $this->writeTokens($tokens);
    }

    /**
     * @return array<int, string>
     */
    public function getConfiguredHosts(): array
    {
        return array_keys($this->readTokens());
    }

    private function ensureConfigDirectoryExists(): void
    {
        $directory = dirname($this->configPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    /** @return array<string, mixed> */
    private function readConfig(): array
    {
        if (! file_exists($this->configPath)) {
            return [];
        }

        return json_decode(file_get_contents($this->configPath), true) ?? [];
    }

    /**
     * @return array<string, string>
     */
    private function readTokens(): array
    {
        $data = $this->readConfig();
        $tokens = $data['tokens'] ?? [];

        if (! is_array($tokens)) {
            $tokens = [];
        }

        $tokens = array_filter(
            $tokens,
            fn (mixed $token, mixed $host): bool => is_string($host) && is_string($token) && $token !== '',
            ARRAY_FILTER_USE_BOTH,
        );

        if (
            isset($data['token']) &&
            is_string($data['token']) &&
            $data['token'] !== '' &&
            ! array_key_exists('flareapp.io', $tokens)
        ) {
            $tokens['flareapp.io'] = $data['token'];
        }

        ksort($tokens);

        return $tokens;
    }

    /**
     * @param  array<string, string>  $tokens
     */
    private function writeTokens(array $tokens): void
    {
        ksort($tokens);

        $data = $this->readConfig();
        unset($data['token']);
        $data['tokens'] = $tokens === [] ? (object) [] : $tokens;

        file_put_contents(
            $this->configPath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }
}
