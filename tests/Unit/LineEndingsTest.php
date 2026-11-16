<?php

declare(strict_types=1);

use Dakotajune\BlueprintModels\Generators\ModelGenerator;
use Illuminate\Filesystem\Filesystem;

function eolOf(string $text, string $eol): string
{
    $method = new ReflectionMethod(ModelGenerator::class, 'eol');

    return $method->invoke(new ModelGenerator(new Filesystem()), $text, $eol);
}

it('converts to one kind of line ending, whatever it was given', function () {
    // \r\r\n is what Blueprint's own code produces when a CRLF stub meets PHP_EOL on Windows.
    $mixed = "a\nb\r\nc\r\r\nd";

    expect(eolOf($mixed, "\r\n"))->toBe("a\r\nb\r\nc\r\nd")
        ->and(eolOf($mixed, "\n"))->toBe("a\nb\nc\nd");
});
