<?php

declare(strict_types=1);

namespace Dakotajune\BlueprintModels\Support;

/**
 * PHP 8 attribute source: `#[Table('x')]`, `#[WithoutTimestamps]`, `#[Fillable([...])]`.
 */
final class AttributeRenderer
{
    /**
     * @param  string|list<string>|null  $argument  null: no parentheses; string: one quoted value; list: an array
     */
    public static function render(string $name, string|array|null $argument, int $width = 100): string
    {
        if ($argument === null) {
            return "#[{$name}]";
        }

        if (is_string($argument)) {
            return "#[{$name}(" . DefaultValue::quote($argument) . ')]';
        }

        $items = array_map(DefaultValue::quote(...), array_values($argument));
        $single = "#[{$name}([" . implode(', ', $items) . '])]';

        if (strlen($single) <= $width || count($items) < 2) {
            return $single;
        }

        return "#[{$name}([\n    " . implode(",\n    ", $items) . ",\n])]";
    }

    /**
     * An array literal, one entry per line, for method bodies and properties.
     *
     * @param  array<string, string>|list<string>  $entries  already-rendered PHP values; string keys become `'key' => value`
     */
    public static function array(array $entries, int $indent): string
    {
        $pad = str_repeat(' ', $indent + 4);
        $lines = [];

        foreach ($entries as $key => $value) {
            $lines[] = is_string($key) ? $pad . DefaultValue::quote($key) . ' => ' . $value . ',' : $pad . $value . ',';
        }

        return "[\n" . implode("\n", $lines) . "\n" . str_repeat(' ', $indent) . ']';
    }
}
