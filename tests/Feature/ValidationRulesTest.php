<?php

declare(strict_types=1);

const VALIDATED_CONTACT_TYPE = <<<'YAML'
models:
  ContactType:
    name: string:60 unique default:"Joe Dir" comment:"Display name"
    description: text nullable
    is_modifiable: boolean default:true
    timestamps
    softDeletes
YAML;

const VALIDATED_RULES = "protected \$rules = [\n"
    . "        'name' => 'sometimes|required|string|max:60|unique:contact_types,name',\n"
    . "        'description' => 'nullable|string',\n"
    . "        'is_modifiable' => 'sometimes|required|boolean',\n"
    . '    ];';

/** The helper turns validation off for every other test; these are about it. */
function validated(string $yaml, array $config = []): array
{
    return models($yaml, ['validation.enabled' => true, ...$config]);
}

it('is on in the config the package ships', function () {
    expect(config('blueprint_models.validation.enabled'))->toBeTrue()
        ->and(config('blueprint_models.validation.trait'))->toBe('Watson\Validating\ValidatingTrait')
        ->and(config('blueprint_models.validation.guess_from_names'))->toBeTrue();
});

it('writes the rules the columns imply and the trait that runs them', function () {
    $model = validated(VALIDATED_CONTACT_TYPE)['ContactType'];

    expect($model)
        ->toContain(VALIDATED_RULES)
        ->toContain('use Watson\Validating\ValidatingTrait;')
        ->toContain('use HasFactory, SoftDeletes, ValidatingTrait;')
        ->toContain('@var array<string, string>')
        ->and(lints($model))->toBeTrue();
});

it('keeps the primary key, timestamps and soft deletes out of the rules', function () {
    $model = validated(VALIDATED_CONTACT_TYPE)['ContactType'];

    // Blueprint's casts do list `id`, so only the rules block is looked at.
    preg_match('/protected \$rules = \[(.*?)\];/s', $model, $block);

    expect($block[1] ?? '')->not->toBe('')
        ->not->toContain("'id' =>")
        ->not->toContain("'created_at' =>")
        ->not->toContain("'deleted_at' =>");
});

it('puts the rules after the defaults and before the casts', function () {
    $model = validated(VALIDATED_CONTACT_TYPE)['ContactType'];
    $cast = strpos($model, "'is_modifiable' => 'boolean'");

    expect($cast)->not->toBeFalse()
        ->and(strpos($model, 'protected $attributes'))->toBeLessThan(strpos($model, 'protected $rules'))
        ->and(strpos($model, 'protected $rules'))->toBeLessThan($cast);
});

it('follows the docblocks setting and the attributes setting', function () {
    $plain = validated(VALIDATED_CONTACT_TYPE, ['docblocks' => false])['ContactType'];

    expect($plain)->toContain(VALIDATED_RULES)->not->toContain('/**')->and(lints($plain))->toBeTrue();

    $classic = validated(VALIDATED_CONTACT_TYPE, ['attributes' => false])['ContactType'];

    expect($classic)->toContain(VALIDATED_RULES)->toContain('protected $fillable = [')->and(lints($classic))->toBeTrue();
});

it('writes neither rules nor trait when it is switched off', function () {
    $model = models(VALIDATED_CONTACT_TYPE, ['validation.enabled' => false])['ContactType'];

    expect($model)->not->toContain('$rules')->not->toContain('ValidatingTrait')->not->toContain('Watson')
        ->and(lints($model))->toBeTrue();
});

it('writes neither when a model has no column to validate', function () {
    $model = validated("models:\n  Tag:\n    timestamps\n")['Tag'];

    expect($model)->not->toContain('$rules')->not->toContain('ValidatingTrait')->and(lints($model))->toBeTrue();
});

it('does not add the trait a second time when the draft already lists it', function () {
    foreach (['use: ValidatingTrait', 'use: Watson\Validating\ValidatingTrait', 'use: [Watson\Validating\ValidatingTrait]'] as $line) {
        $model = validated("models:\n  ContactType:\n    {$line}\n    name: string\n")['ContactType'];

        // `ValidatingTrait;` straight after `HasFactory, ` means it is in the list once: a second copy would follow it.
        expect($model)
            ->toContain('use HasFactory, ValidatingTrait;')
            ->toContain("protected \$rules = [\n        'name' => 'required|string|max:255',\n    ];")
            ->and(lints($model))->toBeTrue();
    }
});

it('uses the trait the config names', function () {
    $model = validated("models:\n  ContactType:\n    name: string\n", ['validation.trait' => '\App\Concerns\Validates'])['ContactType'];

    expect($model)
        ->toContain('use App\Concerns\Validates;')
        ->toContain('use HasFactory, Validates;')
        ->not->toContain('Watson')
        ->and(lints($model))->toBeTrue();
});

it('points foreign keys, belongsTo columns and morphs at what they refer to', function () {
    $model = validated(<<<'YAML'
    models:
      Contact:
        name: string
        email: string unique
        contact_type_id: id foreign:contact_types nullable
        relationships:
          belongsTo: User
          morphTo: Subject
      ContactType:
        name: string
      User:
        name: string
    YAML)['Contact'];

    expect($model)
        ->toContain("'email' => 'required|string|email|max:255|unique:contacts,email',")
        ->toContain("'contact_type_id' => 'nullable|integer|exists:contact_types,id',")
        ->toContain("'user_id' => 'required|integer|exists:users,id',")
        ->toContain("'subject_id' => 'required|integer',")
        ->toContain("'subject_type' => 'required|string|max:255',")
        ->and(lints($model))->toBeTrue();
});

it('can leave the email and url guesses out', function () {
    $model = validated("models:\n  Contact:\n    email: string\n", ['validation.guess_from_names' => false])['Contact'];

    expect($model)->toContain("'email' => 'required|string|max:255',")->not->toContain('|email|');
});

it('produces valid PHP for a whole draft with validation on', function () {
    $files = validated(<<<'YAML'
    models:
      Contact:
        extends: \App\Support\BaseModel
        use: [App\Concerns\Auditable, SoftDeletes]
        meta:
          connection: tenant
          table: crm_contacts
        name: string:100 default:"Unknown" comment:"Full name"
        password: string
        contact_type_id: id foreign:contact_types nullable
        options: json nullable
        status: enum:pending,active
        price: decimal:8,2
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
        expect(lints($source))->toBeTrue("{$name} does not parse")
            ->and($source)->toContain('use Watson\Validating\ValidatingTrait;');
    }

    expect($files['Contact'])
        ->toContain("'status' => 'required|in:pending,active',")
        ->toContain("'price' => 'required|numeric|decimal:0,2',")
        ->toContain("'options' => 'nullable|json',");
});
