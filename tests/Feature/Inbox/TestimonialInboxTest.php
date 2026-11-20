<?php

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Testimonial inbox
|--------------------------------------------------------------------------
|
| Covers the inbox listing, its filters, its search, its pagination and
| the ordering of the results.
|
*/

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->owner->forceFill(['plan_slug' => 'free'])->save();

    $this->other = User::factory()->create();
    $this->other->forceFill(['plan_slug' => 'free'])->save();

    $this->space = Space::factory()->for($this->owner)->create(['name' => 'Main space']);
    $this->secondSpace = Space::factory()->for($this->owner)->create(['name' => 'Second space']);
    $this->foreignSpace = Space::factory()->for($this->other)->create();
});

test('the inbox shows the testimonials of the account spaces', function () {
    $testimonial = Testimonial::factory()->for($this->space)->create(['customer_name' => 'Ada Lovelace']);

    $this->actingAs($this->owner)
        ->get(route('inbox.index'))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('Main space');
});

test('the inbox never shows the testimonials of another account', function () {
    Testimonial::factory()->for($this->foreignSpace)->create(['customer_name' => 'Secret Customer']);

    $this->actingAs($this->owner)
        ->get(route('inbox.index'))
        ->assertOk()
        ->assertDontSee('Secret Customer');
});

test('a guest is redirected to the login page', function () {
    $this->get(route('inbox.index'))->assertRedirect(route('login'));
});

test('the inbox counts the testimonials by moderation status', function () {
    Testimonial::factory()->count(2)->approved()->for($this->space)->create();
    Testimonial::factory()->count(3)->pending()->for($this->space)->create();
    Testimonial::factory()->hidden()->for($this->space)->create();

    $html = $this->actingAs($this->owner)->get(route('inbox.index'))->getContent();

    expect($html)->toContain('Total Testimonials')
        ->and($html)->toContain('Pending')
        ->and($html)->toContain('Approved');
});

test('the inbox count badges exclude the testimonials of another account', function () {
    Testimonial::factory()->count(5)->for($this->foreignSpace)->create();
    Testimonial::factory()->for($this->space)->create();

    $html = $this->actingAs($this->owner)->get(route('inbox.index'))->getContent();

    // The foreign testimonials must not be counted. The total is rendered in
    // the stat card, so the count of six must not appear.
    expect($html)->not->toContain('>6<');
});

test('the inbox can be filtered by space', function () {
    Testimonial::factory()->for($this->space)->create(['customer_name' => 'In First']);
    Testimonial::factory()->for($this->secondSpace)->create(['customer_name' => 'In Second']);

    $this->actingAs($this->owner)
        ->get(route('inbox.index', ['space_id' => $this->secondSpace->id]))
        ->assertOk()
        ->assertSee('In Second')
        ->assertDontSee('In First');
});

test('the inbox can be filtered by status', function () {
    Testimonial::factory()->approved()->for($this->space)->create(['customer_name' => 'Approved One']);
    Testimonial::factory()->pending()->for($this->space)->create(['customer_name' => 'Pending One']);

    $this->actingAs($this->owner)
        ->get(route('inbox.index', ['status' => 'approved']))
        ->assertOk()
        ->assertSee('Approved One')
        ->assertDontSee('Pending One');
});

test('the inbox can be filtered by rating', function () {
    Testimonial::factory()->for($this->space)->create(['customer_name' => 'Five Star', 'rating' => 5]);
    Testimonial::factory()->for($this->space)->create(['customer_name' => 'Two Star', 'rating' => 2]);

    $this->actingAs($this->owner)
        ->get(route('inbox.index', ['rating' => 5]))
        ->assertOk()
        ->assertSee('Five Star')
        ->assertDontSee('Two Star');
});

test('the inbox can be filtered to favourites only', function () {
    Testimonial::factory()->favorite()->for($this->space)->create(['customer_name' => 'Favourite One']);
    Testimonial::factory()->for($this->space)->create(['customer_name' => 'Plain One']);

    $this->actingAs($this->owner)
        ->get(route('inbox.index', ['favorites' => 1]))
        ->assertOk()
        ->assertSee('Favourite One')
        ->assertDontSee('Plain One');
});

test('the inbox can be filtered by date range', function () {
    Testimonial::factory()->for($this->space)->create([
        'customer_name' => 'Recent One',
        'created_at' => now()->subDays(2),
    ]);

    Testimonial::factory()->for($this->space)->create([
        'customer_name' => 'Old One',
        'created_at' => now()->subDays(60),
    ]);

    $this->actingAs($this->owner)
        ->get(route('inbox.index', ['range' => 7]))
        ->assertOk()
        ->assertSee('Recent One')
        ->assertDontSee('Old One');
});

test('the inbox supports a text search', function () {
    Testimonial::factory()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'testimonial_text' => 'A wonderful product.',
    ]);

    Testimonial::factory()->for($this->space)->create([
        'customer_name' => 'Grace Hopper',
        'testimonial_text' => 'Nothing special.',
    ]);

    $this->actingAs($this->owner)
        ->get(route('inbox.index', ['search' => 'wonderful']))
        ->assertOk()
        ->assertSee('A wonderful product.')
        ->assertDontSee('Nothing special.');
});

test('the inbox search matches the customer name', function () {
    Testimonial::factory()->for($this->space)->create(['customer_name' => 'Katherine Johnson']);
    Testimonial::factory()->for($this->space)->create(['customer_name' => 'Someone Else']);

    $this->actingAs($this->owner)
        ->get(route('inbox.index', ['search' => 'Katherine']))
        ->assertOk()
        ->assertSee('Katherine Johnson')
        ->assertDontSee('Someone Else');
});

test('an unknown filter value is ignored rather than breaking the inbox', function () {
    Testimonial::factory()->for($this->space)->create(['customer_name' => 'Still Listed']);

    $this->actingAs($this->owner)
        ->get(route('inbox.index', ['status' => 'bogus', 'rating' => 99, 'range' => 365]))
        ->assertOk()
        ->assertSee('Still Listed');
});

test('favourites are pinned above the other testimonials', function () {
    $old = Testimonial::factory()->for($this->space)->create([
        'customer_name' => 'Older Favourite',
        'is_favorite' => true,
        'created_at' => now()->subDays(10),
    ]);

    $new = Testimonial::factory()->for($this->space)->create([
        'customer_name' => 'Newest Plain',
        'created_at' => now(),
    ]);

    $html = $this->actingAs($this->owner)->get(route('inbox.index'))->getContent();

    expect(strpos($html, 'Older Favourite'))->toBeLessThan(strpos($html, 'Newest Plain'))
        ->and($old->is_favorite)->toBeTrue()
        ->and($new->is_favorite)->toBeFalse();
});

test('the inbox is paginated', function () {
    Testimonial::factory()->count(15)->for($this->space)->create();

    $response = $this->actingAs($this->owner)->get(route('inbox.index'));

    $response->assertOk();

    expect($response->viewData('testimonials')->total())->toBe(15)
        ->and($response->viewData('testimonials')->perPage())->toBe(12)
        ->and($response->viewData('testimonials')->count())->toBe(12);
});

test('the second page of the inbox shows the remaining testimonials', function () {
    Testimonial::factory()->count(15)->for($this->space)->create();

    $response = $this->actingAs($this->owner)->get(route('inbox.index', ['page' => 2]));

    $response->assertOk();

    expect($response->viewData('testimonials')->count())->toBe(3)
        ->and($response->viewData('testimonials')->currentPage())->toBe(2);
});

test('a soft deleted testimonial is excluded from the inbox', function () {
    Testimonial::factory()->for($this->space)->create([
        'customer_name' => 'Removed Customer',
    ])->delete();

    $this->actingAs($this->owner)
        ->get(route('inbox.index'))
        ->assertOk()
        ->assertDontSee('Removed Customer');
});

test('the inbox offers the approve hide favourite and wall actions', function () {
    $testimonial = Testimonial::factory()->for($this->space)->create();

    $this->actingAs($this->owner)
        ->get(route('inbox.index'))
        ->assertOk()
        ->assertSee(route('testimonials.approve', ['testimonial' => $testimonial]), escape: false)
        ->assertSee(route('testimonials.hide', ['testimonial' => $testimonial]), escape: false)
        ->assertSee(route('testimonials.favorite', ['testimonial' => $testimonial]), escape: false)
        ->assertSee(route('testimonials.wall-of-love', ['testimonial' => $testimonial]), escape: false)
        ->assertSee(route('testimonials.edit', ['testimonial' => $testimonial]), escape: false)
        ->assertSee(route('testimonials.destroy', ['testimonial' => $testimonial]), escape: false);
});

test('the inbox shows the moderation status of every testimonial', function () {
    Testimonial::factory()->approved()->for($this->space)->create();
    Testimonial::factory()->pending()->for($this->space)->create();
    Testimonial::factory()->hidden()->for($this->space)->create();

    $html = $this->actingAs($this->owner)->get(route('inbox.index'))->getContent();

    expect($html)->toContain('Approved')
        ->and($html)->toContain('Pending')
        ->and($html)->toContain('Hidden');
});

test('the inbox shows the rating and the sharing consent state', function () {
    Testimonial::factory()->for($this->space)->create([
        'rating' => 4,
        'sharing_consent' => false,
    ]);

    $html = $this->actingAs($this->owner)->get(route('inbox.index'))->getContent();

    expect($html)->toContain('4')
        ->and($html)->toContain('No sharing consent');
});

test('a testimonial without a profile photo shows the initial of the customer', function () {
    Testimonial::factory()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'profile_photo_path' => null,
    ]);

    $html = $this->actingAs($this->owner)->get(route('inbox.index'))->getContent();

    expect($html)->toMatch('/>\s*A\s*<\/span>/');
});

/*
|--------------------------------------------------------------------------
| The count badges
|--------------------------------------------------------------------------
|
| The badges carry a number as well as a label, so the number itself must
| be checked rather than only the label.
|
*/

test('the inbox count badges show the number of testimonials in each status', function () {
    Testimonial::factory()->count(2)->approved()->for($this->space)->create();
    Testimonial::factory()->count(3)->pending()->for($this->space)->create();
    Testimonial::factory()->hidden()->for($this->space)->create();

    $response = $this->actingAs($this->owner)->get(route('inbox.index'));

    $response->assertOk();

    expect($response->viewData('totalCount'))->toBe(6)
        ->and($response->viewData('approvedCount'))->toBe(2)
        ->and($response->viewData('pendingCount'))->toBe(3)
        ->and($response->viewData('hiddenCount'))->toBe(1);
});

test('the inbox count badges are rendered with their number', function () {
    Testimonial::factory()->count(2)->approved()->for($this->space)->create();
    Testimonial::factory()->count(3)->pending()->for($this->space)->create();

    $html = $this->actingAs($this->owner)->get(route('inbox.index'))->getContent();

    // The label and the number sit in separate elements, so the match has
    // to cross the line break between them. The "s" modifier lets the dot
    // match a newline.
    expect($html)->toMatch('/Total Testimonials.*?>5<\/p>/s')
        ->and($html)->toMatch('/Pending.*?>3<\/p>/s')
        ->and($html)->toMatch('/Approved.*?>2<\/p>/s');
});

/*
|--------------------------------------------------------------------------
| The ellipsis menu
|--------------------------------------------------------------------------
|
| The secondary actions live behind an ellipsis menu. The menu and the
| delete confirmation must both be present.
|
*/

test('the inbox offers an ellipsis menu with the secondary actions', function () {
    $testimonial = Testimonial::factory()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
    ]);

    $html = $this->actingAs($this->owner)->get(route('inbox.index'))->getContent();

    expect($html)->toContain('More actions for Ada Lovelace')
        ->and($html)->toContain('Edit testimonial')
        ->and($html)->toContain('Delete testimonial')
        ->and($html)->toContain(route('testimonials.edit', ['testimonial' => $testimonial]), escape: false)
        ->and($html)->toContain(route('testimonials.destroy', ['testimonial' => $testimonial]), escape: false);
});

test('the inbox asks for a confirmation before a testimonial is deleted', function () {
    Testimonial::factory()->for($this->space)->create();

    $html = $this->actingAs($this->owner)->get(route('inbox.index'))->getContent();

    expect($html)->toContain('confirm(')
        ->and($html)->toContain('Delete this testimonial?');
});

/*
|--------------------------------------------------------------------------
| The output is escaped
|--------------------------------------------------------------------------
|
| A testimonial carries text that a customer wrote. It must never be
| rendered as markup.
|
*/

test('a testimonial containing html is escaped in the inbox', function () {
    Testimonial::factory()->for($this->space)->create([
        'customer_name' => '<script>alert("name")</script>',
        'testimonial_text' => '<script>alert("text")</script>',
        'company_name' => '<b>Company</b>',
    ]);

    $html = $this->actingAs($this->owner)->get(route('inbox.index'))->getContent();

    expect($html)->not->toContain('<script>alert("name")</script>')
        ->and($html)->not->toContain('<script>alert("text")</script>')
        ->and($html)->not->toContain('<b>Company</b>')
        ->and($html)->toContain('&lt;script&gt;alert(&quot;name&quot;)&lt;/script&gt;');
});

/*
|--------------------------------------------------------------------------
| The remaining filter combinations
|--------------------------------------------------------------------------
*/

test('the inbox can be filtered to hidden testimonials', function () {
    Testimonial::factory()->hidden()->for($this->space)->create(['customer_name' => 'Hidden One']);
    Testimonial::factory()->approved()->for($this->space)->create(['customer_name' => 'Approved One']);

    $this->actingAs($this->owner)
        ->get(route('inbox.index', ['status' => 'hidden']))
        ->assertOk()
        ->assertSee('Hidden One')
        ->assertDontSee('Approved One');
});

test('the space filter and the favourites filter can be combined', function () {
    Testimonial::factory()->favorite()->for($this->space)->create(['customer_name' => 'Favourite In First']);
    Testimonial::factory()->for($this->space)->create(['customer_name' => 'Plain In First']);
    Testimonial::factory()->favorite()->for($this->secondSpace)->create(['customer_name' => 'Favourite In Second']);

    $this->actingAs($this->owner)
        ->get(route('inbox.index', ['space_id' => $this->space->id, 'favorites' => 1]))
        ->assertOk()
        ->assertSee('Favourite In First')
        ->assertDontSee('Plain In First')
        ->assertDontSee('Favourite In Second');
});

test('the space filter and the search can be combined', function () {
    Testimonial::factory()->for($this->space)->create([
        'customer_name' => 'Ada Lovelace',
        'testimonial_text' => 'A wonderful product.',
    ]);

    Testimonial::factory()->for($this->secondSpace)->create([
        'customer_name' => 'Ada Lovelace',
        'testimonial_text' => 'A wonderful product.',
    ]);

    $this->actingAs($this->owner)
        ->get(route('inbox.index', ['space_id' => $this->space->id, 'search' => 'wonderful']))
        ->assertOk()
        ->assertOk();

    expect($this->actingAs($this->owner)
        ->get(route('inbox.index', ['space_id' => $this->space->id, 'search' => 'wonderful']))
        ->viewData('testimonials')->total())->toBe(1);
});

test('the search filter does not leak the testimonials of another account', function () {
    Testimonial::factory()->for($this->foreignSpace)->create([
        'customer_name' => 'Foreign Person',
        'testimonial_text' => 'A wonderful product.',
    ]);

    Testimonial::factory()->for($this->space)->create([
        'customer_name' => 'Own Person',
        'testimonial_text' => 'A wonderful product.',
    ]);

    $this->actingAs($this->owner)
        ->get(route('inbox.index', ['search' => 'wonderful']))
        ->assertOk()
        ->assertSee('Own Person')
        ->assertDontSee('Foreign Person');
});

test('favourites stay pinned above the other testimonials across a page change', function () {
    // Twelve plain testimonials fill the first page. The favourite is the
    // thirteenth record, so it is only reachable when the favourite ordering
    // is applied before the pagination.
    Testimonial::factory()->count(12)->for($this->space)->create([
        'customer_name' => 'Plain Customer',
        'is_favorite' => false,
    ]);

    Testimonial::factory()->for($this->space)->create([
        'customer_name' => 'Pinned Favourite',
        'is_favorite' => true,
        'created_at' => now()->subDays(30),
    ]);

    $html = $this->actingAs($this->owner)->get(route('inbox.index'))->getContent();

    // The favourite is older than every plain testimonial, so it can only
    // appear on the first page if it is pinned above them.
    expect($html)->toContain('Pinned Favourite')
        ->and($response = $this->actingAs($this->owner)->get(route('inbox.index')))
        ->and($response->viewData('testimonials')->total())->toBe(13);
});

test('the inbox shows the empty state when the account has no testimonials', function () {
    $this->actingAs($this->owner)
        ->get(route('inbox.index'))
        ->assertOk()
        ->assertSee('No testimonials yet');
});
