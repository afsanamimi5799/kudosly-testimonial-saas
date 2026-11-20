<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Billing\PlanType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The plan this account is currently on.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_slug', 'slug');
    }

    /**
     * The spaces owned by this account.
     */
    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }

    /**
     * The plan this account is currently on.
     *
     * The foreign key on users.plan_slug guarantees the plan row exists, so a
     * missing record means the database is inconsistent. Fail loudly rather
     * than silently granting the wrong entitlements.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function currentPlan(): Plan
    {
        return $this->plan
            ?? Plan::query()->where('slug', $this->plan_slug)->firstOrFail();
    }

    /**
     * The identity of the plan this account is currently on.
     *
     * @throws \ValueError when the plan slug is not a recognised plan type.
     */
    public function currentPlanType(): PlanType
    {
        return $this->currentPlan()->type();
    }

    /**
     * Determine whether this account is on the given plan.
     */
    public function hasPlan(PlanType $type): bool
    {
        return $this->currentPlanType() === $type;
    }

    /**
     * The maximum number of spaces allowed on the current plan.
     */
    public function maxSpaces(): int
    {
        return $this->currentPlan()->max_spaces;
    }

    /**
     * The maximum number of testimonials allowed per space on the current plan.
     */
    public function maxTestimonialsPerSpace(): int
    {
        return $this->currentPlan()->max_testimonials_per_space;
    }

    /**
     * The number of spaces this account currently owns.
     */
    public function spacesUsed(): int
    {
        return $this->spaces()->count();
    }

    /**
     * The number of additional spaces this account may create.
     */
    public function spaceQuotaRemaining(): int
    {
        return max(0, $this->maxSpaces() - $this->spacesUsed());
    }

    /**
     * Determine whether this account may create another space.
     */
    public function canCreateSpace(): bool
    {
        return $this->spacesUsed() < $this->maxSpaces();
    }

    /**
     * Determine whether this account has any space quota remaining.
     */
    public function hasSpaceQuota(): bool
    {
        return $this->spaceQuotaRemaining() > 0;
    }
}
