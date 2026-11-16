<?php

declare(strict_types=1);

namespace Dakotajune\BlueprintModels\Support;

use Blueprint\Models\Column;
use Blueprint\Models\Model;

/**
 * `default:` modifiers as PHP literals for `protected $attributes`.
 */
final class DefaultValue
{
    private const INTEGERS = ['integer', 'biginteger', 'mediuminteger', 'smallinteger', 'tinyinteger', 'year',
        'unsignedinteger', 'unsignedbiginteger', 'unsignedmediuminteger', 'unsignedsmallinteger', 'unsignedtinyinteger',
        'increments', 'bigincrements', 'mediumincrements', 'smallincrements', 'tinyincrements'];

    private const FLOATS = ['float', 'double', 'decimal', 'unsigneddecimal'];

    /**
     * @return array<string, string> column name => PHP source of the default
     */
    public static function forModel(Model $model): array
    {
        $defaults = [];

        foreach ($model->columns() as $column) {
            $literal = self::literal($column);

            if ($literal !== null) {
                $defaults[$column->name()] = $literal;
            }
        }

        return $defaults;
    }

    public static function literal(Column $column): ?string
    {
        if ($column->name() === 'id' || str_contains(strtolower($column->dataType()), 'morphs')) {
            return null;
        }

        $raw = null;

        foreach ($column->modifiers() as $modifier) {
            if (is_array($modifier) && array_key_exists('default', $modifier)) {
                $raw = (string) $modifier['default'];
            }
        }

        if ($raw === null) {
            return null;
        }

        $type = strtolower($column->dataType());

        if (strtolower($raw) === 'null') {
            return 'null';
        }

        if ($type === 'boolean') {
            return match (strtolower($raw)) {
                'true', '1' => 'true',
                'false', '0' => 'false',
                default => self::quote($raw),
            };
        }

        if (in_array($type, self::INTEGERS, true) && preg_match('/^-?\d+$/', $raw) === 1) {
            return (string) (int) $raw;
        }

        if (in_array($type, self::FLOATS, true) && is_numeric($raw)) {
            return str_contains($raw, '.') ? (string) (float) $raw : (string) (int) $raw;
        }

        return self::quote($raw);
    }

    public static function quote(string $value): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }
}
