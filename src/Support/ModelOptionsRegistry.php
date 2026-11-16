<?php

declare(strict_types=1);

namespace Dakotajune\BlueprintModels\Support;

/**
 * The lexer fills it, the generator reads it. Keyed by fully qualified class name.
 */
final class ModelOptionsRegistry
{
    /** @var array<string, ModelOptions> */
    private array $options = [];

    public function put(string $class, ModelOptions $options): void
    {
        $this->options[$class] = $options;
    }

    public function for(string $class): ModelOptions
    {
        return $this->options[$class] ?? new ModelOptions();
    }
}
