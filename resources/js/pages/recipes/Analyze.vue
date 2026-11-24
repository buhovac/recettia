<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import RecipeAnalysisController from '@/actions/App/Http/Controllers/RecipeAnalysisController';
import AlertError from '@/components/AlertError.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { analyze } from '@/routes/recipes';

type Ingredient = {
    name: string;
    quantity: number;
    unit: 'g' | 'ml' | 'piece';
    note: string | null;
};

type AllergenItem = {
    code: string;
    label: string;
    source_ingredients: string[];
};

type Analysis = {
    outcome: string;
    error: string | null;
    recipe: {
        dish_name: string;
        portions: number | null;
        ingredients: Ingredient[];
        allergens: AllergenItem[];
        warnings: string[];
    } | null;
};

const props = defineProps<{
    analysis: Analysis | null;
    maxLength: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Analyser une recette',
                href: analyze(),
            },
        ],
    },
});

const form = useForm({
    recipe_text: '',
});

const submit = () => {
    form.submit(RecipeAnalysisController.store(), {
        preserveScroll: true,
        preserveState: true,
    });
};

const recipe = computed(() => props.analysis?.recipe ?? null);

const unitLabels: Record<Ingredient['unit'], string> = {
    g: 'g',
    ml: 'ml',
    piece: 'pièce(s)',
};

const formatQuantity = (quantity: number): string =>
    new Intl.NumberFormat('fr-BE', { maximumFractionDigits: 2 }).format(
        quantity,
    );
</script>

<template>
    <Head title="Analyser une recette" />

    <div class="flex flex-col gap-6 p-4 md:max-w-3xl">
        <Heading
            title="Analyser une recette"
            description="Collez une recette en texte libre. L'IA en extrait les ingrédients, les quantités et les allergènes ; vous vérifiez ensuite le résultat."
        />

        <form class="flex flex-col gap-3" @submit.prevent="submit">
            <Label for="recipe_text">Texte de la recette</Label>
            <textarea
                id="recipe_text"
                v-model="form.recipe_text"
                name="recipe_text"
                rows="8"
                :maxlength="maxLength"
                required
                placeholder="Risotto aux crevettes pour 4 personnes : 320 g de riz arborio, 400 g de crevettes décortiquées…"
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
            />
            <div
                class="flex items-center justify-between text-xs text-muted-foreground"
            >
                <InputError :message="form.errors.recipe_text" />
                <span class="ml-auto"
                    >{{ form.recipe_text.length }} / {{ maxLength }}</span
                >
            </div>
            <Button
                type="submit"
                class="w-full sm:w-auto sm:self-start"
                :disabled="form.processing"
            >
                <Spinner v-if="form.processing" />
                {{ form.processing ? 'Analyse en cours…' : 'Analyser' }}
            </Button>
        </form>

        <AlertError
            v-if="analysis?.error"
            :errors="[analysis.error]"
            title="L'analyse n'a pas abouti."
        />

        <section v-if="recipe" class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-lg font-semibold">{{ recipe.dish_name }}</h3>
                <Badge variant="secondary">Généré par IA</Badge>
            </div>
            <p class="text-sm text-muted-foreground">
                {{
                    recipe.portions
                        ? `${recipe.portions} portion(s)`
                        : 'Nombre de portions non précisé'
                }}
                · à vérifier avant toute utilisation.
            </p>

            <ul
                v-if="recipe.warnings.length"
                class="list-inside list-disc rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
            >
                <li v-for="(warning, index) in recipe.warnings" :key="index">
                    {{ warning }}
                </li>
            </ul>

            <div>
                <h4 class="mb-2 font-medium">Ingrédients</h4>
                <ul class="divide-y rounded-md border">
                    <li
                        v-for="(ingredient, index) in recipe.ingredients"
                        :key="index"
                        class="flex flex-wrap justify-between gap-x-4 px-3 py-2 text-sm"
                    >
                        <span>
                            {{ ingredient.name }}
                            <span
                                v-if="ingredient.note"
                                class="text-muted-foreground"
                                >({{ ingredient.note }})</span
                            >
                        </span>
                        <span class="tabular-nums">
                            {{ formatQuantity(ingredient.quantity) }}
                            {{ unitLabels[ingredient.unit] }}
                        </span>
                    </li>
                </ul>
            </div>

            <div>
                <h4 class="mb-2 font-medium">Allergènes détectés</h4>
                <p
                    v-if="!recipe.allergens.length"
                    class="text-sm text-muted-foreground"
                >
                    Aucun des 14 allergènes réglementaires n'a été détecté.
                </p>
                <ul v-else class="flex flex-col gap-2">
                    <li
                        v-for="allergen in recipe.allergens"
                        :key="allergen.code"
                        class="text-sm"
                    >
                        <Badge variant="outline">{{ allergen.label }}</Badge>
                        <span
                            v-if="allergen.source_ingredients.length"
                            class="ml-2 text-muted-foreground"
                        >
                            {{ allergen.source_ingredients.join(', ') }}
                        </span>
                    </li>
                </ul>
            </div>
        </section>
    </div>
</template>
