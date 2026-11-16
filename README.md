# dakotajune/blueprint_models

Richer Eloquent models for [Laravel Blueprint](https://blueprint.laravelshift.com). Install it and `php artisan blueprint:build` writes models with:

- **modern attributes**: `#[Fillable([...])]`, `#[Hidden([...])]`, `#[Table('x')]`, `#[Connection('x')]`, `#[WithoutTimestamps]`, `#[WithoutIncrementing]`, `#[Unguarded]`
- **default values**: `default:` in a column becomes `protected $attributes`, not just a migration default
- **meaningful docblocks**: `@property` lines with types and your column comments, `@property-read` for relationships, `@return HasMany<Phone, $this>` generics, and a one-line summary on every relationship
- **`_schema()`**: the whole table definition, kept in the model as a commented-out `private function _schema(): void`
- **`extends:`** a base model of your own and **`use:`** traits

```yaml
models:
  ContactType:
    extends: MyBaseModel
    use: ValidatingTrait, App\Concerns\Auditable
    meta:
      description: The kind of a contact, such as customer or supplier.
    name: string:60 unique default:"Joe Dir" comment:"Display name"
    description: text nullable
    is_modifiable: boolean default:true
    is_deletable: boolean default:true
    timestamps
    softDeletes
```

```php
/**
 * The kind of a contact, such as customer or supplier.
 *
 * @property int $id
 * @property string $name Display name
 * @property string|null $description
 * ...
 */
#[Fillable(['name', 'description', 'is_modifiable', 'is_deletable'])]
class ContactType extends MyBaseModel
{
    /** @use HasFactory<\Database\Factories\ContactTypeFactory> */
    use HasFactory, SoftDeletes, ValidatingTrait;

    protected $attributes = [
        'name' => 'Joe Dir',
        'is_modifiable' => true,
        'is_deletable' => true,
    ];

    // ... casts(), then the commented-out _schema(), then relationships
}
```

## Install

```bash
composer require --dev dakotajune/blueprint_models
php artisan vendor:publish --tag=blueprint-models-config   # optional
```

Needs Blueprint 2.10 or later, any release. Nothing to register.

## Using it

### Attributes

Laravel reads these attributes from **version 13** on. On an older Laravel they would be ignored without a word, so by default (`'attributes' => null`) the package writes them only when the app has them, and the classic `$fillable`, `$hidden`, `$table` ... properties otherwise. Set `true` or `false` to decide yourself.

| Draft | Attribute |
|---|---|
| columns that are mass assignable | `#[Fillable([...])]` (left out when there are none) |
| `blueprint.use_guarded` | `#[Unguarded]` |
| `password`, `remember_token` | `#[Hidden([...])]` |
| `meta: connection:` | `#[Connection('x')]` |
| `meta: table:`, and pivots | `#[Table('x')]` |
| `timestamps: false` | `#[WithoutTimestamps]` |
| `id: false` | `#[WithoutIncrementing]` |
| a pivot that has an id | `#[Table('x', incrementing: true)]`, as Blueprint's `$incrementing = true` |

Casts stay a `casts()` method, and defaults stay `$attributes`: Laravel has no attribute for either. A long list wraps onto one column per line past `line_width` (100).

### Defaults

`name: string default:"Joe Dir"` gives `'name' => 'Joe Dir'`. Booleans and numbers are written as such (`true`, `10`, `9.5`), `null` as `null`, everything else as a quoted string. Blueprint reads a default up to the first `:`, so a JSON default such as `default:'{"a":1}'` is cut short in the migration too; set that kind of default by hand.

### `_schema()`

The code is Blueprint's own migration code for the model, asked through a subclass, so the two can't drift apart.

```php
    // The table definition, as Blueprint wrote it into the migration. Reference only: uncomment to use.
    // private function _schema(): void
    // {
    //     \Illuminate\Support\Facades\Schema::create('contact_types', function (\Illuminate\Database\Schema\Blueprint $table) {
    //         $table->id();
    //         $table->string('name', 60)->unique()->default('Joe Dir');
    //         ...
```

Fully qualified names, so uncommenting needs no `use` lines. The whole method is commented out, which keeps IDEs and static analysis from reporting an unused private method. `schema.mode => 'method'` writes a real method with only its body commented out; `schema.enabled => false` leaves it out.

### Docblocks

On by default (`'docblocks' => true`). `null` follows Blueprint's `generate_phpdocs`, `false` turns them off. The class summary is `meta: description:`, or "Eloquent model for the `x` table." Column `comment:` modifiers become the text after a `@property`. Decimal columns read as `string`, which is what Eloquent's decimal cast returns.

### `extends:` and `use:`

```yaml
    extends: MyBaseModel
    use: ValidatingTrait, SoftDeletes        # or  use: [ValidatingTrait, SoftDeletes]
```

- A name with a backslash is a class: `extends: App\Support\BaseModel` adds `use App\Support\BaseModel;`. A leading backslash and a `use ...;` wrapper are both accepted.
- A name without one is looked up in `aliases` (config; `SoftDeletes`, `HasUuids`, `HasUlids`, `Prunable`, `MassPrunable`, `Notifiable`, `Authenticatable` are there), then in `short_name_namespace` if you set one, and otherwise taken to be in the model's own namespace: no import.
- A model in a sub-namespace (`Billing/Invoice`) should use the full name for a base class that lives elsewhere.
- `meta: extends:` and `meta: traits:` (Blueprint master) are read the same way. Block lists with dashes can't be used: Blueprint strips leading dashes from the whole draft before parsing it. Use the comma form or `[A, B]`.

## How it works, and what it touches

- `ModelOptionsLexer` takes `extends`, `use` and `meta.description` out of each model, passes the rest to Blueprint's own `ModelLexer`, and keeps what it took for the generator. Without this `ModelLexer` would read `extends` and `use` as columns and put them in the migration.
- It replaces Blueprint's `ModelLexer` in the registered lexers, through reflection, because Blueprint has no way to unregister one. If a future Blueprint renames that property nothing breaks, but a list-style `use: [A, B]` then fails inside `ModelLexer`; the comma form still works.
- `ModelGenerator` replaces Blueprint's (`swapGenerator`). It borrows Blueprint's relationship, fillable, hidden and cast rules and stubs, so what Blueprint knows still applies. Model files are overwritten on every build, as with Blueprint.
- A `stubs/blueprint/model.class.stub` of your own is used when it has the `{{ extends }}` and `{{ traits }}` placeholders (Blueprint master's stub does). Blueprint 2.13's stub has neither, so the package's own is used.
- Every generated file has one kind of line ending, whatever the stubs on disk use.

## Config

`config/blueprint_models.php`, every key documented there: `attributes`, `line_width`, `defaults`, `schema.enabled`, `schema.mode`, `docblocks`, `short_name_namespace`, `aliases`.

## Not included

Observers, scopes and factories as attributes (`#[ObservedBy]`, `#[ScopedBy]`, `#[UseFactory]`), `#[DateFormat]`, `#[Appends]`, `#[Touches]`: Blueprint has no draft syntax for them, so there is nothing to generate them from.

## Tests

```bash
composer install
vendor/bin/pest
```
