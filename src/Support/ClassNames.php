<?php

declare(strict_types=1);

namespace Dakotajune\BlueprintModels\Support;

use InvalidArgumentException;

/**
 * Turns what people type after `extends:` and `use:` into class names.
 */
final class ClassNames
{
    /** "use Foo\Bar;", "\Foo\Bar" and "Foo\Bar" are the same class. */
    public static function clean(string $name): string
    {
        $name = trim($name);
        $name = (string) preg_replace('/^use\s+/i', '', $name);

        return ltrim(trim($name, " \t;"), '\\');
    }

    /**
     * @param  array<string, string>  $aliases
     */
    public static function qualify(string $name, array $aliases = [], ?string $namespace = null): string
    {
        if ($name === '' || str_contains($name, '\\')) {
            return $name;
        }

        if (isset($aliases[$name])) {
            return ltrim($aliases[$name], '\\');
        }

        return $namespace === null || $namespace === '' ? $name : trim($namespace, '\\') . '\\' . $name;
    }

    /**
     * `use: A, B` and `use: [A, B]` both end up as ['A', 'B'].
     *
     * @return list<string>
     */
    public static function list(mixed $value, string $where): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException("{$where} must be a class name or a list of class names.");
        }

        $names = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw new InvalidArgumentException("{$where} must be a class name or a list of class names.");
            }

            $name = self::clean($item);

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }
}
