<?php

namespace App\Models;

use App\Enums\AiCallOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $feature
 * @property string $provider
 * @property string $model
 * @property int $tokens_in
 * @property int $tokens_out
 * @property string|null $cost_usd
 * @property int $duration_ms
 * @property AiCallOutcome $outcome
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'feature', 'provider', 'model', 'tokens_in', 'tokens_out', 'cost_usd', 'duration_ms', 'outcome', 'error'])]
class AiCallLog extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'cost_usd' => 'decimal:8',
            'duration_ms' => 'integer',
            'outcome' => AiCallOutcome::class,
        ];
    }
}
