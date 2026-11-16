<?php

declare(strict_types=1);

namespace Dakotajune\BlueprintModels\Support;

use Blueprint\Models\Column;

/**
 * The type a column reads as on the model, for @property lines.
 */
final class DocTypes
{
    private const CARBON = '\Carbon\Carbon';

    public static function forColumn(Column $column): string
    {
        $type = strtolower($column->dataType());

        if (str_contains($type, 'integer') || str_contains($type, 'increments') || in_array($type, ['id', 'year'], true)) {
            return 'int';
        }

        if ($type === 'boolean') {
            return 'bool';
        }

        if (in_array($type, ['float', 'double'], true)) {
            return 'float';
        }

        // Eloquent's decimal cast hands back a string, to keep the precision.
        if (in_array($type, ['decimal', 'unsigneddecimal'], true)) {
            return 'string';
        }

        if (in_array($type, ['json', 'jsonb'], true)) {
            return 'array<array-key, mixed>';
        }

        if (in_array($type, ['date', 'datetime', 'datetimetz', 'timestamp', 'timestamptz', 'time', 'timetz'], true)) {
            return self::CARBON;
        }

        return 'string';
    }

    /**
     * One entry per property a column adds: morph columns add two.
     *
     * @return array<string, string> property name => type, nullable already applied
     */
    public static function propertiesFor(Column $column): array
    {
        $type = strtolower($column->dataType());
        $nullable = $column->isNullable() || str_starts_with($type, 'nullable');

        if (str_contains($type, 'morphs')) {
            $id = str_contains($type, 'uuid') || str_contains($type, 'ulid') ? 'string' : 'int';
            $suffix = $nullable ? '|null' : '';

            return [
                $column->name() . '_id' => $id . $suffix,
                $column->name() . '_type' => 'string' . $suffix,
            ];
        }

        return [$column->name() => self::forColumn($column) . ($nullable ? '|null' : '')];
    }

    public static function comment(Column $column): ?string
    {
        foreach ($column->modifiers() as $modifier) {
            if (is_array($modifier) && isset($modifier['comment']) && trim((string) $modifier['comment']) !== '') {
                return trim((string) $modifier['comment']);
            }
        }

        return null;
    }

    public static function carbon(): string
    {
        return self::CARBON;
    }
}
