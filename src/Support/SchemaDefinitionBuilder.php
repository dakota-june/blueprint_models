<?php

declare(strict_types=1);

namespace Dakotajune\BlueprintModels\Support;

use Blueprint\Generators\MigrationGenerator;
use Blueprint\Models\Model;
use Blueprint\Tree;

/**
 * Blueprint's own migration code, asked for one model. Reusing it, rather than copying it, means the `_schema()` in a
 * model can never drift from the migration: same columns, modifiers, foreign keys, indexes, timestamps.
 */
final class SchemaDefinitionBuilder extends MigrationGenerator
{
    /**
     * The statements that go inside `Schema::create(..., function (Blueprint $table) { ... })`, one per line, already
     * indented for a closure inside a class method (12 spaces).
     *
     * @return list<string>
     */
    public function lines(Model $model, Tree $tree): array
    {
        $this->tree = $tree;

        $definition = $this->buildDefinition($model);

        if (trim($definition) === '') {
            return [];
        }

        $lines = [];

        foreach (preg_split('/\r*\n/', $definition) ?: [] as $line) {
            $lines[] = rtrim(self::INDENT . ltrim($line));
        }

        return $lines;
    }
}
