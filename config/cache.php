<?php

return [
    'default' => 'file',
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => env('FLARE_CACHE_PATH', sys_get_temp_dir().'/flare'),
        ],
    ],
];
