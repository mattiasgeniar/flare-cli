<?php

use App\Services\CredentialStore;
use App\Services\FlareUrlResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/flare-cli-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);
    $_SERVER['HOME'] = $this->tempDir;

    $this->originalBaseUrl = getenv('FLARE_BASE_URL') ?: null;
    putenv('FLARE_BASE_URL');
    unset($_SERVER['FLARE_BASE_URL']);

    $this->store = new CredentialStore(new FlareUrlResolver);
    $this->app->instance(CredentialStore::class, $this->store);
});

afterEach(function () {
    if ($this->originalBaseUrl === null) {
        putenv('FLARE_BASE_URL');
        unset($_SERVER['FLARE_BASE_URL']);
    } else {
        putenv("FLARE_BASE_URL={$this->originalBaseUrl}");
        $_SERVER['FLARE_BASE_URL'] = $this->originalBaseUrl;
    }

    $configFile = $this->tempDir.'/.flare/config.json';
    if (file_exists($configFile)) {
        unlink($configFile);
    }
    if (is_dir($this->tempDir.'/.flare')) {
        rmdir($this->tempDir.'/.flare');
    }
    if (is_dir($this->tempDir)) {
        rmdir($this->tempDir);
    }
});

it('stores credentials on successful login', function () {
    Http::fake([
        'flareapp.io/api/me' => Http::response([
            'id' => 20,
            'name' => 'Alex',
            'email' => 'alex@spatie.be',
            'teams' => [],
        ]),
    ]);

    $this->artisan('login')
        ->expectsQuestion('Enter your Flare API token', 'valid-token-123')
        ->expectsOutputToContain('Successfully logged in as alex@spatie.be')
        ->assertExitCode(0);

    expect($this->store->getToken())->toBe('valid-token-123');
});

it('shows error and does not store token on invalid token', function () {
    Http::fake([
        'flareapp.io/api/me' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $this->artisan('login')
        ->expectsQuestion('Enter your Flare API token', 'invalid-token')
        ->expectsOutput('Invalid API token.')
        ->assertExitCode(1);

    expect($this->store->getToken())->toBeNull();
});

it('validates the token against the active base URL', function () {
    putenv('FLARE_BASE_URL=https://ingress-staging.flareapp.io/api/');
    $_SERVER['FLARE_BASE_URL'] = 'https://ingress-staging.flareapp.io/api/';

    Http::fake([
        'staging.flareapp.io/api/me' => Http::response([
            'email' => 'alex+staging@spatie.be',
        ]),
    ]);

    $this->artisan('login')
        ->expectsQuestion('Enter your Flare API token', 'staging-token-123')
        ->expectsOutputToContain('https://staging.flareapp.io/api')
        ->expectsOutputToContain('https://staging.flareapp.io/account/api-tokens')
        ->expectsOutputToContain('Successfully logged in as alex+staging@spatie.be')
        ->assertExitCode(0);

    expect($this->store->getToken())->toBe('staging-token-123');
});

it('shows connection error on network failure', function () {
    Http::fake([
        'flareapp.io/api/me' => function () {
            throw new ConnectionException('Connection refused');
        },
    ]);

    $this->artisan('login')
        ->expectsQuestion('Enter your Flare API token', 'some-token')
        ->expectsOutput('Could not connect to Flare. Please check your internet connection.')
        ->assertExitCode(1);

    expect($this->store->getToken())->toBeNull();
});
