<?php

namespace App\Services\RecipeAnalysis;

use RuntimeException;

/**
 * The model response broke the JSON contract or a blocking business rule.
 */
class InvalidRecipeAnalysis extends RuntimeException
{
    public const NO_INGREDIENTS = 'ingredients missing or empty';

    /**
     * @param  list<string>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Invalid recipe analysis: '.implode('; ', $errors));
    }

    /**
     * True when the answer was well formed but found no ingredient in the text,
     * as opposed to a broken or truncated answer.
     */
    public function onlyLacksIngredients(): bool
    {
        return $this->errors === [self::NO_INGREDIENTS];
    }
}
