<?php

use Illuminate\Support\Facades\Artisan;

it('registers key API commands from the OpenAPI spec', function (string $command) {
    $commands = collect(Artisan::all())->keys()->toArray();

    expect($commands)->toContain($command);
})->with([
    'auth',
    'list-projects',
    'resolve-error',
    'list-error-occurrences',
    'get-authenticated-user',
    'create-project',
    'delete-project',
]);
