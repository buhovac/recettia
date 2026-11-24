<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyzeRecipeRequest;
use App\Services\RecipeAnalysis\RecipeAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecipeAnalysisController extends Controller
{
    /**
     * Show the recipe analysis page, with the last result if any.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('recipes/Analyze', [
            'analysis' => $request->session()->get('analysis'),
            'maxLength' => AnalyzeRecipeRequest::MAX_LENGTH,
        ]);
    }

    /**
     * Send the recipe to the model and come back to the page with the result.
     */
    public function store(AnalyzeRecipeRequest $request, RecipeAnalyzer $analyzer): RedirectResponse
    {
        $result = $analyzer->analyze($request->string('recipe_text')->toString(), $request->user());

        return to_route('recipes.analyze')->with('analysis', $result->toArray());
    }
}
