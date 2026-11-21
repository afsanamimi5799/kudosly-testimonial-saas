<?php

namespace App\Providers;

use App\Billing\Contracts\StripeGateway;
use App\Billing\StripeGateway as StripeGatewayImplementation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The number of minutes a visitor is locked out for after too many
     * attempts from the same address.
     */
    private const PUBLIC_SUBMISSION_MINUTES = 10;

    /**
     * The number of attempts allowed from one address in that window.
     */
    private const PUBLIC_SUBMISSION_ATTEMPTS = 5;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Cashier registers its own webhook route under the same path as the
        // one in this application. The route in routes/web.php is the one
        // that is used, so Cashier's is switched off to keep a single route
        // that answers on the Stripe webhook path.
        Cashier::ignoreRoutes();

        // The billing services talk to Stripe through the contract, so the
        // binding can be swapped for a fake while the billing features are
        // exercised without reaching Stripe.
        $this->app->bind(StripeGateway::class, StripeGatewayImplementation::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->shareNavigationContext();
        $this->limitPublicSubmissions();
    }

    /**
     * Limit how often a visitor may submit a testimonial.
     *
     * The collection form is public, so it is the one place an outsider can
     * write to the application. The limit is counted against the address the
     * request came from and nothing else: the browser the visitor happens to
     * use is not part of the key, so changing it cannot buy a visitor more
     * attempts.
     *
     * Every attempt is counted, including one that fails validation, because
     * the throttle middleware counts the request before the form is checked.
     */
    private function limitPublicSubmissions(): void
    {
        RateLimiter::for('public-collection', function (Request $request) {
            return Limit::perMinutes(self::PUBLIC_SUBMISSION_MINUTES, self::PUBLIC_SUBMISSION_ATTEMPTS)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response(
                        'Too many submissions from your connection. Please try again later.',
                        Response::HTTP_TOO_MANY_REQUESTS,
                        $headers
                    );
                });
        });
    }

    /**
     * Share the active navigation key and page title with the app layout.
     *
     * The sidebar highlights the link whose key matches the current route,
     * and the topbar shows the page title. Each route name maps to the
     * navigation key the sidebar compares against, so highlighting stays
     * correct without each view repeating itself.
     */
    private function shareNavigationContext(): void
    {
        View::composer('layouts.app', function ($view) {
            $routeName = request()->route()?->getName();

            $navigation = [
                'dashboard' => ['key' => 'dashboard', 'title' => 'Dashboard'],
                'spaces.index' => ['key' => 'spaces', 'title' => 'Spaces'],
                'inbox.index' => ['key' => 'inbox', 'title' => 'Inbox'],
                'testimonials.edit' => ['key' => 'inbox', 'title' => 'Edit testimonial'],
                'wall-of-love.index' => ['key' => 'wall-of-love', 'title' => 'Wall of Love'],
                'widgets.index' => ['key' => 'widgets', 'title' => 'Widgets'],
                'widgets.create' => ['key' => 'widgets', 'title' => 'Create widget'],
                'widgets.edit' => ['key' => 'widgets', 'title' => 'Edit widget'],
                'widgets.preview' => ['key' => 'widgets', 'title' => 'Widget preview'],
                'billing.index' => ['key' => 'billing', 'title' => 'Billing'],
                'profile.edit' => ['key' => 'profile', 'title' => 'Settings'],
            ];

            $active = $navigation[$routeName] ?? ['key' => 'dashboard', 'title' => 'Dashboard'];

            $view->with([
                'current' => $active['key'],
                'title' => $active['title'],
            ]);
        });
    }
}
