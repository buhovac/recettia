<?php

namespace App\Enums;

/**
 * The 14 allergens of EU Regulation 1169/2011, Annex II. Closed list (BR02).
 */
enum Allergen: string
{
    case Gluten = 'gluten';
    case Crustaceans = 'crustaceans';
    case Eggs = 'eggs';
    case Fish = 'fish';
    case Peanuts = 'peanuts';
    case Soybeans = 'soybeans';
    case Milk = 'milk';
    case Nuts = 'nuts';
    case Celery = 'celery';
    case Mustard = 'mustard';
    case Sesame = 'sesame';
    case Sulphites = 'sulphites';
    case Lupin = 'lupin';
    case Molluscs = 'molluscs';

    /**
     * French label shown to the chef.
     */
    public function label(): string
    {
        return match ($this) {
            self::Gluten => 'Céréales contenant du gluten',
            self::Crustaceans => 'Crustacés',
            self::Eggs => 'Œufs',
            self::Fish => 'Poissons',
            self::Peanuts => 'Arachides',
            self::Soybeans => 'Soja',
            self::Milk => 'Lait',
            self::Nuts => 'Fruits à coque',
            self::Celery => 'Céleri',
            self::Mustard => 'Moutarde',
            self::Sesame => 'Graines de sésame',
            self::Sulphites => 'Anhydride sulfureux et sulfites',
            self::Lupin => 'Lupin',
            self::Molluscs => 'Mollusques',
        };
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_map(fn (self $allergen): string => $allergen->value, self::cases());
    }
}
