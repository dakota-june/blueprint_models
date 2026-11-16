<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Modern attributes
    |--------------------------------------------------------------------------
    | true  : #[Fillable([...])], #[Hidden([...])], #[Table('x')], #[Connection('x')], #[WithoutTimestamps],
    |         #[WithoutIncrementing] and #[Unguarded] instead of $fillable, $hidden, $table, ... properties.
    | false : the classic properties Blueprint generates.
    | null  : decide per project: attributes when the installed Laravel ships them, properties otherwise.
    |
    | Laravel only reads these attributes from version 13 on. On an older Laravel they would be ignored silently,
    | which is why the default is null and not true.
    */
    'attributes' => null,

    /*
    | Wrap an attribute onto several lines once the single-line form is longer than this.
    */
    'line_width' => 100,

    /*
    |--------------------------------------------------------------------------
    | Default attribute values
    |--------------------------------------------------------------------------
    | Turn `name: string default:"Joe Dir"` into `protected $attributes = ['name' => 'Joe Dir']`. Blueprint itself only
    | puts the default in the migration, so a new, unsaved model has no value until it is read back from the database.
    */
    'defaults' => true,

    /*
    |--------------------------------------------------------------------------
    | _schema() reference
    |--------------------------------------------------------------------------
    | The whole table definition, the same code Blueprint puts in the migration, kept inside the model for reference.
    |
    | mode: 'commented' - the whole `private function _schema(): void` is written as // comments (nothing to run, and
    |                     no "unused private method" noise from IDEs and static analysis). Uncomment to use it.
    |       'method'    - a real private method; the Schema::create() call inside it is commented out.
    */
    'schema' => [
        'enabled' => true,
        'mode' => 'commented',
    ],

    /*
    |--------------------------------------------------------------------------
    | Docblocks
    |--------------------------------------------------------------------------
    | Class @property / @property-read lines, relationship @return generics, and docblocks for $attributes, casts()
    | and _schema(), plus the `@use HasFactory<...>` line. true by default; null follows Blueprint's own
    | `generate_phpdocs` setting instead (off unless you turned it on).
    */
    'docblocks' => true,

    /*
    |--------------------------------------------------------------------------
    | Class names used by `extends:` and `use:`
    |--------------------------------------------------------------------------
    | A name with a backslash is a fully qualified class and gets a `use` import. A name without one is looked up in
    | `aliases` first, then in `short_name_namespace`, and is otherwise assumed to live in the model's own namespace.
    */
    'short_name_namespace' => null,

    'aliases' => [
        'SoftDeletes' => 'Illuminate\Database\Eloquent\SoftDeletes',
        'HasUuids' => 'Illuminate\Database\Eloquent\Concerns\HasUuids',
        'HasUlids' => 'Illuminate\Database\Eloquent\Concerns\HasUlids',
        'Prunable' => 'Illuminate\Database\Eloquent\Prunable',
        'MassPrunable' => 'Illuminate\Database\Eloquent\MassPrunable',
        'Notifiable' => 'Illuminate\Notifications\Notifiable',
        'Authenticatable' => 'Illuminate\Foundation\Auth\User',
    ],
];
