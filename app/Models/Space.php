<?php

namespace App\Models;

use App\Enums\SpaceFieldKey;
use App\Enums\SpaceTheme;
use Database\Factories\SpaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['name', 'slug', 'title', 'subtitle', 'ask', 'theme', 'rating_enabled'])]
class Space extends Model
{
    /** @use HasFactory<SpaceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating_enabled' => 'boolean',
        ];
    }

    /**
     * The account that owns this space.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The configurable collection fields for this space.
     */
    public function fields(): HasMany
    {
        return $this->hasMany(SpaceField::class);
    }

    /**
     * The testimonials submitted to this space.
     */
    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    /**
     * The embeddable widgets created for this space.
     */
    public function widgets(): HasMany
    {
        return $this->hasMany(Widget::class);
    }

    /**
     * The number of testimonials submitted to this space.
     */
    public function testimonialCount(): int
    {
        return $this->testimonials()->count();
    }

    /**
     * The theme of this space.
     *
     * The stored slug is the source of truth. An unrecognised slug means the
     * database is inconsistent, so fail loudly rather than silently falling
     * back to a different theme.
     *
     * @throws \ValueError when the stored theme is not a recognised theme.
     */
    public function themeEnum(): SpaceTheme
    {
        return SpaceTheme::from($this->theme);
    }

    /**
     * The public URL of this space's collection page.
     */
    public function publicUrl(): string
    {
        return route('spaces.public.show', ['space' => $this->slug]);
    }

    /**
     * The public URL of this space's wall of love.
     */
    public function wallUrl(): string
    {
        return route('wall.public.show', ['space' => $this->slug]);
    }

    /**
     * The fields that are shown on this space's collection form.
     *
     * Only enabled fields are collected, and they are returned in the order
     * they should appear on the form.
     *
     * @return Collection<int, SpaceField>
     */
    public function enabledFields(): Collection
    {
        return $this->fields()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Determine whether the given field is collected on this space.
     */
    public function hasFieldEnabled(SpaceFieldKey $key): bool
    {
        return $this->fields()
            ->where('field_key', $key->value)
            ->where('enabled', true)
            ->exists();
    }

    /**
     * Determine whether the given field must be filled in.
     *
     * A field is only required when it is both enabled and marked required. A
     * disabled field is never shown, so it can never be required.
     */
    public function hasFieldRequired(SpaceFieldKey $key): bool
    {
        return $this->fields()
            ->where('field_key', $key->value)
            ->where('enabled', true)
            ->where('required', true)
            ->exists();
    }

    /**
     * The maximum number of testimonials this space may collect.
     *
     * The limit comes from the owning account's plan.
     */
    public function testimonialLimit(): int
    {
        return $this->user->maxTestimonialsPerSpace();
    }

    /**
     * Determine whether this space has collected as many testimonials as its
     * plan allows.
     */
    public function hasReachedTestimonialLimit(): bool
    {
        return $this->testimonialCount() >= $this->testimonialLimit();
    }
}
