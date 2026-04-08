<?php

namespace Tests;

use Illuminate\Cache\FileStore;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use LaravelZero\Framework\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        // Isolate the test cache from the dev binary's cache at
        // sys_get_temp_dir().'/flare'. Without this, every Pest run primes
        // the dev cache with the bundled fixture and the dev `php flare`
        // binary then reads the primed entry for up to 24h. The env var
        // must be set before bootstrap/app.php loads config.
        $testCachePath = sys_get_temp_dir().'/flare-test-'.getmypid();
        $_SERVER['FLARE_CACHE_PATH'] = $testCachePath;
        putenv("FLARE_CACHE_PATH={$testCachePath}");

        /** @var Application $app */
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $specFixturePath = base_path('vendor/spatie/laravel-openapi-cli/flare-api.yaml');

        if (file_exists($specFixturePath)) {
            // Cache facade isn't available yet (CacheServiceProvider boots
            // during $kernel->bootstrap()), so write through FileStore
            // directly. The path matches what config/cache.php will resolve
            // to once bootstrap runs because both consult FLARE_CACHE_PATH.
            (new FileStore(new Filesystem, $testCachePath))->put(
                'openapi-cli-spec:'.md5('https://flareapp.io/downloads/flare-api.yaml'),
                [
                    'content' => file_get_contents($specFixturePath),
                    'extension' => 'yaml',
                ],
                60 * 60 * 24,
            );
        }

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
