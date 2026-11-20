<?php

use App\Billing\PlanType;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Dashboard overview
|--------------------------------------------------------------------------
|
| Covers App\Http\Controllers\DashboardController. The dashboard must only
| ever expose aggregate statistics belonging to the authenticated account.
|
*/

beforeEach(function () {
    // Guarantee the plan rows exist before any account is created, so the
    // dashboard always resolves the account's plan.
    $this->seed(PlanSeeder::class);

    // UserFactory does not set plan_slug, and the attribute is not fillable,
    // so the created model has no plan_slug at all. The relation then looks
    // for a plan whose slug is NULL. Force the value onto the model and save
    // it, exactly as RegisteredUserController does when it assigns the plan.
    $this->user = User::factory()->create(['name' => 'Ada Lovelace']);
    $this->user->forceFill(['plan_slug' => PlanType::Free->value])->save();

    $this->other = User::factory()->create(['name' => 'Mallory']);
    $this->other->forceFill(['plan_slug' => PlanType::Free->value])->save();
});

afterEach(function () {
    Carbon::setTestNow();
});

test('the dashboard renders for an authenticated user', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Kudosly')
        ->assertSee('Ada Lovelace');
});

test('a guest is redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the dashboard counts the spaces owned by the authenticated user', function () {
    Space::factory()->count(2)->for($this->user)->create();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Total Spaces')
        ->assertViewHas('spacesCount', 2)
        ->assertViewHas('maxSpaces', 3);
});

test('the dashboard does not count spaces owned by another user', function () {
    Space::factory()->count(3)->for($this->other)->create();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('spacesCount', 0)
        ->assertViewHas('maxSpaces', 3);
});

test('the dashboard counts the testimonials of the authenticated user', function () {
    $space = Space::factory()->for($this->user)->create();

    Testimonial::factory()->count(3)->for($space)->create();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Total Testimonials')
        ->assertViewHas('totalTestimonials', 3);
});

test('the dashboard does not count the testimonials of another user', function () {
    $otherSpace = Space::factory()->for($this->other)->create();

    Testimonial::factory()->count(5)->for($otherSpace)->create();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('totalTestimonials', 0);
});

test('the dashboard counts unique customers across all of the users spaces', function () {
    $first = Space::factory()->for($this->user)->create();
    $second = Space::factory()->for($this->user)->create();

    Testimonial::factory()->for($first)->create(['customer_email' => 'one@example.com']);
    Testimonial::factory()->for($first)->create(['customer_email' => 'two@example.com']);
    Testimonial::factory()->for($second)->create(['customer_email' => 'three@example.com']);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Unique Customers')
        ->assertViewHas('uniqueCustomers', 3);
});

test('unique customers are counted case insensitively after trimming', function () {
    $space = Space::factory()->for($this->user)->create();

    Testimonial::factory()->for($space)->create(['customer_email' => 'one@example.com']);
    Testimonial::factory()->for($space)->create(['customer_email' => 'ONE@example.com']);
    Testimonial::factory()->for($space)->create(['customer_email' => '  one@example.com  ']);
    Testimonial::factory()->for($space)->create(['customer_email' => 'two@example.com']);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Unique Customers')
        ->assertViewHas('uniqueCustomers', 2);
});

test('empty and whitespace only customer addresses are excluded from the unique count', function () {
    $space = Space::factory()->for($this->user)->create();

    Testimonial::factory()->for($space)->create(['customer_email' => 'one@example.com']);
    Testimonial::factory()->for($space)->create(['customer_email' => '']);
    Testimonial::factory()->for($space)->create(['customer_email' => '   ']);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('uniqueCustomers', 1);
});

test('testimonials this month only counts the current calendar month', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));

    $space = Space::factory()->for($this->user)->create();

    Testimonial::factory()->for($space)->create(['created_at' => '2026-06-10 09:00:00']);
    Testimonial::factory()->for($space)->create(['created_at' => '2026-06-14 23:59:59']);
    Testimonial::factory()->for($space)->create(['created_at' => '2026-05-31 23:59:59']);
    Testimonial::factory()->for($space)->create(['created_at' => '2026-06-01 00:00:00']);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Testimonials This Month')
        ->assertViewHas('testimonialsThisMonth', 3);
});

test('the average rating is shown to one decimal place', function () {
    $space = Space::factory()->for($this->user)->create();

    Testimonial::factory()->for($space)->create(['rating' => 5]);
    Testimonial::factory()->for($space)->create(['rating' => 4]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('4.5');
});

test('the average rating shows a dash when nothing has been rated', function () {
    $space = Space::factory()->for($this->user)->create();

    Testimonial::factory()->for($space)->create(['rating' => null]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('no ratings yet');
});

test('the dashboard reports the plan name and the remaining space quota', function () {
    Space::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('You are on the Free plan.')
        ->assertSee('2 spaces remaining on your plan.');
});

test('the dashboard reports the space limit once it is reached', function () {
    Space::factory()->count(3)->for($this->user)->create();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('You have reached the space limit included in your plan.');
});

test('the upgrade banner is shown to a free plan user', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Upgrade for more spaces.');
});

test('the upgrade banner is not shown to a pro plan user', function () {
    $pro = User::factory()->create(['plan_slug' => 'pro']);

    $this->actingAs($pro)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Upgrade for more spaces.');
});

test('the empty state for spaces is shown when the account owns no spaces', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('No spaces yet');
});

test('the empty state for testimonials is shown when the account has no testimonials', function () {
    Space::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('No testimonials yet');
});

test('the dashboard shows the recent testimonials but never a customer email address', function () {
    $space = Space::factory()->for($this->user)->create();

    Testimonial::factory()->for($space)->create([
        'customer_name' => 'Grace Hopper',
        'customer_email' => 'grace.secret@example.com',
        'company_name' => 'Acme Industries',
        'testimonial_text' => 'A wonderfully useful testimonial.',
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    // The recent testimonials section shows the customer name, the company
    // and an excerpt of the testimonial. The email address is never shown.
    $response->assertOk()
        ->assertSee('Recent testimonials')
        ->assertSee('Grace Hopper')
        ->assertSee('Acme Industries')
        ->assertSee('A wonderfully useful testimonial.')
        ->assertDontSee('grace.secret@example.com');
});

test('the dashboard never exposes a customer email address', function () {
    $space = Space::factory()->for($this->user)->create();

    Testimonial::factory()->for($space)->create([
        'customer_name' => 'Grace Hopper',
        'customer_email' => 'grace.secret@example.com',
        'testimonial_text' => 'A very private piece of testimonial text.',
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertOk()
        ->assertDontSee('grace.secret@example.com');
});

test('the dashboard never exposes another accounts customer details', function () {
    $otherSpace = Space::factory()->for($this->other)->create();

    Testimonial::factory()->for($otherSpace)->create([
        'customer_name' => 'Alan Turing',
        'customer_email' => 'alan.secret@example.com',
        'testimonial_text' => 'Another account private testimonial.',
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertOk()
        ->assertDontSee('Alan Turing')
        ->assertDontSee('alan.secret@example.com')
        ->assertDontSee('Another account private testimonial.');
});

/*
|--------------------------------------------------------------------------
| Create New Space action
|--------------------------------------------------------------------------
|
| Space creation is wired up, so the primary action must link to the space
| creation form and open it successfully.
|
*/

test('the dashboard shows the create new space action', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Create New Space');
});

test('the create new space action links to the space creation form', function () {
    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Create New Space')
        ->assertSee(route('spaces.create'), escape: false);

    expect($response->getContent())->not->toContain('Space creation coming soon.');
});

test('the create new space action opens the space creation form', function () {
    $response = $this->actingAs($this->user)->get(route('spaces.create'));

    $response->assertOk()
        ->assertSee('Create a space')
        ->assertSee(route('spaces.store'), escape: false)
        ->assertSee('Create space');
});

/*
|--------------------------------------------------------------------------
| Dashboard layout
|--------------------------------------------------------------------------
|
| Covers the redesigned overview: the three summary cards, the spaces
| section and the sidebar icons.
|
*/

test('the dashboard shows the three overview cards', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Total Testimonials')
        ->assertSee('Total Spaces')
        ->assertSee('Current Plan');
});

test('the total spaces card shows the plan limit', function () {
    Space::factory()->count(2)->for($this->user)->create();

    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    expect($html)->toContain('/ 3');
});

test('the current plan card shows the plan name', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Free');
});

test('the dashboard shows the spaces section with a search field', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Spaces')
        ->assertSee('Search spaces...');
});

test('the spaces section shows its empty state when the account owns no spaces', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('No spaces yet');
});

test('the spaces section lists the spaces owned by the account', function () {
    Space::factory()->for($this->user)->create(['name' => 'Ada Reviews']);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Ada Reviews');
});

test('the spaces section does not list the spaces of another account', function () {
    Space::factory()->for($this->other)->create(['name' => 'Mallory Reviews']);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Mallory Reviews');
});

test('the sidebar renders an icon beside every navigation item', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    foreach (['Overview', 'Spaces', 'Inbox', 'Wall of Love', 'Widgets', 'Billing', 'Settings'] as $label) {
        expect($html)->toContain($label);
    }

    // Each nav link carries an inline outline icon.
    expect(substr_count($html, '<svg'))->toBeGreaterThanOrEqual(7);
});

test('the sidebar highlights the active navigation item with a light purple background', function () {
    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    expect($html)->toContain('bg-indigo-50');
});

/*
|--------------------------------------------------------------------------
| Submission chart
|--------------------------------------------------------------------------
|
| The dashboard shows a server rendered chart of the daily testimonial
| submissions. The chart supports the 7, 30 and 90 day ranges.
|
*/

test('the dashboard shows the submission chart', function () {
    $space = Space::factory()->for($this->user)->create();

    Testimonial::factory()->for($space)->create();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Testimonial submissions')
        ->assertSee('data-testid="submission-chart"', escape: false);
});

test('the chart shows an empty state when there are no submissions', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('No submissions in this period');
});

test('the chart counts the testimonials submitted on each day', function () {
    $space = Space::factory()->for($this->user)->create();

    Testimonial::factory()->count(2)->for($space)->create(['created_at' => now()]);
    Testimonial::factory()->for($space)->create(['created_at' => now()->subDays(3)]);

    $chart = $this->actingAs($this->user)->get(route('dashboard'))->viewData('chart');

    expect($chart['counts'])->toHaveCount(30)
        ->and($chart['counts'][29])->toBe(2)
        ->and($chart['counts'][26])->toBe(1)
        ->and($chart['total'])->toBe(3);
});

test('the chart supports the seven day range', function () {
    $space = Space::factory()->for($this->user)->create();

    Testimonial::factory()->for($space)->create(['created_at' => now()]);
    Testimonial::factory()->for($space)->create(['created_at' => now()->subDays(20)]);

    $chart = $this->actingAs($this->user)
        ->get(route('dashboard', ['range' => 7]))
        ->viewData('chart');

    expect($chart['range'])->toBe(7)
        ->and($chart['counts'])->toHaveCount(7)
        ->and($chart['total'])->toBe(1);
});

test('the chart supports the thirty day range', function () {
    $chart = $this->actingAs($this->user)
        ->get(route('dashboard', ['range' => 30]))
        ->viewData('chart');

    expect($chart['range'])->toBe(30)
        ->and($chart['counts'])->toHaveCount(30);
});

test('the chart supports the ninety day range', function () {
    $chart = $this->actingAs($this->user)
        ->get(route('dashboard', ['range' => 90]))
        ->viewData('chart');

    expect($chart['range'])->toBe(90)
        ->and($chart['counts'])->toHaveCount(90);
});

test('an unknown chart range falls back to the default', function () {
    $chart = $this->actingAs($this->user)
        ->get(route('dashboard', ['range' => 365]))
        ->viewData('chart');

    expect($chart['range'])->toBe(30);
});

test('the chart offers the seven thirty and ninety day links', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('7 days')
        ->assertSee('30 days')
        ->assertSee('90 days');
});

test('the chart only counts the testimonials of the account', function () {
    $otherSpace = Space::factory()->for($this->other)->create();

    Testimonial::factory()->count(4)->for($otherSpace)->create(['created_at' => now()]);

    $chart = $this->actingAs($this->user)->get(route('dashboard'))->viewData('chart');

    expect($chart['total'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Recent testimonials
|--------------------------------------------------------------------------
|
| The dashboard shows the most recent testimonials sent to the account's
| spaces.
|
*/

test('the dashboard shows the recent testimonials section', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Recent testimonials');
});

test('the dashboard shows the most recent testimonials first', function () {
    $space = Space::factory()->for($this->user)->create();

    Testimonial::factory()->for($space)->create([
        'customer_name' => 'Older Customer',
        'created_at' => now()->subDays(5),
    ]);

    Testimonial::factory()->for($space)->create([
        'customer_name' => 'Newer Customer',
        'created_at' => now(),
    ]);

    $html = $this->actingAs($this->user)->get(route('dashboard'))->getContent();

    expect(strpos($html, 'Newer Customer'))->toBeLessThan(strpos($html, 'Older Customer'));
});

test('the dashboard shows at most five recent testimonials', function () {
    $space = Space::factory()->for($this->user)->create();

    Testimonial::factory()->count(8)->for($space)->create();

    $recent = $this->actingAs($this->user)->get(route('dashboard'))->viewData('recentTestimonials');

    expect($recent)->toHaveCount(5);
});

test('the dashboard never shows the recent testimonials of another account', function () {
    $otherSpace = Space::factory()->for($this->other)->create();

    Testimonial::factory()->for($otherSpace)->create([
        'customer_name' => 'Mallory Reviews',
    ]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Mallory Reviews');
});

test('the dashboard links to the inbox from the recent testimonials', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('inbox.index'), escape: false);
});
