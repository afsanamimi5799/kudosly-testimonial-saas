<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicCollection\SpaceCollectionController;
use App\Http\Controllers\PublicWall\WallController;
use App\Http\Controllers\SpacesController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TestimonialModerationController;
use App\Http\Controllers\WallOfLoveController;
use App\Http\Controllers\WidgetEmbedController;
use App\Http\Controllers\WidgetsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Stripe webhook
|--------------------------------------------------------------------------
|
| Stripe posts to this endpoint from outside the application, so it is
| reachable without a session and without a CSRF token. The request is
| trusted only when the signature of the payload matches the webhook
| secret, which the controller checks before it reads anything.
|
*/

Route::post('stripe/webhook', StripeWebhookController::class)
    ->name('stripe.webhook');

/*
|--------------------------------------------------------------------------
| Public collection routes
|--------------------------------------------------------------------------
|
| The collection page of a space is reachable without an authenticated
| session. A visitor submits a testimonial here and the testimonial is
| stored as pending for the owner to moderate.
|
*/

Route::get('s/{space:slug}', [SpaceCollectionController::class, 'show'])
    ->name('spaces.public.show');

Route::post('s/{space:slug}', [SpaceCollectionController::class, 'store'])
    ->name('spaces.public.store');

Route::get('s/{space:slug}/thanks', [SpaceCollectionController::class, 'thanks'])
    ->name('spaces.public.thanks');

/*
|--------------------------------------------------------------------------
| Public wall of love routes
|--------------------------------------------------------------------------
|
| The wall of love of a space is reachable without an authenticated
| session. It shows only the testimonials that the owner has marked with a
| heart and that are eligible for public display.
|
*/

Route::get('wall/{space:slug}', [WallController::class, 'show'])
    ->name('wall.public.show');

/*
|--------------------------------------------------------------------------
| Public widget embed routes
|--------------------------------------------------------------------------
|
| A widget is embedded on an external website, so these routes are
| reachable without an authenticated session. A widget is addressed by its
| unguessable public id and may only ever show the testimonials that are
| approved and that the customer gave explicit permission to display.
|
*/

Route::get('embed/{widget:public_id}', [WidgetEmbedController::class, 'show'])
    ->name('widgets.embed.show');

Route::get('widgets/embed-data/{widget:public_id}', [WidgetEmbedController::class, 'data'])
    ->name('widgets.embed.data');

Route::get('widgets/embed.js', [WidgetEmbedController::class, 'script'])
    ->name('widgets.embed.script');

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
|
| These routes are reachable without an authenticated session. Visiting
| any of them while signed in redirects back to the dashboard.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
|
| These routes require an authenticated session. The dashboard route name
| is required by Laravel's default guest redirect behaviour.
|
*/

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->name('dashboard');

    Route::get('spaces', [SpacesController::class, 'index'])
        ->name('spaces.index');

    Route::get('spaces/create', [SpacesController::class, 'create'])
        ->name('spaces.create');

    Route::post('spaces', [SpacesController::class, 'store'])
        ->name('spaces.store');

    Route::get('spaces/created/{space}', [SpacesController::class, 'created'])
        ->name('spaces.created');

    Route::get('spaces/{space}/edit', [SpacesController::class, 'edit'])
        ->name('spaces.edit');

    Route::put('spaces/{space}', [SpacesController::class, 'update'])
        ->name('spaces.update');

    Route::delete('spaces/{space}', [SpacesController::class, 'destroy'])
        ->name('spaces.destroy');

    Route::get('spaces/{space}/fields', [SpacesController::class, 'fields'])
        ->name('spaces.fields');

    Route::put('spaces/{space}/fields', [SpacesController::class, 'updateFields'])
        ->name('spaces.fields.update');

    Route::get('inbox', [InboxController::class, 'index'])
        ->name('inbox.index');

    Route::get('inbox/{testimonial}/edit', [TestimonialModerationController::class, 'edit'])
        ->name('testimonials.edit');

    Route::put('inbox/{testimonial}', [TestimonialModerationController::class, 'update'])
        ->name('testimonials.update');

    Route::post('inbox/{testimonial}/approve', [TestimonialModerationController::class, 'approve'])
        ->name('testimonials.approve');

    Route::post('inbox/{testimonial}/hide', [TestimonialModerationController::class, 'hide'])
        ->name('testimonials.hide');

    Route::post('inbox/{testimonial}/favorite', [TestimonialModerationController::class, 'toggleFavorite'])
        ->name('testimonials.favorite');

    Route::post('inbox/{testimonial}/wall-of-love', [TestimonialModerationController::class, 'toggleWallOfLove'])
        ->name('testimonials.wall-of-love');

    Route::delete('inbox/{testimonial}', [TestimonialModerationController::class, 'destroy'])
        ->name('testimonials.destroy');

    Route::get('wall-of-love', WallOfLoveController::class)
        ->name('wall-of-love.index');

    Route::get('widgets', [WidgetsController::class, 'index'])
        ->name('widgets.index');

    Route::get('widgets/create', [WidgetsController::class, 'create'])
        ->name('widgets.create');

    Route::post('widgets', [WidgetsController::class, 'store'])
        ->name('widgets.store');

    Route::get('widgets/{widget}/edit', [WidgetsController::class, 'edit'])
        ->name('widgets.edit');

    Route::put('widgets/{widget}', [WidgetsController::class, 'update'])
        ->name('widgets.update');

    Route::delete('widgets/{widget}', [WidgetsController::class, 'destroy'])
        ->name('widgets.destroy');

    Route::get('widgets/{widget}/preview', [WidgetsController::class, 'preview'])
        ->name('widgets.preview');

    Route::get('billing', BillingController::class)
        ->name('billing.index');

    Route::post('billing/checkout', [BillingController::class, 'checkout'])
        ->name('billing.checkout');

    Route::post('billing/portal', [BillingController::class, 'portal'])
        ->name('billing.portal');

    Route::get('billing/checkout/success', [BillingController::class, 'checkoutSuccess'])
        ->name('billing.checkout.success');

    Route::get('billing/checkout/cancelled', [BillingController::class, 'checkoutCancelled'])
        ->name('billing.checkout.cancelled');

    Route::get('profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::put('password', [ProfileController::class, 'updatePassword'])
        ->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
