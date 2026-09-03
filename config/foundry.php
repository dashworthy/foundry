<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Domain Generators
    |--------------------------------------------------------------------------
    |
    | Where the domain-aware `make:*` generators place the files they create.
    | `base` is the directory under app/ that holds the domains; each entry in
    | `subdirectories` maps a generator type to the folder its output lands in
    | within a domain.
    |
    */

    'domains' => [
        'base' => 'Domains',

        'subdirectories' => [
            'action' => 'Actions',
            'data' => 'Data',
            'precondition' => 'Preconditions',
            'controller' => 'Controllers',
            'request' => 'FormRequests',
            'resource' => 'JsonResources',
            'model' => 'Models',
            'policy' => 'Policies',
            'enum' => 'Enums',
            'notification' => 'Notifications',
            'job' => 'Jobs',
            'event' => 'Events',
            'listener' => 'Listeners',
            'rule' => 'Rules',
            'observer' => 'Observers',
            'cast' => 'Casts',
            'mail' => 'Mail',
            'middleware' => 'Middleware',
        ],
    ],
];
