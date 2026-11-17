<?php

declare(strict_types=1);

use Blueprint\Models\Column;
use Blueprint\Models\Model;
use Blueprint\Tree;
use Dakotajune\BlueprintModels\Support\RuleBuilder;

/**
 * @return array<string, string>
 */
function rulesOf(Column ...$columns): array
{
    $model = new Model('Contact');

    foreach ($columns as $column) {
        $model->addColumn($column);
    }

    return RuleBuilder::forModel($model);
}

it('asks for a value unless the column is nullable or has a default', function () {
    expect(rulesOf(new Column('name', 'string')))->toBe(['name' => 'required|string|max:255'])
        ->and(rulesOf(new Column('name', 'string', ['nullable'])))->toBe(['name' => 'nullable|string|max:255'])
        ->and(rulesOf(new Column('name', 'string', [['default' => 'Joe Dir']])))->toBe(['name' => 'sometimes|required|string|max:255'])
        ->and(rulesOf(new Column('name', 'string', [['default' => 'null']])))->toBe(['name' => 'nullable|string|max:255'])
        ->and(rulesOf(new Column('at', 'nullableTimestamp')))->toBe(['at' => 'nullable']);
});

it('reads the length of a string, and the type of everything else', function () {
    expect(rulesOf(new Column('title', 'string', [], ['60'])))->toBe(['title' => 'required|string|max:60'])
        ->and(rulesOf(new Column('bio', 'text')))->toBe(['bio' => 'required|string'])
        ->and(rulesOf(new Column('age', 'integer')))->toBe(['age' => 'required|integer'])
        ->and(rulesOf(new Column('age', 'unsignedInteger')))->toBe(['age' => 'required|integer|min:0'])
        ->and(rulesOf(new Column('age', 'smallInteger', ['unsigned'])))->toBe(['age' => 'required|integer|min:0'])
        ->and(rulesOf(new Column('rating', 'float')))->toBe(['rating' => 'required|numeric'])
        ->and(rulesOf(new Column('price', 'decimal', [], ['8', '3'])))->toBe(['price' => 'required|numeric|decimal:0,3'])
        ->and(rulesOf(new Column('price', 'unsignedDecimal')))->toBe(['price' => 'required|numeric|min:0|decimal:0,2'])
        ->and(rulesOf(new Column('is_active', 'boolean')))->toBe(['is_active' => 'required|boolean'])
        ->and(rulesOf(new Column('born_on', 'date')))->toBe(['born_on' => 'required|date'])
        ->and(rulesOf(new Column('seen_at', 'dateTime')))->toBe(['seen_at' => 'required|date'])
        ->and(rulesOf(new Column('opens_at', 'time')))->toBe(['opens_at' => 'required|date_format:H:i:s,H:i'])
        ->and(rulesOf(new Column('options', 'json')))->toBe(['options' => 'required|json'])
        ->and(rulesOf(new Column('token', 'uuid')))->toBe(['token' => 'required|uuid'])
        ->and(rulesOf(new Column('ref', 'ulid')))->toBe(['ref' => 'required|ulid'])
        ->and(rulesOf(new Column('ip', 'ipAddress')))->toBe(['ip' => 'required|ip'])
        ->and(rulesOf(new Column('year_of', 'year')))->toBe(['year_of' => 'required|integer|digits:4']);
});

it('lists the values of an enum, unless one of them would break the rule syntax', function () {
    expect(rulesOf(new Column('status', 'enum', [], ['pending', 'active'])))->toBe(['status' => 'required|in:pending,active'])
        ->and(rulesOf(new Column('status', 'enum', [], ['a,b', 'c'])))->toBe(['status' => 'required'])
        ->and(rulesOf(new Column('status', 'enum', [], ['a|b', 'c'])))->toBe(['status' => 'required']);
});

it('leaves a column of an unknown type with the presence rule only', function () {
    expect(rulesOf(new Column('blob', 'binary')))->toBe(['blob' => 'required'])
        ->and(rulesOf(new Column('area', 'polygon', ['nullable'])))->toBe(['area' => 'nullable']);
});

it('adds unique for a unique column', function () {
    expect(rulesOf(new Column('name', 'string', ['unique'])))->toBe(['name' => 'required|string|max:255|unique:contacts,name']);
});

it('guesses email and url from the name, and can be told not to', function () {
    expect(rulesOf(new Column('email', 'string')))->toBe(['email' => 'required|string|email|max:255'])
        ->and(rulesOf(new Column('billing_email', 'string')))->toBe(['billing_email' => 'required|string|email|max:255'])
        ->and(rulesOf(new Column('home_url', 'string')))->toBe(['home_url' => 'required|string|url|max:255'])
        ->and(rulesOf(new Column('emailed', 'string')))->toBe(['emailed' => 'required|string|max:255']);

    $model = new Model('Contact');
    $model->addColumn(new Column('email', 'string'));

    expect(RuleBuilder::forModel($model, null, false))->toBe(['email' => 'required|string|max:255']);
});

it('leaves out keys, timestamps and what Eloquent fills in', function () {
    expect(rulesOf(
        new Column('id', 'id'),
        new Column('code', 'string', ['primary']),
        new Column('serial', 'integer', ['autoIncrement']),
        new Column('legacy_id', 'bigIncrements'),
        new Column('remember_token', 'rememberToken'),
    ))->toBe([]);
});

it('points a foreign key at the table the migration constrains it to', function () {
    $model = new Model('Contact');
    $model->addColumn(new Column('user_id', 'id'));
    $model->addColumn(new Column('contact_type_id', 'id', ['nullable', ['foreign' => 'contact_types']]));
    $model->addColumn(new Column('owner_id', 'id', [], ['user']));
    $model->addColumn(new Column('team_id', 'uuid', [['foreign' => 'teams.uuid']]));

    expect(RuleBuilder::forModel($model))->toBe([
        'user_id' => 'required|integer|exists:users,id',
        'contact_type_id' => 'nullable|integer|exists:contact_types,id',
        'owner_id' => 'required|integer|exists:users,id',
        'team_id' => 'required|uuid|exists:teams,uuid',
    ]);
});

it('takes the table of a related model from the draft', function () {
    $kind = new Model('ContactType');
    $kind->setTableName('kinds');

    $model = new Model('Contact');
    $model->addColumn(new Column('contact_type_id', 'id'));

    $tree = new Tree(['models' => ['Contact' => $model, 'ContactType' => $kind]]);

    expect(RuleBuilder::forModel($model, $tree))->toBe(['contact_type_id' => 'required|integer|exists:kinds,id']);
});

it('writes a rule for each half of a morph', function () {
    expect(rulesOf(new Column('subject', 'morphs')))->toBe([
        'subject_id' => 'required|integer',
        'subject_type' => 'required|string|max:255',
    ])->and(rulesOf(new Column('subject', 'nullableUuidMorphs')))->toBe([
        'subject_id' => 'nullable|uuid',
        'subject_type' => 'nullable|string|max:255',
    ]);
});

it('writes the morph rules for a morphTo relationship, which is no column of the model', function () {
    $model = new Model('Contact');
    $model->addRelationship('morphTo', 'Subject');

    expect(RuleBuilder::forModel($model))->toBe([
        'subject_id' => 'required|integer',
        'subject_type' => 'required|string|max:255',
    ]);
});
