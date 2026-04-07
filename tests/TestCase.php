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
        /** @var Application $app */
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $specFixturePath = base_path('vendor/spatie/laravel-openapi-cli/flare-api.yaml');

        if (file_exists($specFixturePath)) {
            (new FileStore(new Filesystem, sys_get_temp_dir().'/flare'))->put(
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
