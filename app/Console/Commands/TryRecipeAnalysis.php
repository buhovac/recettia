<?php

namespace App\Console\Commands;

use App\Models\AiCallLog;
use App\Services\RecipeAnalysis\RecipeAnalyzer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Makes ONE real model call with a sample recipe, to check the OpenRouter setup.
 * It costs real credit: run it by hand, never from a test or a scheduler.
 */
#[Signature('recipe:try {text? : Recipe text, defaults to the README example}')]
#[Description('Send one recipe to the model (real call, real cost) and print the result and its cost')]
class TryRecipeAnalysis extends Command
{
    private const SAMPLE = "Risotto aux crevettes pour 4 personnes : 320 g de riz arborio, 400 g de crevettes décortiquées, 2 gousses d'ail, 60 g de beurre, 15 cl de vin blanc sec, 80 g de parmesan.";

    public function handle(RecipeAnalyzer $analyzer): int
    {
        $text = $this->argument('text') ?: self::SAMPLE;

        $this->components->info('Real call to '.config('ai.providers.openrouter.models.text.default').'…');

        $result = $analyzer->analyze($text, null);
        $log = AiCallLog::query()->latest('id')->firstOrFail();

        $this->components->twoColumnDetail('Outcome', $result->outcome->value);
        $this->components->twoColumnDetail('Model', $log->model);
        $this->components->twoColumnDetail('Tokens in / out', $log->tokens_in.' / '.$log->tokens_out);
        $this->components->twoColumnDetail('Cost (USD)', $log->cost_usd ?? 'not reported');
        $this->components->twoColumnDetail('Duration', $log->duration_ms.' ms');

        if (! $result->succeeded()) {
            $this->components->error((string) $log->error);

            return self::FAILURE;
        }

        $this->line((string) json_encode($result->recipe?->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
