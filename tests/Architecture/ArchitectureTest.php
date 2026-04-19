<?php

arch('no debugging functions')
    ->expect(['dd', 'dump', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();

arch('strict types declaration')
    ->expect('Qredit\LaravelQredit')
    ->toUseStrictTypes();

arch('classes are final or abstract')
    ->expect('Qredit\LaravelQredit\Exceptions')
    ->classes()
    ->toBeFinal();

arch('interfaces have proper suffix')
    ->expect('Qredit\LaravelQredit\Contracts')
    ->toBeInterfaces()
    ->toHaveSuffix('Interface');

arch('traits have proper suffix')
    ->expect('Qredit\LaravelQredit\Traits')
    ->toBeTraits()
    ->toHaveSuffix('Trait');

arch('controllers extend base controller')
    ->expect('Qredit\LaravelQredit\Controllers')
    ->classes()
    ->toExtend('Illuminate\Routing\Controller');

arch('no direct env calls outside config')
    ->expect('env')
    ->not->toBeUsed()
    ->ignoring('Qredit\LaravelQredit\Config');

arch('facades extend Laravel facade')
    ->expect('Qredit\LaravelQredit\Facades')
    ->toExtend('Illuminate\Support\Facades\Facade');

arch('exceptions extend base exception')
    ->expect('Qredit\LaravelQredit\Exceptions')
    ->toExtend('Exception')
    ->or->toExtend('Qredit\LaravelQredit\Exceptions\QreditException');

arch('requests implement proper interface')
    ->expect('Qredit\LaravelQredit\Requests')
    ->toExtend('Saloon\Http\Request');

arch('no public properties in classes')
    ->expect('Qredit\LaravelQredit')
    ->not->toHavePublicProperties()
    ->ignoring('Qredit\LaravelQredit\DataTransferObjects');

arch('service provider is properly structured')
    ->expect('Qredit\LaravelQredit\QreditServiceProvider')
    ->toExtend('Illuminate\Support\ServiceProvider')
    ->toHaveMethod('register')
    ->toHaveMethod('boot');

arch('dependency injection over facades in src')
    ->expect('Qredit\LaravelQredit')
    ->not->toUse('Illuminate\Support\Facades')
    ->ignoring([
        'Qredit\LaravelQredit\Facades',
        'Qredit\LaravelQredit\QreditServiceProvider',
    ]);

arch('no unused imports')
    ->expect('Qredit\LaravelQredit')
    ->not->toHaveUnusedImports();