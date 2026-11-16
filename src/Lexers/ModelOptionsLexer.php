<?php

declare(strict_types=1);

namespace Dakotajune\BlueprintModels\Lexers;

use Blueprint\Contracts\Lexer;
use Blueprint\Lexers\ModelLexer;
use Dakotajune\BlueprintModels\Support\ClassNames;
use Dakotajune\BlueprintModels\Support\ModelOptions;
use Dakotajune\BlueprintModels\Support\ModelOptionsRegistry;
use InvalidArgumentException;

/**
 * Teaches the model section three things and then hands it to Blueprint's own ModelLexer:
 *
 *   ContactType:
 *     extends: MyBaseModel          -> the parent class
 *     use: ValidatingTrait, Other   -> traits (a comma string or a list)
 *     meta:
 *       description: Lookup values  -> docblock summary
 *
 * They are taken out of the draft before ModelLexer sees it, because left alone it would read `extends` and `use` as
 * columns and put them in the migration. `meta.extends` and `meta.traits` (Blueprint master) are read the same way.
 * What was taken goes to the ModelOptionsRegistry, so it works on every Blueprint release, with or without
 * Blueprint's own support for it.
 */
final class ModelOptionsLexer implements Lexer
{
    private readonly ModelLexer $models;

    public function __construct(private readonly ModelOptionsRegistry $registry, ?ModelLexer $models = null)
    {
        $this->models = $models ?? new ModelLexer();
    }

    public function analyze(array $tokens): array
    {
        $options = [];

        foreach (['models', 'cache'] as $section) {
            if (empty($tokens[$section]) || ! is_array($tokens[$section])) {
                continue;
            }

            foreach ($tokens[$section] as $name => $definition) {
                if (! is_array($definition)) {
                    continue;
                }

                [$definition, $found] = $this->extract((string) $name, $definition);

                $tokens[$section][$name] = $definition;

                if ($section === 'models') {
                    $options[(string) $name] = $found;
                }
            }
        }

        $registry = $this->models->analyze($tokens);

        foreach ($registry['models'] ?? [] as $name => $model) {
            $this->registry->put($model->fullyQualifiedClassName(), $options[$name] ?? new ModelOptions());
        }

        return $registry;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{0: array<string, mixed>, 1: ModelOptions}
     */
    private function extract(string $model, array $definition): array
    {
        $meta = isset($definition['meta']) && is_array($definition['meta']) ? $definition['meta'] : [];
        $aliases = (array) config('blueprint_models.aliases', []);
        $namespace = config('blueprint_models.short_name_namespace');
        $qualify = fn (string $class): string => ClassNames::qualify($class, $aliases, is_string($namespace) ? $namespace : null);

        $extends = null;

        // Top-level wins over meta when a draft has both.
        foreach ([[$definition['extends'] ?? null, "{$model}: extends"], [$meta['extends'] ?? null, "{$model}: meta.extends"]] as [$value, $where]) {
            if ($value === null) {
                continue;
            }

            $parents = ClassNames::list($value, $where);

            if (count($parents) !== 1) {
                throw new InvalidArgumentException("{$where} takes exactly one class.");
            }

            $extends = $qualify($parents[0]);

            break;
        }

        $traits = [];

        foreach ([[$meta['traits'] ?? null, "{$model}: meta.traits"], [$definition['use'] ?? null, "{$model}: use"]] as [$value, $where]) {
            if ($value !== null) {
                array_push($traits, ...array_map($qualify, ClassNames::list($value, $where)));
            }
        }

        $description = isset($meta['description']) && is_string($meta['description']) && trim($meta['description']) !== ''
            ? trim($meta['description'])
            : null;

        unset($definition['extends'], $definition['use'], $meta['extends'], $meta['traits'], $meta['description']);

        if ($meta !== []) {
            $definition['meta'] = $meta;
        } else {
            unset($definition['meta']);
        }

        return [$definition, new ModelOptions($description, $extends, array_values(array_unique($traits)))];
    }
}
