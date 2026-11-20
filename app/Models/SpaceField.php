<?php

namespace App\Models;

use Database\Factories\SpaceFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['field_key', 'enabled', 'required', 'sort_order'])]
class SpaceField extends Model
{
    /** @use HasFactory<SpaceFieldFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The space this field belongs to.
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }
}
