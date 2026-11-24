<?php

namespace App\Services\RecipeAnalysis;

use App\Enums\Allergen;

/**
 * Checks a model response against the JSON contract and the business rules
 * of the README ("Business rules & validation"), then normalises units.
 *
 * Model output is untrusted input: an error rejects the whole response,
 * a warning is shown to the chef and never blocks.
 */
class RecipeAnalysisValidator
{
    public const SUPPORTED_SCHEMA_VERSIONS = [1];

    public const ALLOWED_UNITS = ['g', 'kg', 'ml', 'cl', 'l', 'piece'];

    /**
     * Unit => [normalised unit, factor].
     */
    private const UNIT_CONVERSIONS = [
        'g' => ['g', 1],
        'kg' => ['g', 1000],
        'ml' => ['ml', 1],
        'cl' => ['ml', 10],
        'l' => ['ml', 1000],
        'piece' => ['piece', 1],
    ];

    /**
     * @param  array<mixed>  $data
     *
     * @throws InvalidRecipeAnalysis
     */
    public function validate(array $data): ValidatedRecipe
    {
        $errors = [];
        $warnings = [];

        if (! in_array($data['schema_version'] ?? null, self::SUPPORTED_SCHEMA_VERSIONS, true)) {
            $errors[] = 'schema_version missing or not supported';
        }

        $dish = $data['dish'] ?? null;
        $dishName = is_array($dish) && is_string($dish['name'] ?? null) ? trim($dish['name']) : '';

        if ($dishName === '') {
            $errors[] = 'dish.name missing or not a string';
        }

        $portions = is_array($dish) ? ($dish['portions'] ?? null) : null;

        if ($portions === null) {
            $warnings[] = 'Nombre de portions non précisé : indiquez-le avant de calculer le coût par portion.';
        } elseif (! is_int($portions) || $portions <= 0) {
            $errors[] = 'dish.portions must be a positive integer or null';
        }

        $ingredients = $this->validateIngredients($data['ingredients'] ?? null, $errors);
        $allergens = $this->validateAllergens($data['allergens'] ?? null, $ingredients, $errors, $warnings);

        $unknownKeys = array_diff(array_keys($data), ['schema_version', 'dish', 'ingredients', 'allergens']);

        if ($unknownKeys !== []) {
            $warnings[] = 'Champs inattendus ignorés dans la réponse de l\'IA : '.implode(', ', $unknownKeys).'.';
        }

        if ($errors !== []) {
            throw new InvalidRecipeAnalysis($errors);
        }

        return new ValidatedRecipe(
            dishName: $dishName,
            portions: is_int($portions) ? $portions : null,
            ingredients: $ingredients,
            allergens: $allergens,
            warnings: $warnings,
        );
    }

    /**
     * @param  list<string>  $errors
     * @return list<array{name: string, quantity: float, unit: string, note: ?string}>
     */
    private function validateIngredients(mixed $items, array &$errors): array
    {
        if (! is_array($items) || $items === []) {
            $errors[] = InvalidRecipeAnalysis::NO_INGREDIENTS;

            return [];
        }

        $ingredients = [];

        foreach (array_values($items) as $index => $item) {
            $name = is_array($item) && is_string($item['name'] ?? null) ? trim($item['name']) : '';
            $quantity = is_array($item) ? ($item['quantity'] ?? null) : null;
            $unit = is_array($item) ? ($item['unit'] ?? null) : null;
            $note = is_array($item) && is_string($item['note'] ?? null) && trim($item['note']) !== ''
                ? trim($item['note'])
                : null;

            if ($name === '') {
                $errors[] = "ingredients.{$index}.name missing";
            }

            if ((! is_int($quantity) && ! is_float($quantity)) || $quantity <= 0) {
                $errors[] = "ingredients.{$index}.quantity must be a number > 0";

                continue;
            }

            if (! is_string($unit) || ! isset(self::UNIT_CONVERSIONS[$unit])) {
                $errors[] = "ingredients.{$index}.unit not allowed";

                continue;
            }

            [$normalisedUnit, $factor] = self::UNIT_CONVERSIONS[$unit];

            $ingredients[] = [
                'name' => $name,
                'quantity' => round($quantity * $factor, 3),
                'unit' => $normalisedUnit,
                'note' => $note,
            ];
        }

        return $ingredients;
    }

    /**
     * @param  list<array{name: string, quantity: float, unit: string, note: ?string}>  $ingredients
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     * @return list<array{code: string, label: string, source_ingredients: list<string>}>
     */
    private function validateAllergens(mixed $items, array $ingredients, array &$errors, array &$warnings): array
    {
        if (! is_array($items)) {
            $errors[] = 'allergens missing or not a list';

            return [];
        }

        $ingredientNames = array_map(
            fn (array $ingredient): string => mb_strtolower($ingredient['name']),
            $ingredients,
        );

        $allergens = [];

        foreach (array_values($items) as $index => $item) {
            $code = is_array($item) ? ($item['code'] ?? null) : null;
            $allergen = is_string($code) ? Allergen::tryFrom($code) : null;

            if ($allergen === null) {
                $errors[] = "allergens.{$index}.code not in the closed list of 14";

                continue;
            }

            $sources = is_array($item['source_ingredients'] ?? null)
                ? array_values(array_filter($item['source_ingredients'], 'is_string'))
                : [];

            foreach ($sources as $source) {
                if (! in_array(mb_strtolower(trim($source)), $ingredientNames, true)) {
                    $warnings[] = "Allergène « {$allergen->label()} » : l'ingrédient « {$source} » n'est pas dans la liste des ingrédients.";
                }
            }

            $allergens[$allergen->value] = [
                'code' => $allergen->value,
                'label' => $allergen->label(),
                'source_ingredients' => $sources,
            ];
        }

        return array_values($allergens);
    }
}
