<?php

namespace App\Billing;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Read-side access to the plans table.
 *
 * Plan limits live in the database as the single source of truth. A missing
 * plan row is a misconfiguration that an operator must fix, so this service
 * raises rather than silently falling back to hardcoded limits.
 */
final class PlanCatalog
{
    /**
     * All available plans, ordered by price.
     *
     * @return Collection<int, Plan>
     */
    public function all(): Collection
    {
        return Plan::query()->orderBy('price')->get();
    }

    /**
     * Find the plan matching the given type.
     */
    public function find(PlanType $type): ?Plan
    {
        return Plan::query()->where('slug', $type->value)->first();
    }

    /**
     * The maximum number of spaces allowed for the given plan.
     *
     * @throws ModelNotFoundException when the plan row is missing.
     */
    public function maxSpaces(PlanType $type): int
    {
        return $this->findOrFail($type)->max_spaces;
    }

    /**
     * The maximum number of testimonials allowed per space for the given plan.
     *
     * @throws ModelNotFoundException when the plan row is missing.
     */
    public function maxTestimonialsPerSpace(PlanType $type): int
    {
        return $this->findOrFail($type)->max_testimonials_per_space;
    }

    /**
     * Resolve a plan or fail loudly.
     *
     * @throws ModelNotFoundException when the plan row is missing.
     */
    private function findOrFail(PlanType $type): Plan
    {
        return $this->find($type) ?? throw (new ModelNotFoundException)->setModel(Plan::class, [$type->value]);
    }
}
