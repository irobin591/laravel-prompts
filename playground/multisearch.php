<?php

use function Laravel\Prompts\multisearch;

require __DIR__.'/../vendor/autoload.php';

$model = multisearch(
    label: 'Which user should receive the email?',
    placeholder: 'Search...',
    options: function ($value) {
        if (strlen($value) === 0) {
            return [];
        }

        usleep(100 * 1000);

        $min = min(strlen($value), 10);
        $max = max(10, 20 - strlen($value));

        if ($max - $min === 0) {
            return [];
        }

        return array_map(
            fn ($id) => "User $id",
            range($min, $max)
        );
    },
    validate: function ($value) {
        if ($value === '0') {
            return 'User 0 is not allowed to receive emails.';
        }
    }
);

var_dump($model);

echo str_repeat(PHP_EOL, 6);
