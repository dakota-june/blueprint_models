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
 * Validation rules are switched off unless a test says otherwise (`['validation.enabled' => true]`). They are on in the
 * shipped config, and would add a trait and a `$rules` property to every file the other tests look at; the tests
 * about them are in ValidationRulesTest.
 *
 * @param  array<string, mixed>  $config  overrides for `blueprint_models.*`, dotted keys such as `schema.mode` included
 * @return array<string, string>
 */
function models(string $yaml, array $config = []): array
{
    config(['blueprint_models.attributes' => true]);
    config(['blueprint_models.validation.enabled' => false]);

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
        $source = (string) file_get_contents(base_path($path));

        // Generated files use PHP_EOL, which is \r\n on Windows; the tests below are written with \n. A test that
        // plants a stub of its own on disk is about line endings, and gets the file as it was written.
        $files[basename($path, '.php')] = is_file(base_path('stubs/blueprint/model.class.stub'))
            ? $source
            : str_replace("\r\n", "\n", $source);
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
