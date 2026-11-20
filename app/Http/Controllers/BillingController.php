<?php

namespace App\Http\Controllers;

use App\Billing\PlanCatalog;
use App\Billing\PlanType;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    /**
     * Show the available plans and the account's current usage.
     *
     * No payment processing is implemented. The page reports the plan the
     * account is on and the limits that plan grants, and it does not offer
     * any way to change the plan.
     */
    public function __invoke(Request $request, PlanCatalog $catalog): View
    {
        $user = $request->user();

        $user->loadMissing('plan');

        $currentPlan = $user->currentPlan();
        $spacesUsed = $user->spacesUsed();

        return view('billing', [
            'user' => $user,
            'plans' => $catalog->all(),
            'currentPlan' => $currentPlan,
            'isFreePlan' => $user->hasPlan(PlanType::Free),
            'spacesUsed' => $spacesUsed,
            'maxSpaces' => $user->maxSpaces(),
            'spacesRemaining' => $user->spaceQuotaRemaining(),
            'maxTestimonialsPerSpace' => $user->maxTestimonialsPerSpace(),
            'spaceUsagePercentage' => $user->maxSpaces() > 0
                ? (int) min(100, round(($spacesUsed / $user->maxSpaces()) * 100))
                : 0,
        ]);
    }
}
