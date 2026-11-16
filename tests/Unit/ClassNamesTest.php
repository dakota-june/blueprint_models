<?php

declare(strict_types=1);

use Dakotajune\BlueprintModels\Support\ClassNames;

it('cleans what people type', function () {
    expect(ClassNames::clean('ValidatingTrait'))->toBe('ValidatingTrait')
        ->and(ClassNames::clean('use ValidatingTrait;'))->toBe('ValidatingTrait')
        ->and(ClassNames::clean('  \App\Support\Base  '))->toBe('App\Support\Base')
        ->and(ClassNames::clean('useful'))->toBe('useful');
});

it('qualifies short names through aliases, then the configured namespace', function () {
    $aliases = ['SoftDeletes' => 'Illuminate\Database\Eloquent\SoftDeletes'];

    expect(ClassNames::qualify('SoftDeletes', $aliases, 'App\Concerns'))->toBe('Illuminate\Database\Eloquent\SoftDeletes')
        ->and(ClassNames::qualify('Mine', $aliases, 'App\Concerns'))->toBe('App\Concerns\Mine')
        ->and(ClassNames::qualify('Mine', $aliases, null))->toBe('Mine')
        ->and(ClassNames::qualify('A\B', $aliases, 'App\Concerns'))->toBe('A\B');
});

it('reads a comma string and a list the same way', function () {
    expect(ClassNames::list('A, use B;, \C\D,,A', 'x'))->toBe(['A', 'B', 'C\D'])
        ->and(ClassNames::list(['A', 'B'], 'x'))->toBe(['A', 'B']);
});

it('rejects anything else with a message that names the place', function () {
    expect(fn () => ClassNames::list(true, 'Contact: use'))->toThrow(InvalidArgumentException::class, 'Contact: use')
        ->and(fn () => ClassNames::list([['A']], 'Contact: use'))->toThrow(InvalidArgumentException::class, 'Contact: use');
});
