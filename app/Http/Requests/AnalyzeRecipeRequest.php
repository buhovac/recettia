<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AnalyzeRecipeRequest extends FormRequest
{
    /**
     * Longest recipe sent to the model: caps the cost of one call.
     */
    public const MAX_LENGTH = 5000;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recipe_text' => ['required', 'string', 'min:10', 'max:'.self::MAX_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipe_text.required' => 'Collez le texte de votre recette.',
            'recipe_text.min' => 'La recette est trop courte pour être analysée.',
            'recipe_text.max' => 'La recette dépasse '.self::MAX_LENGTH.' caractères.',
        ];
    }
}
