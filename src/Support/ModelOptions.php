<?php

declare(strict_types=1);

namespace Dakotajune\BlueprintModels\Support;

/**
 * What a draft says about one model that Blueprint's own Model class has no room for, or does not have at all in the
 * release you run (`extends`, `traits` and `implements` exist only on Blueprint's unreleased master).
 */
final class ModelOptions
{
    /**
     * @param  list<string>  $traits  fully qualified, or a bare name that lives next to the model
     */
    public function __construct(
        public readonly ?string $description = null,
        public readonly ?string $extends = null,
        public readonly array $traits = [],
    ) {
    }
}
