<?php

namespace App\Services\RecipeAnalysis;

/**
 * A model response that passed validation, with units normalised to g, ml or piece.
 */
final readonly class ValidatedRecipe
{
    /**
     * @param  list<array{name: string, quantity: float, unit: string, note: ?string}>  $ingredients
     * @param  list<array{code: string, label: string, source_ingredients: list<string>}>  $allergens
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $dishName,
        public ?int $portions,
        public array $ingredients,
        public array $allergens,
        public array $warnings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dish_name' => $this->dishName,
            'portions' => $this->portions,
            'ingredients' => $this->ingredients,
            'allergens' => $this->allergens,
            'warnings' => $this->warnings,
        ];
    }
}
