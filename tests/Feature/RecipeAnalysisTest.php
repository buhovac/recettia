<?php

use App\Ai\Agents\RecipeAnalysisAgent;
use App\Enums\AiCallOutcome;
use App\Models\AiCallLog;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\RateLimitedException;

const RECIPE_TEXT = 'Risotto aux crevettes pour 4 personnes : 320 g de riz arborio, 400 g de crevettes décortiquées, 60 g de beurre, 15 cl de vin blanc sec.';

function fakeModelAnswer(array $overrides = []): array
{
    return array_replace([
        'schema_version' => 1,
        'dish' => ['name' => 'Risotto aux crevettes', 'portions' => 4],
        'ingredients' => [
            ['name' => 'riz arborio', 'quantity' => 320, 'unit' => 'g', 'note' => null],
            ['name' => 'crevettes décortiquées', 'quantity' => 400, 'unit' => 'g', 'note' => null],
            ['name' => 'beurre', 'quantity' => 60, 'unit' => 'g', 'note' => null],
            ['name' => 'vin blanc sec', 'quantity' => 15, 'unit' => 'cl', 'note' => null],
        ],
        'allergens' => [
            ['code' => 'crustaceans', 'source_ingredients' => ['crevettes décortiquées']],
            ['code' => 'milk', 'source_ingredients' => ['beurre']],
            ['code' => 'sulphites', 'source_ingredients' => ['vin blanc sec']],
        ],
    ], $overrides);
}

test('guests cannot reach the analysis page', function () {
    $this->get(route('recipes.analyze'))->assertRedirect(route('login'));
    $this->post(route('recipes.analyze.store'), ['recipe_text' => RECIPE_TEXT])->assertRedirect(route('login'));
});

test('a logged-in user sees the analysis page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('recipes.analyze'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('recipes/Analyze')->where('analysis', null));
});

test('a valid model answer is shown, normalised, and logged', function () {
    RecipeAnalysisAgent::fake([fakeModelAnswer()])->preventStrayPrompts();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->followingRedirects()
        ->post(route('recipes.analyze.store'), ['recipe_text' => RECIPE_TEXT])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('recipes/Analyze')
            ->where('analysis.outcome', 'success')
            ->where('analysis.error', null)
            ->where('analysis.recipe.dish_name', 'Risotto aux crevettes')
            ->where('analysis.recipe.ingredients.3.quantity', 150)
            ->where('analysis.recipe.ingredients.3.unit', 'ml')
            ->where('analysis.recipe.allergens.0.label', 'Crustacés'));

    RecipeAnalysisAgent::assertPrompted(RECIPE_TEXT);

    $log = AiCallLog::sole();
    expect($log->user_id)->toBe($user->id)
        ->and($log->outcome)->toBe(AiCallOutcome::Success)
        ->and($log->feature)->toBe('recipe_analysis')
        ->and($log->error)->toBeNull();
});

test('a model answer that breaks the contract is rejected and logged', function () {
    RecipeAnalysisAgent::fake([fakeModelAnswer(['allergens' => [['code' => 'tomato', 'source_ingredients' => []]]])])
        ->preventStrayPrompts();

    $this->actingAs(User::factory()->create())
        ->followingRedirects()
        ->post(route('recipes.analyze.store'), ['recipe_text' => RECIPE_TEXT])
        ->assertInertia(fn ($page) => $page
            ->where('analysis.outcome', 'invalid_response')
            ->where('analysis.recipe', null)
            ->where('analysis.error', fn (string $message) => str_contains($message, 'conservée')));

    $log = AiCallLog::sole();
    expect($log->outcome)->toBe(AiCallOutcome::InvalidResponse)
        ->and($log->error)->toContain('closed list');
});

test('provider failures give a clear message and are logged', function (Throwable $exception, AiCallOutcome $expected) {
    RecipeAnalysisAgent::fake(fn () => throw $exception)->preventStrayPrompts();

    $this->actingAs(User::factory()->create())
        ->followingRedirects()
        ->post(route('recipes.analyze.store'), ['recipe_text' => RECIPE_TEXT])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('analysis.outcome', $expected->value)
            ->where('analysis.error', $expected->userMessage()));

    $log = AiCallLog::sole();
    expect($log->outcome)->toBe($expected)
        ->and($log->error)->toContain(class_basename($exception));
})->with([
    // Pest calls each closure once, so the test receives the exception itself.
    'timeout' => [
        fn () => ProviderConnectionException::forProvider('openrouter', 28, new ConnectionException('cURL error 28: Operation timed out')),
        AiCallOutcome::Timeout,
    ],
    'connection refused' => [
        fn () => ProviderConnectionException::forProvider('openrouter', 7, new ConnectionException('cURL error 7: Failed to connect')),
        AiCallOutcome::ProviderDown,
    ],
    'credit exhausted' => [
        fn () => InsufficientCreditsException::forProvider('openrouter', 402),
        AiCallOutcome::CreditExhausted,
    ],
    'rate limited' => [
        fn () => RateLimitedException::forProvider('openrouter', 429),
        AiCallOutcome::ProviderDown,
    ],
]);

test('the recipe text is validated before any model call', function (string $text) {
    RecipeAnalysisAgent::fake()->preventStrayPrompts();

    $this->actingAs(User::factory()->create())
        ->post(route('recipes.analyze.store'), ['recipe_text' => $text])
        ->assertSessionHasErrors('recipe_text');

    RecipeAnalysisAgent::assertNeverPrompted();
    expect(AiCallLog::count())->toBe(0);
})->with([
    'empty' => [''],
    'too short' => ['riz'],
    'too long' => [str_repeat('a', 5001)],
]);

test('the recipe:try command prints the result and logs the call', function () {
    RecipeAnalysisAgent::fake([fakeModelAnswer()])->preventStrayPrompts();

    $this->artisan('recipe:try')
        ->expectsOutputToContain('success')
        ->expectsOutputToContain('Risotto aux crevettes')
        ->assertSuccessful();

    expect(AiCallLog::sole()->user_id)->toBeNull();
});

test('a text without ingredients gets a specific message, not a failure message', function () {
    RecipeAnalysisAgent::fake([fakeModelAnswer(['ingredients' => [], 'allergens' => []])])->preventStrayPrompts();

    $this->actingAs(User::factory()->create())
        ->followingRedirects()
        ->post(route('recipes.analyze.store'), ['recipe_text' => 'Gnocchis façon steak philly'])
        ->assertInertia(fn ($page) => $page
            ->where('analysis.outcome', 'no_ingredients')
            ->where('analysis.error', AiCallOutcome::NoIngredients->userMessage()));

    expect(AiCallLog::sole()->outcome)->toBe(AiCallOutcome::NoIngredients);
});

test('an empty or truncated answer stays an unusable response', function () {
    RecipeAnalysisAgent::fake([[]])->preventStrayPrompts();

    $this->actingAs(User::factory()->create())
        ->followingRedirects()
        ->post(route('recipes.analyze.store'), ['recipe_text' => RECIPE_TEXT])
        ->assertInertia(fn ($page) => $page->where('analysis.outcome', 'invalid_response'));

    expect(AiCallLog::sole()->outcome)->toBe(AiCallOutcome::InvalidResponse);
});
