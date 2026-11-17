<?php

declare(strict_types=1);

namespace Dakotajune\BlueprintModels\Support;

use Blueprint\Models\Column;
use Blueprint\Models\Model;
use Blueprint\Tree;
use Illuminate\Support\Str;

/**
 * The validation rules a model's columns imply, for `protected $rules`. They are the basic ones only: whether a value
 * is needed, what type it is, how long it may be, and whether it must be unique or point at a row that exists. What a
 * database would refuse anyway, written down where the model can say so before it gets that far.
 *
 * The rules are for Laravel's validator, and for packages such as watson/validating that run them when a model is
 * saved. Such packages read the raw attributes (a json column holds a string, a date a formatted string), which is why
 * `json` and `date` are checked here and not `array` and `Carbon`.
 */
final class RuleBuilder
{
    /** Column types that are not a value somebody supplies: keys, and what Eloquent fills in itself. */
    private const NOT_VALIDATED = [
        'increments', 'bigincrements', 'mediumincrements', 'smallincrements', 'tinyincrements',
        'timestamps', 'timestampstz', 'nullabletimestamps', 'softdeletes', 'softdeletestz', 'remembertoken', 'fulltext',
    ];

    private const INTEGERS = ['integer', 'biginteger', 'mediuminteger', 'smallinteger', 'tinyinteger',
        'unsignedinteger', 'unsignedbiginteger', 'unsignedmediuminteger', 'unsignedsmallinteger', 'unsignedtinyinteger'];

    private const DATES = ['date', 'datetime', 'datetimetz', 'timestamp', 'timestamptz'];

    /**
     * @return array<string, string> column name => rules joined with `|`, in the order the columns are declared
     */
    public static function forModel(Model $model, ?Tree $tree = null, bool $guessFromNames = true): array
    {
        $rules = [];

        foreach ($model->columns() as $column) {
            foreach (self::forColumn($model, $column, $tree, $guessFromNames) as $field => $rule) {
                $rules[$field] = $rule;
            }
        }

        // `morphTo: Subject` is not a column of the model, but the migration adds `$table->morphs('subject')` for it.
        foreach ((array) ($model->relationships()['morphTo'] ?? []) as $morphTo) {
            $morph = new Column(Str::lower((string) $morphTo), 'morphs');

            foreach (self::forColumn($model, $morph, $tree, $guessFromNames) as $field => $rule) {
                $rules[$field] ??= $rule;
            }
        }

        return $rules;
    }

    /**
     * One entry for a column, none for a column that is not validated, two for a morph (`subject_id`, `subject_type`).
     *
     * @return array<string, string>
     */
    public static function forColumn(Model $model, Column $column, ?Tree $tree = null, bool $guessFromNames = true): array
    {
        $type = strtolower($column->dataType());
        $name = $column->name();

        if (self::isKey($column, $type) || in_array($type, self::NOT_VALIDATED, true)) {
            return [];
        }

        $presence = self::presence($column, $type);

        if (str_contains($type, 'morphs')) {
            $id = match (true) {
                str_contains($type, 'uuid') => 'uuid',
                str_contains($type, 'ulid') => 'ulid',
                default => 'integer',
            };

            return [
                $name . '_id' => implode('|', [...$presence, $id]),
                $name . '_type' => implode('|', [...$presence, 'string', 'max:255']),
            ];
        }

        $rules = [...$presence, ...self::typeRules($column, $type, $guessFromNames)];

        if (self::isUnique($column)) {
            $rules[] = 'unique:' . $model->tableName() . ',' . $name;
        }

        $target = self::foreignTarget($column, $type, $tree);

        if ($target !== null) {
            $rules[] = 'exists:' . $target[0] . ',' . $target[1];
        }

        return [$name => implode('|', $rules)];
    }

    // -- presence ------------------------------------------------------------------------------------------------

    /**
     * `nullable` when the column may be empty; `sometimes|required` when the database fills it in; otherwise `required`.
     *
     * @return list<string>
     */
    private static function presence(Column $column, string $type): array
    {
        $default = self::defaultOf($column);

        if ($column->isNullable() || str_starts_with($type, 'nullable') || ($default !== null && strtolower($default) === 'null')) {
            return ['nullable'];
        }

        return $default !== null ? ['sometimes', 'required'] : ['required'];
    }

    private static function isKey(Column $column, string $type): bool
    {
        if ($column->name() === 'id' || str_ends_with($type, 'increments')) {
            return true;
        }

        foreach ($column->modifiers() as $modifier) {
            if (is_string($modifier) && in_array(strtolower($modifier), ['primary', 'autoincrement'], true)) {
                return true;
            }
        }

        return false;
    }

    private static function defaultOf(Column $column): ?string
    {
        $default = null;

        foreach ($column->modifiers() as $modifier) {
            if (is_array($modifier) && array_key_exists('default', $modifier)) {
                $default = (string) $modifier['default'];
            }
        }

        return $default;
    }

    private static function isUnique(Column $column): bool
    {
        foreach ($column->modifiers() as $modifier) {
            if (is_string($modifier) && strtolower($modifier) === 'unique') {
                return true;
            }
        }

        return false;
    }

    // -- type ----------------------------------------------------------------------------------------------------

    /**
     * @return list<string>
     */
    private static function typeRules(Column $column, string $type, bool $guessFromNames): array
    {
        $attributes = array_values($column->attributes());
        $unsigned = str_starts_with($type, 'unsigned') || $column->isUnsigned();

        if (in_array($type, ['string', 'char'], true)) {
            $length = isset($attributes[0]) && ctype_digit((string) $attributes[0]) ? (int) $attributes[0] : 255;

            return ['string', ...($guessFromNames ? self::guessFromName($column->name()) : []), 'max:' . $length];
        }

        if (in_array($type, ['text', 'tinytext', 'mediumtext', 'longtext'], true)) {
            return ['string'];
        }

        if ($type === 'id') {
            return ['integer'];
        }

        if (in_array($type, self::INTEGERS, true)) {
            return ['integer', ...($unsigned ? ['min:0'] : [])];
        }

        if ($type === 'year') {
            return ['integer', 'digits:4'];
        }

        if ($type === 'boolean') {
            return ['boolean'];
        }

        if (in_array($type, ['float', 'double'], true)) {
            return ['numeric', ...($unsigned ? ['min:0'] : [])];
        }

        if (in_array($type, ['decimal', 'unsigneddecimal'], true)) {
            // Laravel's own default for the places of a decimal column is 2.
            $places = isset($attributes[1]) && ctype_digit((string) $attributes[1]) ? (int) $attributes[1] : 2;

            return ['numeric', ...($unsigned ? ['min:0'] : []), 'decimal:0,' . $places];
        }

        if (in_array($type, self::DATES, true)) {
            return ['date'];
        }

        if (in_array($type, ['time', 'timetz'], true)) {
            return ['date_format:H:i:s,H:i'];
        }

        return match ($type) {
            'json', 'jsonb' => ['json'],
            'uuid' => ['uuid'],
            'ulid' => ['ulid'],
            'ipaddress' => ['ip'],
            'macaddress' => ['mac_address'],
            'enum' => self::enumRules($attributes),
            default => [],
        };
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private static function enumRules(array $values): array
    {
        $values = array_map('strval', $values);

        // A value with a comma or a pipe cannot be told apart from the rule syntax, so it is left to the database.
        if ($values === [] || array_filter($values, fn (string $value): bool => preg_match('/[,|]/', $value) === 1) !== []) {
            return [];
        }

        return ['in:' . implode(',', $values)];
    }

    /**
     * @return list<string>
     */
    private static function guessFromName(string $name): array
    {
        return match (true) {
            $name === 'email', str_ends_with($name, '_email') => ['email'],
            $name === 'url', str_ends_with($name, '_url') => ['url'],
            default => [],
        };
    }

    // -- foreign keys --------------------------------------------------------------------------------------------

    /**
     * The table and column a foreign key points at, found the way Blueprint finds them for the migration, so the rule
     * and the constraint always name the same thing.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function foreignTarget(Column $column, string $type, ?Tree $tree): ?array
    {
        $name = $column->name();
        $marked = false;
        $on = null;

        foreach ($column->modifiers() as $modifier) {
            if ($modifier === 'foreign') {
                $marked = true;
            } elseif (is_array($modifier) && array_key_exists('foreign', $modifier)) {
                $marked = true;
                $on = (string) $modifier['foreign'];
            }
        }

        $constrained = $type === 'id'
            || (in_array($type, ['uuid', 'ulid'], true) && str_ends_with($name, '_id') && config('blueprint.use_constraints'));

        if (! $marked && ! $constrained) {
            return null;
        }

        if ($on === null) {
            $context = Str::beforeLast($name, '_');
            $table = Str::plural($context);
            $key = Str::afterLast($name, '_');
        } elseif (str_contains($on, '.')) {
            [$context, $key] = explode('.', $on, 2);
            $table = $context;
        } elseif (str_contains($on, '\\')) {
            $context = Str::afterLast($on, '\\');
            $table = Str::snake(Str::plural($context));
            $key = Str::afterLast($name, '_');
        } else {
            $context = $on;
            $table = Str::snake(Str::plural($context));
            $key = Str::afterLast($name, '_');
        }

        // `id:user` names the model the column belongs to.
        if (in_array($type, ['id', 'uuid', 'ulid'], true) && $column->attributes() !== []) {
            $context = (string) array_values($column->attributes())[0];
            $table = Str::lower(Str::plural($context));
        }

        $related = $tree?->modelForContext($context);

        if ($related !== null) {
            $table = $related->tableName();
        }

        return [$table, $key];
    }
}
