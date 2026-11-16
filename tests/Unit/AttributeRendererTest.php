<?php

declare(strict_types=1);

use Dakotajune\BlueprintModels\Support\AttributeRenderer;

it('renders the three shapes', function () {
    expect(AttributeRenderer::render('WithoutTimestamps', null))->toBe('#[WithoutTimestamps]')
        ->and(AttributeRenderer::render('Table', 'crm_contacts'))->toBe("#[Table('crm_contacts')]")
        ->and(AttributeRenderer::render('Fillable', ['name', 'email']))->toBe("#[Fillable(['name', 'email'])]");
});

it('wraps a long list, one column per line', function () {
    $columns = ['first_name', 'last_name', 'email_address', 'phone_number', 'street_address'];

    expect(AttributeRenderer::render('Fillable', $columns, 60))
        ->toBe("#[Fillable([\n    'first_name',\n    'last_name',\n    'email_address',\n    'phone_number',\n    'street_address',\n])]");
});

it('never wraps a single column', function () {
    expect(AttributeRenderer::render('Fillable', ['a_very_long_column_name_indeed'], 10))
        ->toBe("#[Fillable(['a_very_long_column_name_indeed'])]");
});

it('renders an array literal with string keys and lists', function () {
    expect(AttributeRenderer::array(['name' => "'x'", 'on' => 'true'], 4))->toBe("[\n        'name' => 'x',\n        'on' => true,\n    ]")
        ->and(AttributeRenderer::array(["'a'", "'b'"], 8))->toBe("[\n            'a',\n            'b',\n        ]");
});
