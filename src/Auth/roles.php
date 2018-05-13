<?php

return [
    'root' => [
        'dominate' => ['root', 'admin', 'moderator', 'user', 'guest'],
        'obey' => [],
    ],
    'admin' => [
        'dominate' => ['admin', 'moderator', 'user', 'guest'],
        'obey' => ['root'],
    ],
    'moderator' => [
        'dominate' => ['user', 'guest'],
        'obey' => ['root', 'admin'],
    ],
    'user' => [
        'dominate' => [],
        'obey' => ['root', 'admin', 'moderator'],
    ],
    'guest' => [
        'dominate' => [],
        'obey' => ['root', 'admin', 'moderator'],
    ],
];