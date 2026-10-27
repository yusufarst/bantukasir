<?php

use App\Providers\AccessServiceProvider;
use App\Providers\DatabaseServiceProvider;
use App\Providers\OperationsServiceProvider;

return [
    DatabaseServiceProvider::class,
    AccessServiceProvider::class,
    OperationsServiceProvider::class,
];
