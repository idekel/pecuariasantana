<?php

namespace App\Models;

use Database\Factories\YieldRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('yields')]
#[Fillable(['user_id', 'quantity', 'produced_on'])]
class YieldRecord extends Model
{
    /** @use HasFactory<YieldRecordFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'produced_on' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The unit this yield is measured in, derived from its project's type.
     */
    public function unit(): string
    {
        return $this->project->type->yieldUnit();
    }
}
