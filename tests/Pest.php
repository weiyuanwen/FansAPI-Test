<?php

/*
|--------------------------------------------------------------------------
| Minimal container bootstrap
|--------------------------------------------------------------------------
|
| App\Models\Profile uses Laravel\Scout\Searchable, which registers a
| ModelObserver during boot. That observer's constructor calls Config::get(),
| so merely doing `new Profile()` throws "A facade root has not been set"
| without a container. These tests never touch the database or the search
| engine — they only call the pure calculateIntervalForLikes() — so a bare
| Container with a config repository is all the model needs to construct.
|
| If this file grows into a real Laravel application, delete it and extend
| Orchestra Testbench instead.
|
*/

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;

$container = new Container;

Container::setInstance($container);
Facade::setFacadeApplication($container);

$container->instance('config', new Repository([
    'scout' => [
        'after_commit' => false,
        'soft_delete'  => false,
    ],
]));

$container->instance('events', new Dispatcher($container));
