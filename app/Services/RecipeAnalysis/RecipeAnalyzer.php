<?php

namespace App\Services\RecipeAnalysis;

use App\Ai\Agents\RecipeAnalysisAgent;
use App\Enums\AiCallOutcome;
use App\Models\AiCallLog;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Responses\AgentResponse;
use Throwable;

/**
 * Sends a recipe to the model, validates the answer and logs the call.
 *
 * Every call writes one ai_call_logs row, whatever the outcome. The recipe
 * text itself is never logged.
 */
class RecipeAnalyzer
{
    public const FEATURE = 'recipe_analysis';

    public function __construct(private RecipeAnalysisValidator $validator) {}

    public function analyze(string $recipeText, ?User $user): RecipeAnalysisResult
    {
        $startedAt = hrtime(true);
        $response = null;

        try {
            $response = RecipeAnalysisAgent::make()->prompt($recipeText);

            $recipe = $this->validator->validate($response->toArray());

            $this->log($user, $startedAt, AiCallOutcome::Success, $response);

            return RecipeAnalysisResult::success($recipe);
        } catch (Throwable $exception) {
            $outcome = $this->outcomeFor($exception);

            if ($outcome === AiCallOutcome::ProviderDown) {
                report($exception);
            }

            $this->log($user, $startedAt, $outcome, $response, $exception);

            return RecipeAnalysisResult::failure($outcome);
        }
    }

    private function outcomeFor(Throwable $exception): AiCallOutcome
    {
        return match (true) {
            $exception instanceof InvalidRecipeAnalysis && $exception->onlyLacksIngredients() => AiCallOutcome::NoIngredients,
            $exception instanceof InvalidRecipeAnalysis => AiCallOutcome::InvalidResponse,
            $exception instanceof InsufficientCreditsException => AiCallOutcome::CreditExhausted,
            $exception instanceof ProviderConnectionException && $this->isTimeout($exception) => AiCallOutcome::Timeout,
            default => AiCallOutcome::ProviderDown,
        };
    }

    private function isTimeout(Throwable $exception): bool
    {
        $messages = $exception->getMessage().' '.$exception->getPrevious()?->getMessage();

        return Str::contains($messages, ['timed out', 'cURL error 28'], ignoreCase: true);
    }

    private function log(
        ?User $user,
        int $startedAt,
        AiCallOutcome $outcome,
        ?AgentResponse $response,
        ?Throwable $exception = null,
    ): void {
        $cost = $response?->raw?->json('usage.cost');

        AiCallLog::create([
            'user_id' => $user?->id,
            'feature' => self::FEATURE,
            'provider' => $response->meta->provider ?? (string) config('ai.default'),
            'model' => $response->meta->model ?? (string) config('ai.providers.openrouter.models.text.default'),
            'tokens_in' => $response->usage->inputTokens ?? 0,
            'tokens_out' => $response->usage->outputTokens ?? 0,
            'cost_usd' => is_numeric($cost) ? (string) $cost : null,
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'outcome' => $outcome,
            'error' => $exception === null
                ? null
                : Str::limit(class_basename($exception).': '.$exception->getMessage(), 490),
        ]);
    }
}
