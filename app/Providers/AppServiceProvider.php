<?php

namespace App\Providers;

use App\Billing\Contracts\StripeGateway;
use App\Billing\StripeGateway as StripeGatewayImplementation;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
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
