<?php

declare(strict_types=1);

use Blueprint\Blueprint;
use Blueprint\Generators\ModelGenerator as BlueprintModelGenerator;
use Dakotajune\BlueprintModels\Generators\ModelGenerator;
use Dakotajune\BlueprintModels\Lexers\ModelOptionsLexer;

function registered(string $property): array
{
    $reflection = new ReflectionProperty(Blueprint::class, $property);

    return $reflection->getValue(app(Blueprint::class));
}

it('replaces Blueprint\'s model generator with ours, once', function () {
    $classes = array_map(fn (object $generator): string => $generator::class, registered('generators'));

    expect(array_count_values($classes)[ModelGenerator::class] ?? 0)->toBe(1)
        ->and(in_array(BlueprintModelGenerator::class, $classes, true))->toBeFalse();
});

it('registers the options lexer and no second ModelLexer', function () {
    $classes = array_map(fn (object $lexer): string => $lexer::class, registered('lexers'));

    expect(in_array(ModelOptionsLexer::class, $classes, true))->toBeTrue()
        ->and(in_array(\Blueprint\Lexers\ModelLexer::class, $classes, true))->toBeFalse();
});

it('merges a partial published config over the defaults', function () {
    expect(config('blueprint_models.schema.enabled'))->toBeTrue()
        ->and(config('blueprint_models.schema.mode'))->toBe('commented')
        ->and(config('blueprint_models.docblocks'))->toBeTrue()
        ->and(config('blueprint_models.aliases.SoftDeletes'))->toBe('Illuminate\Database\Eloquent\SoftDeletes');
});
