<?php

use App\Services\CredentialStore;
use App\Services\FlareUrlResolver;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/flare-cli-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);

    // Override HOME so CredentialStore uses temp directory
    $_SERVER['HOME'] = $this->tempDir;

    $this->originalBaseUrl = getenv('FLARE_BASE_URL') ?: null;
    putenv('FLARE_BASE_URL');
    unset($_SERVER['FLARE_BASE_URL']);

    $this->resolver = new FlareUrlResolver;
    $this->store = new CredentialStore($this->resolver);
});

afterEach(function () {
    if ($this->originalBaseUrl === null) {
        putenv('FLARE_BASE_URL');
        unset($_SERVER['FLARE_BASE_URL']);
    } else {
        putenv("FLARE_BASE_URL={$this->originalBaseUrl}");
        $_SERVER['FLARE_BASE_URL'] = $this->originalBaseUrl;
    }

    // Clean up temp directory
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

it('returns null when no config file exists', function () {
    expect($this->store->getToken())->toBeNull();
});

it('stores and retrieves a token', function () {
    $this->store->setToken('test-api-token-123');

    expect($this->store->getToken())->toBe('test-api-token-123');
});

it('stores tokens per host context', function () {
    $this->store->setToken('first-token');

    putenv('FLARE_BASE_URL=https://ingress-staging.flareapp.io/api');
    $_SERVER['FLARE_BASE_URL'] = 'https://ingress-staging.flareapp.io/api';

    $stagingStore = new CredentialStore(new FlareUrlResolver);
    $stagingStore->setToken('second-token');

    expect($stagingStore->getToken())->toBe('second-token');

    putenv('FLARE_BASE_URL');
    unset($_SERVER['FLARE_BASE_URL']);

    expect((new CredentialStore(new FlareUrlResolver))->getToken())->toBe('first-token');
});

it('flushes only the active host context', function () {
    $this->store->setToken('production-token');

    putenv('FLARE_BASE_URL=https://ingress-staging.flareapp.io/api');
    $_SERVER['FLARE_BASE_URL'] = 'https://ingress-staging.flareapp.io/api';

    $stagingStore = new CredentialStore(new FlareUrlResolver);
    $stagingStore->setToken('staging-token');
    $stagingStore->flush();

    expect($stagingStore->getToken())->toBeNull();

    putenv('FLARE_BASE_URL');
    unset($_SERVER['FLARE_BASE_URL']);

    expect((new CredentialStore(new FlareUrlResolver))->getToken())->toBe('production-token');
});

it('does not create a config file when flushing without stored credentials', function () {
    $this->store->flush();

    expect(file_exists($this->tempDir.'/.flare/config.json'))->toBeFalse();
});

it('creates the config directory if it does not exist', function () {
    $configDir = $this->tempDir.'/.flare';

    expect(is_dir($configDir))->toBeFalse();

    $this->store->setToken('test-token');

    expect(is_dir($configDir))->toBeTrue();
});

it('writes pretty-printed JSON', function () {
    $this->store->setToken('test-token');

    $configFile = $this->tempDir.'/.flare/config.json';
    $contents = file_get_contents($configFile);

    expect($contents)->toContain("\n");
    expect(json_decode($contents, true))->toBe([
        'tokens' => ['flareapp.io' => 'test-token'],
    ]);
});

it('falls back to the legacy production token', function () {
    mkdir($this->tempDir.'/.flare', 0755, true);

    file_put_contents(
        $this->tempDir.'/.flare/config.json',
        json_encode(['token' => 'legacy-production-token'], JSON_PRETTY_PRINT),
    );

    expect($this->store->getToken())->toBe('legacy-production-token');
    expect($this->store->getConfiguredHosts())->toBe(['flareapp.io']);
});

it('does not apply the legacy production token to other hosts', function () {
    mkdir($this->tempDir.'/.flare', 0755, true);

    file_put_contents(
        $this->tempDir.'/.flare/config.json',
        json_encode(['token' => 'legacy-production-token'], JSON_PRETTY_PRINT),
    );

    putenv('FLARE_BASE_URL=https://ingress-staging.flareapp.io/api');
    $_SERVER['FLARE_BASE_URL'] = 'https://ingress-staging.flareapp.io/api';

    $stagingStore = new CredentialStore(new FlareUrlResolver);

    expect($stagingStore->getToken())->toBeNull();
    expect($stagingStore->getConfiguredHosts())->toBe(['flareapp.io']);
});
