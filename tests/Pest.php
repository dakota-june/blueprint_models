<?php

declare(strict_types=1);

use Blueprint\Blueprint;
use Blueprint\Tree;
use Dakotajune\BlueprintModels\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Real Blueprint, from YAML to generated model files. Returns each file's source keyed by model name.
 *
 * Attributes are switched on unless a test says otherwise: whether they are the default depends on the Laravel the
 * tests happen to run on, and these tests are about what the generator writes.
 *
 * @param  array<string, mixed>  $config  overrides for `blueprint_models.*`
 * @return array<string, string>
 */
function models(string $yaml, array $config = []): array
{
    config(['blueprint_models.attributes' => true]);

    foreach ($config as $key => $value) {
        config(["blueprint_models.{$key}" => $value]);
    }

    $tree = tree($yaml);
    $blueprint = app(Blueprint::class);
    $generated = $blueprint->generate($tree, ['models']);
    $files = [];

    foreach ($generated['created'] ?? [] as $entry) {
        // Blueprint 2.10 lists plain paths, later releases ['Model', path].
        $path = is_array($entry) ? $entry[1] : $entry;
        $files[basename($path, '.php')] = (string) file_get_contents(base_path($path));
    }

    return $files;
}

/** The analysed draft, for tests about what the lexer made of it. */
function tree(string $yaml): Tree
{
    $blueprint = app(Blueprint::class);

    // As Blueprint's build command does: dashes are only stripped from drafts that do not use `indexes:` lists.
    return $blueprint->analyze($blueprint->parse($yaml, preg_match('/^\s+indexes:\R/m', $yaml) !== 1));
}

function lints(string $source): bool
{
    try {
        token_get_all($source, TOKEN_PARSE);

        return true;
    } catch (ParseError) {
        return false;
    }
}
