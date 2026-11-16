<?php

declare(strict_types=1);

const CONTACT_TYPE = <<<'YAML'
models:
  ContactType:
    name: string:60 unique default:"Joe Dir" comment:"Display name"
    description: text nullable
    is_modifiable: boolean default:true
    is_deletable: boolean default:true
    timestamps
    softDeletes
YAML;

// -- attributes ----------------------------------------------------------------------------------------------------

it('writes modern attributes instead of properties', function () {
    $model = models(CONTACT_TYPE)['ContactType'];

    expect($model)
        ->toContain("#[Fillable(['name', 'description', 'is_modifiable', 'is_deletable'])]")
        ->toContain('use Illuminate\Database\Eloquent\Attributes\Fillable;')
        ->not->toContain('protected $fillable')
        ->not->toContain('#[Hidden')
        ->and(lints($model))->toBeTrue();
});

it('covers connection, table, timestamps, hidden and unguarded', function () {
    $yaml = <<<'YAML'
    models:
      Contact:
        meta:
          connection: tenant
          table: crm_contacts
        name: string
        password: string
        timestamps: false
    YAML;

    $model = models($yaml)['Contact'];

    expect($model)
        ->toContain("#[Connection('tenant')]")
        ->toContain("#[Table('crm_contacts')]")
        ->toContain('#[WithoutTimestamps]')
        ->toContain("#[Hidden(['password'])]")
        ->not->toContain('$timestamps')
        ->and(lints($model))->toBeTrue();

    config(['blueprint.use_guarded' => true]);

    expect(models($yaml)['Contact'])->toContain('#[Unguarded]')->not->toContain('#[Fillable');

    config(['blueprint.use_guarded' => false]);
});

it('wraps a long fillable list and leaves out an empty one', function () {
    $long = models(<<<'YAML'
    models:
      Contact:
        first_name: string
        last_name: string
        email_address: string
        phone_number: string
        street_address: string
    YAML, ['line_width' => 60])['Contact'];

    expect($long)->toContain("#[Fillable([\n    'first_name',")->and(lints($long))->toBeTrue();

    $none = models("models:\n  Tag:\n    timestamps\n")['Tag'];

    expect($none)->not->toContain('Fillable')->and(lints($none))->toBeTrue();
});

it('marks a model without a primary key as not incrementing, and a pivot with one as incrementing', function () {
    $files = models(<<<'YAML'
    models:
      Note:
        id: false
        body: text
      ContactTag:
        meta:
          pivot: true
        contact_id: id
        tag_id: id
    YAML);

    expect($files['Note'])->toContain('#[WithoutIncrementing]')
        ->and($files['ContactTag'])->toContain("#[Table('contact_tags', incrementing: true)]")
        ->and(lints($files['Note']))->toBeTrue()
        ->and(lints($files['ContactTag']))->toBeTrue();
});

it('writes a name that clashes with an attribute in full', function () {
    $model = models("models:\n  Fillable:\n    name: string\n")['Fillable'];

    expect($model)
        ->toContain("#[\\Illuminate\\Database\\Eloquent\\Attributes\\Fillable(['name'])]")
        ->not->toContain('use Illuminate\Database\Eloquent\Attributes\Fillable;')
        ->and(lints($model))->toBeTrue();
});

it('keeps the classic properties when attributes are off', function () {
    $model = models(CONTACT_TYPE, ['attributes' => false])['ContactType'];

    expect($model)
        ->toContain('protected $fillable = [')
        ->toContain("'description',")
        ->not->toContain('#[Fillable')
        ->not->toContain('Attributes\\')
        ->toContain('protected $attributes = [')
        ->and(lints($model))->toBeTrue();
});

// -- defaults ------------------------------------------------------------------------------------------------------

it('turns default: modifiers into $attributes', function () {
    $model = models(CONTACT_TYPE)['ContactType'];

    expect($model)
        ->toContain("protected \$attributes = [\n        'name' => 'Joe Dir',\n        'is_modifiable' => true,\n        'is_deletable' => true,\n    ];")
        ->toContain('@var array<string, mixed>')
        ->not->toContain("'description' =>");
});

it('can leave defaults to the migration', function () {
    expect(models(CONTACT_TYPE, ['defaults' => false])['ContactType'])->not->toContain('$attributes');
});

// -- _schema() -----------------------------------------------------------------------------------------------------

it('keeps the whole table definition in a commented-out _schema()', function () {
    $model = models(CONTACT_TYPE)['ContactType'];

    expect($model)
        ->toContain('    // private function _schema(): void')
        ->toContain("Schema::create('contact_types', function (\\Illuminate\\Database\\Schema\\Blueprint \$table) {")
        ->toContain("    //         \$table->string('name', 60)->unique()->default('Joe Dir')->comment('Display name');")
        ->toContain("    //         \$table->text('description')->nullable();")
        ->toContain("    //         \$table->softDeletes();")
        ->and(preg_match('/^\s*private function _schema/m', $model))->toBe(0)
        ->and(lints($model))->toBeTrue();
});

it('can write _schema() as a real method with a commented-out body', function () {
    $model = models(CONTACT_TYPE, ['schema' => ['enabled' => true, 'mode' => 'method']])['ContactType'];

    expect($model)
        ->toContain("    private function _schema(): void\n    {\n")
        ->toContain("        //     \$table->text('description')->nullable();")
        ->and(preg_match('/^\s*Schema::create|^\s*\\\\Illuminate\\\\Support\\\\Facades\\\\Schema::create/m', $model))->toBe(0)
        ->and(lints($model))->toBeTrue();
});

it('can leave _schema() out', function () {
    expect(models(CONTACT_TYPE, ['schema' => ['enabled' => false]])['ContactType'])->not->toContain('_schema');
});

it('shows foreign keys, indexes and morphs the way the migration does', function () {
    $model = models(<<<'YAML'
    models:
      Contact:
        name: string
        contact_type_id: id foreign:contact_types nullable
        relationships:
          morphTo: Subject
        indexes:
          - index: name
    YAML)['Contact'];

    expect($model)
        ->toContain("\$table->foreignId('contact_type_id')->nullable()->constrained();")
        ->toContain("\$table->morphs('subject');")
        ->toContain("\$table->index('name');")
        ->and(lints($model))->toBeTrue();
});

// -- extends and use -----------------------------------------------------------------------------------------------

it('extends a base model next to it without importing it', function () {
    $yaml = "models:\n  ContactType:\n    extends: MyBaseModel\n    name: string\n";
    $model = models($yaml)['ContactType'];

    expect($model)
        ->toContain('class ContactType extends MyBaseModel')
        ->not->toContain('use MyBaseModel;')
        ->not->toContain('use Illuminate\Database\Eloquent\Model;')
        ->and(array_keys(tree($yaml)->models()['ContactType']->columns()))->toBe(['id', 'name'])
        ->and(lints($model))->toBeTrue();
});

it('imports a qualified base class and strips a leading backslash', function () {
    $model = models("models:\n  ContactType:\n    extends: \\App\\Support\\BaseModel\n    name: string\n")['ContactType'];

    expect($model)->toContain('use App\Support\BaseModel;')->toContain('class ContactType extends BaseModel');
});

it('still reads extends from meta', function () {
    $model = models("models:\n  ContactType:\n    meta:\n      extends: App\\Support\\BaseModel\n    name: string\n")['ContactType'];

    expect($model)->toContain('use App\Support\BaseModel;')->toContain('extends BaseModel');
});

it('adds traits from use: as a string, a list, or with the use keyword', function () {
    foreach ([
        'use: ValidatingTrait, App\Concerns\Auditable',
        'use: [ValidatingTrait, App\Concerns\Auditable]',
        "use: ['use ValidatingTrait;', 'use App\Concerns\Auditable;']",
    ] as $line) {
        $yaml = "models:\n  ContactType:\n    {$line}\n    name: string\n";
        $model = models($yaml)['ContactType'];

        expect($model)
            ->toContain('use Auditable, HasFactory, ValidatingTrait;')
            ->toContain('use App\Concerns\Auditable;')
            ->not->toContain('use ValidatingTrait;')
            ->and(array_keys(tree($yaml)->models()['ContactType']->columns()))->toBe(['id', 'name'])
            ->and(lints($model))->toBeTrue();
    }
});

it('knows the Illuminate traits by their short name and merges with meta.traits and softDeletes', function () {
    $model = models("models:\n  ContactType:\n    use: SoftDeletes, HasUuids\n    meta:\n      traits: App\\Concerns\\Auditable\n    name: string\n    softDeletes\n")['ContactType'];

    expect($model)
        ->toContain('use Auditable, HasFactory, HasUuids, SoftDeletes;')
        ->toContain('use Illuminate\Database\Eloquent\SoftDeletes;')
        ->toContain('use Illuminate\Database\Eloquent\Concerns\HasUuids;')
        ->and(substr_count($model, 'use Illuminate\Database\Eloquent\SoftDeletes;'))->toBe(1);
});

it('qualifies short names against short_name_namespace', function () {
    $model = models("models:\n  ContactType:\n    extends: Base\n    use: ValidatingTrait\n    name: string\n", ['short_name_namespace' => 'App\Support'])['ContactType'];

    expect($model)->toContain('use App\Support\Base;')->toContain('use App\Support\ValidatingTrait;');
});

it('says what is wrong with an extends or use it cannot read', function () {
    expect(fn () => models("models:\n  ContactType:\n    extends: [A, B]\n    name: string\n"))
        ->toThrow(InvalidArgumentException::class, 'ContactType: extends takes exactly one class')
        ->and(fn () => models("models:\n  ContactType:\n    use: true\n    name: string\n"))
        ->toThrow(InvalidArgumentException::class, 'ContactType: use');
});

// -- docblocks -----------------------------------------------------------------------------------------------------

it('documents the class: properties, comments, nullability and a description', function () {
    $model = models(<<<'YAML'
    models:
      ContactType:
        meta:
          description: The kind of a contact, such as customer or supplier.
        name: string:60 comment:"Display name"
        notes: text nullable
        sort: integer
        price: decimal:8,2
        options: json
        is_active: boolean
        started_on: date nullable
        timestamps
        softDeletes
    YAML)['ContactType'];

    expect($model)
        ->toContain(' * The kind of a contact, such as customer or supplier.')
        ->toContain(' * @property int $id')
        ->toContain(' * @property string $name Display name')
        ->toContain(' * @property string|null $notes')
        ->toContain(' * @property int $sort')
        ->toContain(' * @property string $price')
        ->toContain(' * @property array<array-key, mixed> $options')
        ->toContain(' * @property bool $is_active')
        ->toContain(' * @property \Carbon\Carbon|null $started_on')
        ->toContain(' * @property \Carbon\Carbon|null $created_at')
        ->toContain(' * @property \Carbon\Carbon|null $deleted_at')
        ->and(lints($model))->toBeTrue();
});

it('describes the table when there is no description', function () {
    expect(models(CONTACT_TYPE)['ContactType'])->toContain(' * Eloquent model for the `contact_types` table.');
});

it('documents relationships with a summary, generics and a @property-read', function () {
    $files = models(<<<'YAML'
    models:
      Contact:
        name: string
        contact_type_id: id foreign:contact_types nullable
        relationships:
          hasMany: Phone
          belongsToMany: Tag
          morphTo: Subject
      Phone:
        contact_id: id
      Tag:
        name: string
      ContactType:
        name: string
    YAML);
    $contact = $files['Contact'];

    expect($contact)
        ->toContain('Get the contact type this contact belongs to.')
        ->toContain('@return BelongsTo<ContactType, $this>')
        ->toContain('Get the phones that belong to this contact.')
        ->toContain('@return HasMany<Phone, $this>')
        ->toContain('Get the tags attached to this contact.')
        ->toContain('@return BelongsToMany<Tag, $this>')
        ->toContain('@return MorphTo<\Illuminate\Database\Eloquent\Model, $this>')
        ->toContain(' * @property-read ContactType $contactType')
        ->toContain(' * @property-read \Illuminate\Database\Eloquent\Collection<int, Phone> $phones')
        ->toContain(' * @property-read \Illuminate\Database\Eloquent\Model|null $subject')
        ->and(lints($contact))->toBeTrue()
        ->and(lints($files['Phone']))->toBeTrue()
        ->and($files['Phone'])->toContain('@return BelongsTo<Contact, $this>');
});

it('keeps the HasFactory docblock and can drop every docblock', function () {
    expect(models(CONTACT_TYPE)['ContactType'])->toContain('/** @use HasFactory<\Database\Factories\ContactTypeFactory> */');

    $plain = models(CONTACT_TYPE, ['docblocks' => false])['ContactType'];

    expect($plain)
        ->not->toContain('/**')
        ->not->toContain('@property')
        ->toContain("'is_modifiable' => 'boolean'")
        ->and(lints($plain))->toBeTrue();
});

// -- everything together -------------------------------------------------------------------------------------------

it('produces valid PHP for the whole draft', function () {
    $files = models(<<<'YAML'
    models:
      Contact:
        extends: \App\Support\BaseModel
        use: [App\Concerns\Auditable, SoftDeletes]
        meta:
          connection: tenant
          table: crm_contacts
          description: A person we talk to.
        name: string:100 default:"Unknown" comment:"Full name"
        password: string
        contact_type_id: id foreign:contact_types nullable
        options: json nullable
        relationships:
          belongsTo: ContactType
          hasMany: Phone
          morphTo: Subject
        softDeletes
      Phone:
        number: string:20 default:"555"
        contact_id: id
        timestamps: false
      ContactType:
        name: string:60 default:"Joe Dir"
    YAML);

    foreach ($files as $name => $source) {
        expect(lints($source))->toBeTrue("{$name} does not parse");
    }

    expect(array_keys($files))->toBe(['Contact', 'Phone', 'ContactType']);
});

it('writes one kind of line ending even when a stub on disk uses another', function () {
    @mkdir(base_path('stubs/blueprint'), 0777, true);
    file_put_contents(
        base_path('stubs/blueprint/model.class.stub'),
        str_replace("\n", "\r\n", "<?php\n\nnamespace {{ namespace }};\n\n{{ imports }}\n\nclass {{ class }} extends {{ extends }}\n{\n    {{ traits }}\n}\n"),
    );

    $model = models(CONTACT_TYPE)['ContactType'];

    expect(str_replace(PHP_EOL, '', $model))->not->toContain("\r")->not->toContain("\n")
        ->and($model)->toContain('#[Fillable(')
        ->and($model)->toContain('@property')
        ->and(lints($model))->toBeTrue();
});
