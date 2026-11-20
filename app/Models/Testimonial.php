<?php

namespace App\Models;

use App\Enums\TestimonialStatus;
use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'customer_name',
    'customer_email',
    'company_name',
    'social_url',
    'profile_photo_path',
    'testimonial_text',
    'rating',
    'sharing_consent',
    'status',
    'is_favorite',
    'is_wall_of_love',
])]
class Testimonial extends Model
{
    /** @use HasFactory<TestimonialFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'sharing_consent' => 'boolean',
            'is_favorite' => 'boolean',
            'is_wall_of_love' => 'boolean',
            'status' => TestimonialStatus::class,
        ];
    }

    /**
     * The space this testimonial was submitted to.
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * Only testimonials that have been approved.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', TestimonialStatus::Approved);
    }

    /**
     * Only testimonials that are eligible for public display.
     *
     * A testimonial must be approved and have explicit sharing consent.
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('status', TestimonialStatus::Approved)
            ->where('sharing_consent', true);
    }

    /**
     * Only testimonials marked as a favourite.
     */
    public function scopeFavorited(Builder $query): Builder
    {
        return $query->where('is_favorite', true);
    }

    /**
     * Only testimonials marked for the wall of love.
     */
    public function scopeWallOfLove(Builder $query): Builder
    {
        return $query->where('is_wall_of_love', true);
    }

    /**
     * Order the testimonials for the inbox.
     *
     * Favourites are pinned above the rest, and everything else is ordered
     * newest first. This ordering only ever applies to the owner's own
     * inbox. It never widens what a public page may show.
     */
    public function scopeOrderedForInbox(Builder $query): Builder
    {
        return $query->orderByDesc('is_favorite')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Determine whether this testimonial may be shown on a public page.
     *
     * A testimonial is publicly eligible when it has been approved and the
     * customer gave explicit permission for it to be displayed. A favourite
     * or a wall of love flag never makes a testimonial public on its own.
     */
    public function isPubliclyVisible(): bool
    {
        return $this->status === TestimonialStatus::Approved
            && $this->sharing_consent === true;
    }

    /**
     * The public URL of the wall of love for the testimonial's space.
     *
     * A testimonial always belongs to a space, but the relation may not be
     * loaded yet. The space is loaded on demand so that the URL can never
     * fail because of an unloaded relation.
     */
    public function wallUrl(): string
    {
        return $this->space->wallUrl();
    }
}
