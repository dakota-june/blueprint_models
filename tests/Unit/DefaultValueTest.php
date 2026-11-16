<?php

declare(strict_types=1);

use Blueprint\Models\Column;
use Dakotajune\BlueprintModels\Support\DefaultValue;

function defaultLiteral(string $type, string $default, string $name = 'col'): ?string
{
    return DefaultValue::literal(new Column($name, $type, [['default' => $default]]));
}

it('writes strings quoted and escaped', function () {
    expect(defaultLiteral('string', 'Joe Dir'))->toBe("'Joe Dir'")
        ->and(defaultLiteral('string', "O'Brien"))->toBe("'O\\'Brien'")
        ->and(defaultLiteral('string', 'a\\b'))->toBe("'a\\\\b'")
        ->and(defaultLiteral('string', '10'))->toBe("'10'");
});

it('writes booleans, numbers and null as PHP values', function () {
    expect(defaultLiteral('boolean', 'true'))->toBe('true')
        ->and(defaultLiteral('boolean', '0'))->toBe('false')
        ->and(defaultLiteral('integer', '10'))->toBe('10')
        ->and(defaultLiteral('integer', '-3'))->toBe('-3')
        ->and(defaultLiteral('decimal', '9.5'))->toBe('9.5')
        ->and(defaultLiteral('decimal', '3'))->toBe('3')
        ->and(defaultLiteral('string', 'null'))->toBe('null')
        ->and(defaultLiteral('integer', 'NULL'))->toBe('null');
});

it('falls back to a string when a number is not one', function () {
    expect(defaultLiteral('integer', 'abc'))->toBe("'abc'")
        ->and(defaultLiteral('boolean', 'maybe'))->toBe("'maybe'");
});

it('has nothing to say about columns without a default, or the key', function () {
    expect(DefaultValue::literal(new Column('name', 'string', ['nullable'])))->toBeNull()
        ->and(defaultLiteral('id', '1', 'id'))->toBeNull()
        ->and(defaultLiteral('morphs', '1', 'subject'))->toBeNull();
});
