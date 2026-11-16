<?php

declare(strict_types=1);

namespace Dakotajune\BlueprintModels;

use Blueprint\Blueprint;
use Blueprint\Generators\ModelGenerator as BlueprintModelGenerator;
use Blueprint\Lexers\ModelLexer;
use Dakotajune\BlueprintModels\Generators\ModelGenerator;
use Dakotajune\BlueprintModels\Lexers\ModelOptionsLexer;
use Dakotajune\BlueprintModels\Support\ModelOptionsRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use ReflectionProperty;
use Throwable;

/**
 * Deliberately NOT a DeferrableProvider: Blueprint's own provider already defers Blueprint::class, and two deferred
 * providers cannot both own one service.
 */
final class BlueprintModelsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfig();

        $this->app->singleton(ModelOptionsRegistry::class);

        // extend() runs when Blueprint is resolved, whatever order the providers loaded in.
        $this->app->extend(Blueprint::class, fn (Blueprint $blueprint, $app): Blueprint => self::extendBlueprint($blueprint, $app));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/blueprint_models.php' => config_path('blueprint_models.php'),
            ], 'blueprint-models-config');
        }
    }

    /**
     * Public and static so tests and other tooling can build the same Blueprint the provider does.
     */
    public static function extendBlueprint(Blueprint $blueprint, Container $app): Blueprint
    {
        self::dropBlueprintModelLexer($blueprint);

        $blueprint->registerLexer(new ModelOptionsLexer($app->make(ModelOptionsRegistry::class)));
        $blueprint->swapGenerator(BlueprintModelGenerator::class, new ModelGenerator($app->make('files')));

        return $blueprint;
    }

    /**
     * ModelOptionsLexer runs Blueprint's ModelLexer itself, after taking `extends:` and `use:` out of the way. If the
     * original stayed registered it would run first on the raw draft, and a `use: [A, B]` list would reach it as a
     * column definition and fail. Blueprint has no way to unregister a lexer, hence the reflection. Should a future
     * Blueprint rename the property this does nothing, and everything except list-style `use:` still works.
     */
    private static function dropBlueprintModelLexer(Blueprint $blueprint): void
    {
        try {
            $property = new ReflectionProperty(Blueprint::class, 'lexers');
            $lexers = $property->getValue($blueprint);

            $property->setValue($blueprint, array_values(array_filter(
                $lexers,
                fn (object $lexer): bool => ! ($lexer::class === ModelLexer::class),
            )));
        } catch (Throwable) {
            // Left as is; see above.
        }
    }

    /**
     * Like mergeConfigFrom(), one level deeper, so a published config that sets `schema.mode` keeps `schema.enabled`.
     */
    private function mergeConfig(): void
    {
        $defaults = require __DIR__ . '/../config/blueprint_models.php';
        $current = (array) $this->app['config']->get('blueprint_models', []);

        $this->app['config']->set('blueprint_models', array_replace_recursive($defaults, $current));
    }
}
