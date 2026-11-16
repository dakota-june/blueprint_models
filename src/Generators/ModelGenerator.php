<?php

declare(strict_types=1);

namespace Dakotajune\BlueprintModels\Generators;

use Blueprint\Contracts\Model as ModelContract;
use Blueprint\Generators\ModelGenerator as BlueprintModelGenerator;
use Blueprint\Models\Model;
use Blueprint\Tree;
use Dakotajune\BlueprintModels\Support\AttributeRenderer;
use Dakotajune\BlueprintModels\Support\DefaultValue;
use Dakotajune\BlueprintModels\Support\DocTypes;
use Dakotajune\BlueprintModels\Support\ModelOptions;
use Dakotajune\BlueprintModels\Support\ModelOptionsRegistry;
use Dakotajune\BlueprintModels\Support\SchemaDefinitionBuilder;
use Illuminate\Support\Str;

/**
 * Blueprint's model generator with attributes, defaults, docblocks and `_schema()` on top.
 *
 * It is written to run on every Blueprint release from 2.10 on, and on master. Releases have no `parent()`, `traits()` or
 * `interfaces()` on the model, and an older stub, so the file is assembled here from the model's columns and the
 * ModelOptions the lexer took out of the draft. From Blueprint itself only the pieces that are the same everywhere are
 * borrowed: relationship methods, fillable/hidden/cast column rules, the property stubs, path and write helpers.
 */
final class ModelGenerator extends BlueprintModelGenerator
{
    private const ATTRIBUTE_NAMESPACE = 'Illuminate\\Database\\Eloquent\\Attributes\\';

    private const ELOQUENT_MODEL = 'Illuminate\\Database\\Eloquent\\Model';

    private const PIVOT = 'Illuminate\\Database\\Eloquent\\Relations\\Pivot';

    private const HAS_FACTORY = 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory';

    /** @var array<string, list<array{name: string, type: string, related: string, through: ?string}>> */
    private array $relations = [];

    /** @var array<string, array<string, true>> model class => imported classes */
    private array $useStatements = [];

    /** @var array<string, array<string, string>> model class => short name => class that owns it */
    private array $shortNames = [];

    public function output(Tree $tree): array
    {
        $this->tree = $tree;
        $this->relations = $this->useStatements = $this->shortNames = [];

        $stub = $this->classStub();

        /** @var Model $model */
        foreach ($tree->models() as $model) {
            $path = $this->getPath($model);

            $before = count($this->output['created'] ?? []);

            $this->create($path, $this->render($stub, $model));

            // Blueprint 2.10 notes the file inside create(); later releases leave it to the generator.
            if (count($this->output['created'] ?? []) === $before) {
                $this->output['created'][] = ['Model', $path];
            }
        }

        return $this->output;
    }

    /**
     * Blueprint's relationship code imports the relation classes through this.
     */
    protected function addImport(ModelContract $model, $class): void
    {
        $this->reference($model, (string) $class);
    }

    /**
     * The name to write in the file for a class, importing it when that is needed and possible. A bare name lives next to
     * the model (or in the global namespace) and is written as is; a class from the model's own namespace needs no import;
     * and when two classes would share a short name the second is written out in full.
     */
    private function reference(ModelContract $model, string $class): string
    {
        $class = ltrim($class, '\\');

        if (! str_contains($class, '\\')) {
            return $class;
        }

        $short = Str::afterLast($class, '\\');
        $key = $model->fullyQualifiedClassName();
        $this->shortNames[$key] ??= [$model->name() => $key];

        if (Str::beforeLast($class, '\\') === $model->fullyQualifiedNamespace() && ($this->shortNames[$key][$short] ?? $class) === $class) {
            return $short;
        }

        if (($this->shortNames[$key][$short] ?? $class) !== $class) {
            return '\\' . $class;
        }

        $this->shortNames[$key][$short] = $class;
        $this->useStatements[$key][$class] = true;

        return $short;
    }

    private function classStub(): string
    {
        $stub = $this->lf($this->filesystem->stub('model.class.stub'));

        // A project's own stub is used when it has the placeholders this generator fills; Blueprint's older stub has none.
        if (str_contains($stub, '{{ extends }}') && str_contains($stub, '{{ traits }}')) {
            return $stub;
        }

        return $this->lf((string) file_get_contents(__DIR__ . '/../../stubs/model.class.stub'));
    }

    private function render(string $stub, Model $model): string
    {
        $options = app(ModelOptionsRegistry::class)->for($model->fullyQualifiedClassName());

        $parent = $this->reference($model, $this->parentClass($model, $options));
        $traits = array_map(fn (string $trait): string => $this->reference($model, $trait), $this->traitClasses($model, $options));
        $interfaces = array_map(fn (string $interface): string => $this->reference($model, $interface), $this->interfaceClasses($model));

        $relationships = $this->relationshipMethods($model);
        $attributes = $this->useAttributes() ? $this->buildAttributes($model) : [];

        $header = $this->classDocblock($model, $options);

        foreach ($attributes as $attribute) {
            $header .= "\n" . $attribute;
        }

        $extends = $parent . ($interfaces === [] ? '' : ' implements ' . implode(', ', $interfaces));

        $members = array_filter([
            $this->propertiesBlock($model),
            $this->buildSchema($model),
            $relationships,
        ], fn (string $block): bool => trim($block) !== '');

        $body = trim(implode("\n\n", array_map('rtrim', $members)));
        $inside = $this->traitsBlock($model, $traits) . ($body === '' ? '' : "\n\n    " . $body);

        $stub = str_replace('{{ namespace }}', $model->fullyQualifiedNamespace(), $stub);
        $stub = str_replace("\nclass {{ class }}", $header . "\nclass {{ class }}", $stub);
        $stub = str_replace('{{ class }}', $model->name(), $stub);
        $stub = str_replace('{{ extends }}', $extends, $stub);
        $stub = str_replace('{{ traits }}', $inside, $stub);
        $stub = str_replace('{{ imports }}', $this->importLines($model), $stub);

        // Blueprint mixes line endings (its stubs on disk, PHP_EOL in code); the generated file gets one kind only.
        return $this->eol($stub);
    }

    // -- parent, traits, interfaces ------------------------------------------------------------------------------

    private function parentClass(Model $model, ModelOptions $options): string
    {
        if ($options->extends !== null) {
            return $options->extends;
        }

        // Master knows the parent; a release has Eloquent's Model, or Pivot for a pivot table.
        if (method_exists($model, 'parent')) {
            return $model->parent();
        }

        return $model->isPivot() ? self::PIVOT : self::ELOQUENT_MODEL;
    }

    /**
     * @return list<string>
     */
    private function traitClasses(Model $model, ModelOptions $options): array
    {
        $traits = [self::HAS_FACTORY];

        if ($model->usesSoftDeletes()) {
            $traits[] = 'Illuminate\\Database\\Eloquent\\SoftDeletes';
        }

        if ($model->usesUlids()) {
            $traits[] = 'Illuminate\\Database\\Eloquent\\Concerns\\HasUlids';
        }

        if ($model->usesUuids()) {
            $traits[] = 'Illuminate\\Database\\Eloquent\\Concerns\\HasUuids';
        }

        if (method_exists($model, 'traits')) {
            array_push($traits, ...$model->traits());
        }

        array_push($traits, ...$options->traits);

        return array_values(array_unique(array_map(fn (string $trait): string => ltrim($trait, '\\'), $traits)));
    }

    /**
     * @return list<string>
     */
    private function interfaceClasses(Model $model): array
    {
        return method_exists($model, 'interfaces') ? array_values(array_unique($model->interfaces())) : [];
    }

    /**
     * @param  list<string>  $traits  as they are written in the file
     */
    private function traitsBlock(Model $model, array $traits): string
    {
        usort($traits, fn (string $a, string $b): int => strcmp(ltrim($a, '\\'), ltrim($b, '\\')));

        $line = 'use ' . implode(', ', $traits) . ';';

        // Blueprint's own `@use` line, which it only writes when its phpdoc setting is on; here it follows ours.
        if ($this->docblocks() && in_array('HasFactory', $traits, true)) {
            return "/** @use HasFactory<\\Database\\Factories\\" . $model->name() . "Factory> */\n    " . $line;
        }

        return $line;
    }

    private function importLines(ModelContract $model): string
    {
        $classes = array_keys($this->useStatements[$model->fullyQualifiedClassName()] ?? []);
        sort($classes, SORT_STRING);

        return implode("\n", array_map(fn (string $class): string => "use {$class};", $classes));
    }

    // -- class docblock ------------------------------------------------------------------------------------------

    private function classDocblock(Model $model, ModelOptions $options): string
    {
        if (! $this->docblocks()) {
            return '';
        }

        $description = $options->description ?? sprintf('Eloquent model for the `%s` table.', $model->tableName());

        $lines = [' * ' . $description, ' *'];

        foreach ($model->columns() as $column) {
            $comment = DocTypes::comment($column);

            foreach (DocTypes::propertiesFor($column) as $name => $type) {
                $lines[] = rtrim(sprintf(' * @property %s $%s %s', $type, $name, $comment ?? ''));
            }
        }

        if ($model->usesTimestamps()) {
            $lines[] = ' * @property ' . DocTypes::carbon() . '|null $created_at';
            $lines[] = ' * @property ' . DocTypes::carbon() . '|null $updated_at';
        }

        if ($model->usesSoftDeletes()) {
            $lines[] = ' * @property ' . DocTypes::carbon() . '|null $deleted_at';
        }

        $properties = [];

        foreach ($this->relations[$model->fullyQualifiedClassName()] ?? [] as $relation) {
            $properties[] = sprintf(' * @property-read %s $%s', $this->relationPropertyType($relation), $relation['name']);
        }

        if ($properties !== []) {
            $lines[] = ' *';
            array_push($lines, ...$properties);
        }

        return "\n/**\n" . implode("\n", $lines) . "\n */";
    }

    /**
     * @param  array{name: string, type: string, related: string, through: ?string}  $relation
     */
    private function relationPropertyType(array $relation): string
    {
        $collection = '\\Illuminate\\Database\\Eloquent\\Collection<int, ' . $relation['related'] . '>';

        return match ($relation['type']) {
            'HasMany', 'BelongsToMany', 'MorphMany', 'MorphToMany', 'HasManyThrough' => $collection,
            'HasOne', 'MorphOne', 'HasOneThrough', 'MorphTo' => $relation['related'] . '|null',
            default => $relation['related'],
        };
    }

    // -- attributes ----------------------------------------------------------------------------------------------

    /**
     * @return list<string>
     */
    private function buildAttributes(Model $model): array
    {
        $width = (int) config('blueprint_models.line_width', 100);
        $attributes = [];
        $add = function (string $name, string|array|null $argument = null) use (&$attributes, $model, $width): void {
            $attributes[] = $this->attribute($model, $name, $argument, $width);
        };

        if ($model->usesCustomDatabaseConnection()) {
            $add('Connection', $model->databaseConnection());
        }

        if ($model->isPivot() && $model->usesPrimaryKey()) {
            // Blueprint turns incrementing back on for a pivot with an id; Table() is the attribute that carries it.
            $attributes[] = $this->attribute($model, 'Table', null, $width, sprintf("'%s', incrementing: true", $model->tableName()));
        } elseif ($model->usesCustomTableName() || $model->isPivot()) {
            $add('Table', $model->tableName());
        }

        if (! $model->usesTimestamps()) {
            $add('WithoutTimestamps');
        }

        if (! $model->usesPrimaryKey()) {
            $add('WithoutIncrementing');
        }

        if (config('blueprint.use_guarded')) {
            $add('Unguarded');
        } else {
            $fillable = array_values($this->fillableColumns($model->columns()));

            // Fillable() with no columns is not a valid call; an empty $fillable is Eloquent's default anyway.
            if ($fillable !== []) {
                $add('Fillable', $fillable);
            }
        }

        $hidden = array_values($this->hiddenColumns($model->columns()));

        if ($hidden !== []) {
            $add('Hidden', $hidden);
        }

        return $attributes;
    }

    /**
     * @param  string|list<string>|null  $argument
     */
    private function attribute(Model $model, string $name, string|array|null $argument, int $width, ?string $raw = null): string
    {
        $name = $this->reference($model, self::ATTRIBUTE_NAMESPACE . $name);

        return $raw === null ? AttributeRenderer::render($name, $argument, $width) : "#[{$name}({$raw})]";
    }

    // -- properties and methods ----------------------------------------------------------------------------------

    private function propertiesBlock(Model $model): string
    {
        $blocks = [];

        if (! $this->useAttributes()) {
            $blocks = $this->classicProperties($model);
        }

        $defaults = config('blueprint_models.defaults', true) ? DefaultValue::forModel($model) : [];

        if ($defaults !== []) {
            $blocks[] = $this->defaultsProperty($defaults);
        }

        $casts = $this->castableColumns($model->columns());

        if ($casts !== []) {
            $stub = $this->lf($this->filesystem->stub('model.casts.stub'));

            if (! $this->docblocks()) {
                $stub = (string) preg_replace('~^\s*/\*\*.*?\*/\R~s', '', $stub, 1);
            }

            // Blueprint 2.10 still has a `$casts` property here, later releases a casts() method.
            $indent = str_contains($stub, 'function') ? 8 : 4;
            $blocks[] = str_replace('[]', AttributeRenderer::array(array_map(DefaultValue::quote(...), $casts), $indent), $stub);
        }

        return implode("\n\n", array_map('rtrim', array_filter($blocks, fn (string $block): bool => trim($block) !== '')));
    }

    /**
     * The properties Blueprint writes when attributes are switched off. Same rules, same stubs.
     *
     * @return list<string>
     */
    private function classicProperties(Model $model): array
    {
        $stub = fn (string $name): string => $this->lf($this->filesystem->stub($name));
        $list = fn (string $name, array $columns): string => str_replace(
            '[]',
            AttributeRenderer::array(array_map(DefaultValue::quote(...), array_values($columns)), 4),
            $stub($name),
        );
        $blocks = [];

        if ($model->usesCustomDatabaseConnection()) {
            $blocks[] = str_replace('{{ name }}', $model->databaseConnection(), $stub('model.connection.stub'));
        }

        if ($model->usesCustomTableName() || $model->isPivot()) {
            $blocks[] = str_replace('{{ name }}', $model->tableName(), $stub('model.table.stub'));
        }

        if (! $model->usesTimestamps()) {
            $blocks[] = $stub('model.timestamps.stub');
        }

        if ($model->isPivot() && $model->usesPrimaryKey()) {
            $blocks[] = $stub('model.incrementing.stub');
        }

        if (config('blueprint.use_guarded')) {
            $blocks[] = $stub('model.guarded.stub');
        } else {
            $fillable = $this->fillableColumns($model->columns());
            $blocks[] = $fillable === [] ? $stub('model.fillable.stub') : $list('model.fillable.stub', $fillable);
        }

        $hidden = $this->hiddenColumns($model->columns());

        if ($hidden !== []) {
            $blocks[] = $list('model.hidden.stub', $hidden);
        }

        return $blocks;
    }

    /**
     * @param  array<string, string>  $defaults
     */
    private function defaultsProperty(array $defaults): string
    {
        $doc = $this->docblocks()
            ? "    /**\n     * The model's default values for attributes.\n     *\n     * @var array<string, mixed>\n     */\n"
            : '';

        return $doc . '    protected $attributes = ' . AttributeRenderer::array($defaults, 4) . ';';
    }

    private function buildSchema(Model $model): string
    {
        if (! config('blueprint_models.schema.enabled', true)) {
            return '';
        }

        $lines = (new SchemaDefinitionBuilder($this->filesystem))->lines($model, $this->tree);

        $create = [
            "        \\Illuminate\\Support\\Facades\\Schema::create('{$model->tableName()}', function (\\Illuminate\\Database\\Schema\\Blueprint \$table) {",
            ...$lines,
            '        });',
        ];

        if (config('blueprint_models.schema.mode', 'commented') === 'method') {
            $doc = $this->docblocks()
                ? "    /**\n     * The table definition, as Blueprint wrote it into the migration. Reference only: nothing calls this.\n     */\n"
                : '';
            $body = array_map(fn (string $line): string => rtrim('        // ' . substr($line, 8)), $create);

            return $doc . "    private function _schema(): void\n    {\n" . implode("\n", $body) . "\n    }";
        }

        $method = ['    private function _schema(): void', '    {', ...$create, '    }'];
        $out = ['    // The table definition, as Blueprint wrote it into the migration. Reference only: uncomment to use.'];

        foreach ($method as $line) {
            $out[] = rtrim('    // ' . substr($line, 4));
        }

        return implode("\n", $out);
    }

    // -- relationships -------------------------------------------------------------------------------------------

    /**
     * Blueprint's relationship methods, each with a docblock. Blueprint builds them as one string, so they are taken
     * apart again here, which also yields the list the class docblock needs.
     */
    private function relationshipMethods(Model $model): string
    {
        $key = $model->fullyQualifiedClassName();
        $this->relations[$key] = [];

        $raw = $this->lf(parent::buildRelationships($model));

        if (trim($raw) === '') {
            return '';
        }

        $docblocks = $this->docblocks();
        $out = [];

        foreach (preg_split('/(?=^    public function )/m', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $chunk) {
            if (preg_match('/^    public function (\w+)\(\): (\w+)/', $chunk, $method) !== 1) {
                $out[] = rtrim($chunk);

                continue;
            }

            preg_match_all('/(\\\\?[A-Za-z_][\w\\\\]*)::class/', $chunk, $classes);

            $type = $method[2];
            $through = str_contains($type, 'Through') ? ($classes[1][1] ?? null) : null;
            $related = $type === 'MorphTo' ? '\\Illuminate\\Database\\Eloquent\\Model' : ($classes[1][0] ?? '\\Illuminate\\Database\\Eloquent\\Model');
            $relation = ['name' => $method[1], 'type' => $type, 'related' => $related, 'through' => $through];

            $this->relations[$key][] = $relation;
            $out[] = rtrim(($docblocks ? $this->relationDocblock($model, $relation) : '') . $chunk);
        }

        return implode("\n\n", $out);
    }

    /**
     * @param  array{name: string, type: string, related: string, through: ?string}  $relation
     */
    private function relationDocblock(Model $model, array $relation): string
    {
        $words = fn (string $class): string => str_replace('_', ' ', Str::snake(Str::afterLast($class, '\\')));
        $owner = $words($model->name());
        $related = $words($relation['related']);
        $many = Str::plural($related);

        $summary = match ($relation['type']) {
            'BelongsTo' => "Get the {$related} this {$owner} belongs to.",
            'HasOne', 'MorphOne' => "Get the {$related} that belongs to this {$owner}.",
            'HasMany', 'MorphMany' => "Get the {$many} that belong to this {$owner}.",
            'BelongsToMany', 'MorphToMany' => "Get the {$many} attached to this {$owner}.",
            'MorphTo' => "Get the model this {$owner} belongs to.",
            'HasOneThrough' => "Get the {$related} reached through " . $words((string) $relation['through']) . '.',
            'HasManyThrough' => "Get the {$many} reached through " . $words((string) $relation['through']) . '.',
            default => "Get the {$related} relationship.",
        };

        $generics = match ($relation['type']) {
            'MorphTo' => $relation['related'] . ', $this',
            'HasOneThrough', 'HasManyThrough' => $relation['related'] . ', ' . $relation['through'] . ', $this',
            default => $relation['related'] . ', $this',
        };

        return "    /**\n     * {$summary}\n     *\n     * @return {$relation['type']}<{$generics}>\n     */\n";
    }

    // -- helpers ------------------------------------------------------------------------------------------------

    private function useAttributes(): bool
    {
        $setting = config('blueprint_models.attributes');

        return $setting === null
            ? class_exists(self::ATTRIBUTE_NAMESPACE . 'Fillable')
            : (bool) $setting;
    }

    private function docblocks(): bool
    {
        return (bool) (config('blueprint_models.docblocks') ?? config('blueprint.generate_phpdocs'));
    }

    private function lf(string $text): string
    {
        return (string) preg_replace('/\r*\n/', "\n", $text);
    }

    /** Normalised first: a string that already holds \r\n (Blueprint's import list does) must not become \r\r\n. */
    private function eol(string $text, string $eol = PHP_EOL): string
    {
        return str_replace("\n", $eol, $this->lf($text));
    }
}
