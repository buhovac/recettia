<?php

namespace App\Services\RecipeAnalysis;

use App\Enums\AiCallOutcome;

final readonly class RecipeAnalysisResult
{
    private function __construct(
        public AiCallOutcome $outcome,
        public ?ValidatedRecipe $recipe,
    ) {}

    public static function success(ValidatedRecipe $recipe): self
    {
        return new self(AiCallOutcome::Success, $recipe);
    }

    public static function failure(AiCallOutcome $outcome): self
    {
        return new self($outcome, null);
    }

    public function succeeded(): bool
    {
        return $this->outcome === AiCallOutcome::Success;
    }

    /**
     * Shape sent to the page.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome->value,
            'error' => $this->outcome->userMessage(),
            'recipe' => $this->recipe?->toArray(),
        ];
    }
}
