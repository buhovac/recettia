<?php

use App\Services\RecipeAnalysis\InvalidRecipeAnalysis;
use App\Services\RecipeAnalysis\RecipeAnalysisValidator;

function validRecipePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'schema_version' => 1,
        'dish' => ['name' => 'Risotto aux crevettes', 'portions' => 4],
        'ingredients' => [
            ['name' => 'riz arborio', 'quantity' => 0.32, 'unit' => 'kg', 'note' => null],
            ['name' => 'vin blanc sec', 'quantity' => 15, 'unit' => 'cl', 'note' => null],
            ['name' => 'ail', 'quantity' => 2, 'unit' => 'piece', 'note' => 'gousses'],
            ['name' => 'beurre', 'quantity' => 60, 'unit' => 'g', 'note' => null],
        ],
        'allergens' => [
            ['code' => 'milk', 'source_ingredients' => ['beurre']],
            ['code' => 'sulphites', 'source_ingredients' => ['vin blanc sec']],
        ],
    ], $overrides);
}

function invalidRecipeErrors(array $payload): array
{
    try {
        (new RecipeAnalysisValidator)->validate($payload);
    } catch (InvalidRecipeAnalysis $exception) {
        return $exception->errors;
    }

    return [];
}

test('a valid payload is accepted and units are normalised', function () {
    $recipe = (new RecipeAnalysisValidator)->validate(validRecipePayload());

    expect($recipe->dishName)->toBe('Risotto aux crevettes')
        ->and($recipe->portions)->toBe(4)
        ->and($recipe->ingredients[0])->toMatchArray(['quantity' => 320.0, 'unit' => 'g'])
        ->and($recipe->ingredients[1])->toMatchArray(['quantity' => 150.0, 'unit' => 'ml'])
        ->and($recipe->ingredients[2])->toMatchArray(['unit' => 'piece', 'note' => 'gousses'])
        ->and(array_column($recipe->allergens, 'code'))->toBe(['milk', 'sulphites'])
        ->and($recipe->allergens[0]['label'])->toBe('Lait')
        ->and($recipe->warnings)->toBe([]);
});

test('litres are converted to millilitres', function () {
    $recipe = (new RecipeAnalysisValidator)->validate(validRecipePayload([
        'ingredients' => [1 => ['quantity' => 1.5, 'unit' => 'l']],
    ]));

    expect($recipe->ingredients[1])->toMatchArray(['quantity' => 1500.0, 'unit' => 'ml']);
});

test('blocking rules reject the whole response', function (array $payload, string $expectedError) {
    expect(invalidRecipeErrors($payload))->toContain($expectedError);
})->with([
    'unsupported schema version' => [validRecipePayload(['schema_version' => 2]), 'schema_version missing or not supported'],
    'empty ingredient list' => [array_replace(validRecipePayload(), ['ingredients' => []]), 'ingredients missing or empty'],
    'zero quantity' => [validRecipePayload(['ingredients' => [0 => ['quantity' => 0]]]), 'ingredients.0.quantity must be a number > 0'],
    'negative quantity' => [validRecipePayload(['ingredients' => [0 => ['quantity' => -3]]]), 'ingredients.0.quantity must be a number > 0'],
    'quantity as text' => [validRecipePayload(['ingredients' => [0 => ['quantity' => 'beaucoup']]]), 'ingredients.0.quantity must be a number > 0'],
    'unit not allowed' => [validRecipePayload(['ingredients' => [0 => ['unit' => 'cup']]]), 'ingredients.0.unit not allowed'],
    'allergen outside the 14' => [validRecipePayload(['allergens' => [0 => ['code' => 'tomato']]]), 'allergens.0.code not in the closed list of 14'],
    'missing dish name' => [validRecipePayload(['dish' => ['name' => '']]), 'dish.name missing or not a string'],
    'empty response' => [[], 'schema_version missing or not supported'],
]);

test('non-blocking problems become warnings', function () {
    $payload = validRecipePayload([
        'dish' => ['portions' => null],
        'allergens' => [0 => ['source_ingredients' => ['crème']]],
    ]);
    $payload['price'] = 12;

    $recipe = (new RecipeAnalysisValidator)->validate($payload);

    expect($recipe->portions)->toBeNull()
        ->and($recipe->warnings)->toHaveCount(3)
        ->and(implode(' ', $recipe->warnings))
        ->toContain('portions')
        ->toContain('crème')
        ->toContain('price');
});
