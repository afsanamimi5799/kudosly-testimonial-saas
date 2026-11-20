<?php

namespace App\Http\Controllers;

use App\Billing\CheckoutService;
use App\Billing\Contracts\StripeGateway;
use App\Billing\PlanCatalog;
use App\Billing\PlanType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class BillingController extends Controller
{
    /**
     * Show the available plans and the account's current usage.
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
            'proPlan' => $catalog->find(PlanType::Pro),
            'stripeConfigured' => app(StripeGateway::class)->isConfigured(),
            'canUpgrade' => $user->hasPlan(PlanType::Free)
                && $catalog->hasStripePriceId(PlanType::Pro),
            'hasSubscription' => $user->hasActiveSubscription(),
        ]);
    }

    /**
     * Start the yearly Pro subscription checkout.
     *
     * The account is sent to Stripe to pay. The Pro plan is only granted
     * once the webhook confirms the payment, so returning here never grants
     * anything by itself.
     */
    public function checkout(Request $request, CheckoutService $checkout): RedirectResponse
    {
        try {
            return $checkout->redirectToStripe($request->user());
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('billing.index')
                ->with('error', $exception->getMessage());
        }
    }

    /**
     * The page Stripe returns to once the payment has been made.
     *
     * The subscription is confirmed by the webhook, which may not have run
     * yet, so the page tells the account to wait rather than promising that
     * the upgrade is already in place.
     */
    public function checkoutSuccess(Request $request): View
    {
        return view('billing.checkout-success', [
            'user' => $request->user(),
        ]);
    }

    /**
     * The page Stripe returns to when the payment was abandoned.
     */
    public function checkoutCancelled(Request $request): View
    {
        return view('billing.checkout-cancelled', [
            'user' => $request->user(),
        ]);
    }
}
