<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Billing\PlanCatalog;
use App\Billing\PlanType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Cashier\Billable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'stripe_id', 'billing_email'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasFactory, Notifiable;

    /**
     * The directory on the public disk that holds the profile pictures of
     * the account holders.
     *
     * The pictures of the people who send in testimonials live in their own
     * directory. Keeping the two apart means that removing one set can
     * never affect the other.
     */
    public const PROFILE_PHOTO_DIRECTORY = 'user-profile-photos';

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
     * @throws ModelNotFoundException
     */
    public function currentPlan(): Plan
    {
        return $this->plan
            ?? Plan::query()->where('slug', $this->plan_slug)->firstOrFail();
    }

    /**
     * The identity of the plan this account is currently on.
     *
     * This reflects the plan the account is entitled to, so a Pro plan whose
     * subscription has lapsed reads as Free.
     *
     * @throws \ValueError when the plan slug is not a recognised plan type.
     */
    public function currentPlanType(): PlanType
    {
        return $this->effectivePlanType();
    }

    /**
     * Determine whether this account is on the given plan.
     *
     * This reflects the plan the account is entitled to, so a Pro plan whose
     * subscription has lapsed reads as Free.
     */
    public function hasPlan(PlanType $type): bool
    {
        return $this->effectivePlanType() === $type;
    }

    /**
     * The maximum number of spaces allowed on the current plan.
     *
     * The limit follows the plan the account is entitled to, which is the
     * stored plan while a Pro subscription is valid and the Free plan once
     * that subscription has lapsed.
     */
    public function maxSpaces(): int
    {
        return $this->effectivePlan()->max_spaces;
    }

    /**
     * The maximum number of testimonials allowed per space on the current plan.
     *
     * The limit follows the plan the account is entitled to, which is the
     * stored plan while a Pro subscription is valid and the Free plan once
     * that subscription has lapsed.
     */
    public function maxTestimonialsPerSpace(): int
    {
        return $this->effectivePlan()->max_testimonials_per_space;
    }

    /**
     * The plan the account is entitled to right now.
     *
     * The stored plan is the entitlement. The Stripe webhook writes it when
     * a subscription starts and when it ends, so this stays a cheap read
     * with no call to Stripe.
     */
    public function effectivePlan(): Plan
    {
        return app(PlanCatalog::class)->effectivePlanFor($this);
    }

    /**
     * The identity of the plan the account is entitled to right now.
     */
    public function effectivePlanType(): PlanType
    {
        return $this->effectivePlan()->type();
    }

    /**
     * Determine whether the account holds a valid Pro subscription.
     *
     * This reads the subscription record, so it tells the difference between
     * an account that is paying for Pro and an account that was granted Pro
     * without a subscription behind it.
     */
    public function hasActiveSubscription(): bool
    {
        return app(PlanCatalog::class)->hasValidSubscription($this);
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

    /**
     * The address of the profile picture, when the account has one.
     *
     * The picture is stored on the public disk, so it is served through the
     * address of that disk. An account without a picture has no address,
     * and the caller shows the initial of the name instead.
     */
    public function profilePhotoUrl(): ?string
    {
        $path = $this->profile_photo_path;

        if (! is_string($path) || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * The letter that stands in for the picture when there is none.
     */
    public function profileInitial(): string
    {
        return strtoupper(mb_substr($this->name ?? 'U', 0, 1));
    }
}
