<?php

use App\Providers\AppServiceProvider;
use App\Services\CredentialStore;
use App\Services\FlareUrlResolver;
use Spatie\OpenApiCli\OpenApiCli;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/flare-cli-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);
    $_SERVER['HOME'] = $this->tempDir;

    $this->originalBaseUrl = getenv('FLARE_BASE_URL') ?: null;
    putenv('FLARE_BASE_URL');
    unset($_SERVER['FLARE_BASE_URL']);
});

afterEach(function () {
    if ($this->originalBaseUrl === null) {
        putenv('FLARE_BASE_URL');
        unset($_SERVER['FLARE_BASE_URL']);
    } else {
        putenv("FLARE_BASE_URL={$this->originalBaseUrl}");
        $_SERVER['FLARE_BASE_URL'] = $this->originalBaseUrl;
    }

    OpenApiCli::clearRegistrations();
    app()->forgetInstance(CredentialStore::class);
    app()->forgetInstance(FlareUrlResolver::class);
    (new AppServiceProvider($this->app))->boot();

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

it('registers the OpenAPI commands with the active base URL and auth context', function () {
    putenv('FLARE_BASE_URL=https://ingress-staging.flareapp.io/api/');
    $_SERVER['FLARE_BASE_URL'] = 'https://ingress-staging.flareapp.io/api/';

    $store = new CredentialStore(new FlareUrlResolver);
    $store->setToken('staging-token');

    OpenApiCli::clearRegistrations();
    app()->forgetInstance(CredentialStore::class);
    app()->forgetInstance(FlareUrlResolver::class);
    $this->app->instance(CredentialStore::class, $store);
    $this->app->instance(FlareUrlResolver::class, new FlareUrlResolver);

    (new AppServiceProvider($this->app))->boot();

    $registration = OpenApiCli::getRegistrations()[0];

    expect($registration->getSpecPath())->toBe('https://flareapp.io/downloads/flare-api.yaml');
    expect($registration->getBaseUrl())->toBe('https://staging.flareapp.io/api');

    $store->setToken('late-staging-token');

    expect(($registration->getAuthCallable())())->toBe('late-staging-token');
});
