<?php

namespace App\Enums;

/**
 * How a model call ended. Matches the failure cases of the README (O5).
 */
enum AiCallOutcome: string
{
    case Success = 'success';
    case Timeout = 'timeout';
    case ProviderDown = 'provider_down';
    case CreditExhausted = 'credit_exhausted';
    case InvalidResponse = 'invalid_response';
    // Well-formed answer, but the text held no ingredient (e.g. only a dish name).
    case NoIngredients = 'no_ingredients';

    /**
     * French message shown to the chef. Null on success.
     */
    public function userMessage(): ?string
    {
        return match ($this) {
            self::Success => null,
            self::Timeout => "L'analyse a pris trop de temps. Votre recette est conservée : réessayez dans un instant.",
            self::ProviderDown, self::CreditExhausted => "Le service d'IA est indisponible pour le moment. Votre recette est conservée : réessayez plus tard.",
            self::InvalidResponse => "La réponse de l'IA n'a pas pu être utilisée. Votre recette est conservée : réessayez.",
            self::NoIngredients => 'Aucun ingrédient trouvé dans ce texte. Collez la recette complète, avec ses ingrédients et leurs quantités.',
        };
    }
}
