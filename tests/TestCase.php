<?php

declare(strict_types=1);

namespace Dakotajune\BlueprintModels\Tests;

use Blueprint\BlueprintServiceProvider;
use Dakotajune\BlueprintModels\BlueprintModelsServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    private string $originalDirectory = '';

    protected function getPackageProviders($app): array
    {
        return [BlueprintServiceProvider::class, BlueprintModelsServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Blueprint's generators write to paths relative to the working directory; make that the app, not the package.
        $this->originalDirectory = (string) getcwd();
        chdir(base_path());

        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();
        chdir($this->originalDirectory);

        parent::tearDown();
    }

    /** Removes only what the tests generate; Testbench's skeleton keeps its own files under app/. */
    private function clean(): void
    {
        $files = new Filesystem();

        foreach (['stubs/blueprint'] as $directory) {
            $files->deleteDirectory(base_path($directory));
        }

        foreach (['ContactType', 'Contact', 'Phone', 'Tag', 'ContactTag', 'Fillable', 'Note'] as $model) {
            @unlink(base_path("app/Models/{$model}.php"));
        }

        @unlink(base_path('.blueprint'));
    }
}
