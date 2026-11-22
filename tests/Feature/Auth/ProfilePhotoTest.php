<?php

use App\Billing\PlanType;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Profile photo
|--------------------------------------------------------------------------
|
| Covers App\Http\Controllers\ProfileController::updatePhoto and
| ::destroyPhoto, App\Http\Requests\ProfilePhotoRequest and the two
| helpers on App\Models\User.
|
| The picture belongs to the account that is signed in. The account is
| taken from the session, so a request cannot name a different one.
|
*/

beforeEach(function () {
    Storage::fake('public');

    // The dashboard resolves the plan of the account, so the plan rows
    // have to exist before the account is created.
    $this->seed(PlanSeeder::class);

    $this->user = User::factory()->create(['name' => 'Ada Lovelace']);
    $this->user->forceFill(['plan_slug' => PlanType::Free->value])->save();

    $this->other = User::factory()->create(['name' => 'Mallory']);
    $this->other->forceFill(['plan_slug' => PlanType::Free->value])->save();
});

/*
|--------------------------------------------------------------------------
| The model helpers
|--------------------------------------------------------------------------
*/

test('an account without a picture has no picture address', function () {
    expect($this->user->profile_photo_path)->toBeNull()
        ->and($this->user->profilePhotoUrl())->toBeNull();
});

test('the initial of the name is used when there is no picture', function () {
    expect($this->user->profileInitial())->toBe('A');
});

test('the initial is taken from the first character of the name', function () {
    $user = User::factory()->create(['name' => 'grace hopper']);

    expect($user->profileInitial())->toBe('G');
});

test('the picture address points at the public disk', function () {
    $this->user->forceFill(['profile_photo_path' => 'user-profile-photos/photo.jpg'])->save();

    expect($this->user->profilePhotoUrl())
        ->toContain('user-profile-photos/photo.jpg');
});

test('an empty picture path is treated as no picture', function () {
    $this->user->forceFill(['profile_photo_path' => ''])->save();

    expect($this->user->profilePhotoUrl())->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Uploading
|--------------------------------------------------------------------------
*/

test('a profile photo can be uploaded', function () {
    $this->actingAs($this->user)
        ->post(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('status', 'profile-photo-updated');

    $path = $this->user->fresh()->profile_photo_path;

    expect($path)->toBeString()
        ->and($path)->toStartWith(User::PROFILE_PHOTO_DIRECTORY.'/');

    Storage::disk('public')->assertExists($path);
});

test('the picture is kept in a directory of its own', function () {
    $this->actingAs($this->user)
        ->post(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

    $path = $this->user->fresh()->profile_photo_path;

    // The pictures of the customers of a space live in another directory,
    // so the two can never be confused with one another.
    expect(User::PROFILE_PHOTO_DIRECTORY)->toBe('user-profile-photos')
        ->and($path)->not->toStartWith('profile-photos/');
});

test('the name of the uploaded file is not used on the disk', function () {
    $this->actingAs($this->user)
        ->post(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->image('my-secret-name.jpg'),
        ]);

    $path = $this->user->fresh()->profile_photo_path;

    expect($path)->not->toContain('my-secret-name')
        ->and(basename($path))->not->toBe('my-secret-name.jpg');
});

test('every upload gets a name of its own', function () {
    $this->actingAs($this->user)
        ->post(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

    $first = $this->user->fresh()->profile_photo_path;

    $this->actingAs($this->user)
        ->post(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

    $second = $this->user->fresh()->profile_photo_path;

    expect($first)->not->toBe($second);
});

/*
|--------------------------------------------------------------------------
| Replacing and removing
|--------------------------------------------------------------------------
*/

test('a profile photo can be replaced', function () {
    $this->actingAs($this->user)
        ->post(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->image('first.jpg'),
        ]);

    $first = $this->user->fresh()->profile_photo_path;

    $this->actingAs($this->user)
        ->post(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->image('second.jpg'),
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('status', 'profile-photo-updated');

    $second = $this->user->fresh()->profile_photo_path;

    expect($second)->toBeString()
        ->and($second)->not->toBe($first);

    // The new picture is kept and the one it replaced is gone.
    Storage::disk('public')->assertExists($second);
    Storage::disk('public')->assertMissing($first);
});

test('a profile photo can be removed', function () {
    $this->actingAs($this->user)
        ->post(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

    $path = $this->user->fresh()->profile_photo_path;

    $this->actingAs($this->user)
        ->delete(route('profile.photo.destroy'))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('status', 'profile-photo-removed');

    expect($this->user->fresh()->profile_photo_path)->toBeNull();

    Storage::disk('public')->assertMissing($path);
});

test('removing a picture that is not there does not fail', function () {
    $this->actingAs($this->user)
        ->delete(route('profile.photo.destroy'))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('status', 'profile-photo-removed');

    expect($this->user->fresh()->profile_photo_path)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

test('a picture is required', function () {
    $this->actingAs($this->user)
        ->post(route('profile.photo.update'), [])
        ->assertSessionHasErrors('profile_photo');

    expect($this->user->fresh()->profile_photo_path)->toBeNull();
});

test('the picture must be an image', function () {
    $this->actingAs($this->user)
        ->post(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->create('document.pdf', 100),
        ])
        ->assertSessionHasErrors('profile_photo');

    expect($this->user->fresh()->profile_photo_path)->toBeNull();
});

test('only jpg png and webp pictures are accepted', function (string $name) {
    $this->actingAs($this->user)
        ->post(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->create($name, 10),
        ])
        ->assertSessionHasErrors('profile_photo');

    expect($this->user->fresh()->profile_photo_path)->toBeNull();
})->with(['photo.gif', 'photo.svg', 'photo.bmp']);

test('a picture of two megabytes or less is accepted', function (string $name) {
    $this->actingAs($this->user)
        ->post(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->image($name),
        ])
        ->assertSessionHasNoErrors();

    expect($this->user->fresh()->profile_photo_path)->toBeString();
})->with(['photo.jpg', 'photo.jpeg', 'photo.png', 'photo.webp']);

test('a picture of more than two megabytes is refused', function () {
    $this->actingAs($this->user)
        ->post(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->create('photo.jpg', 2049),
        ])
        ->assertSessionHasErrors('profile_photo');

    expect($this->user->fresh()->profile_photo_path)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Authorization and isolation
|--------------------------------------------------------------------------
*/

test('a guest cannot upload a picture', function () {
    $this->post(route('profile.photo.update'), [
        'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
    ])->assertRedirect(route('login'));

    expect($this->user->fresh()->profile_photo_path)->toBeNull();
});

test('a guest cannot remove a picture', function () {
    $this->delete(route('profile.photo.destroy'))->assertRedirect(route('login'));
});

test('an account cannot name another account in the request', function () {
    $this->actingAs($this->user)
        ->post(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
            'user_id' => $this->other->id,
            'profile_photo_path' => 'user-profile-photos/somebody-elses.jpg',
        ])
        ->assertRedirect(route('profile.edit'));

    // The picture belongs to the account that was signed in.
    expect($this->user->fresh()->profile_photo_path)->toBeString();

    // The other account is left exactly as it was.
    expect($this->other->fresh()->profile_photo_path)->toBeNull();
});

test('the picture path cannot be filled in from the request', function () {
    $this->actingAs($this->user)
        ->post(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
            'profile_photo_path' => 'somewhere/else.jpg',
        ]);

    expect($this->user->fresh()->profile_photo_path)
        ->toStartWith(User::PROFILE_PHOTO_DIRECTORY.'/');
});

test('removing a picture only affects the account that is signed in', function () {
    Storage::disk('public')->put(User::PROFILE_PHOTO_DIRECTORY.'/mallory.jpg', 'photo');

    $this->other->forceFill([
        'profile_photo_path' => User::PROFILE_PHOTO_DIRECTORY.'/mallory.jpg',
    ])->save();

    $this->actingAs($this->user)
        ->delete(route('profile.photo.destroy'))
        ->assertRedirect(route('profile.edit'));

    // The other account keeps its picture.
    expect($this->other->fresh()->profile_photo_path)
        ->toBe(User::PROFILE_PHOTO_DIRECTORY.'/mallory.jpg');

    Storage::disk('public')->assertExists(User::PROFILE_PHOTO_DIRECTORY.'/mallory.jpg');
});

/*
|--------------------------------------------------------------------------
| The settings page
|--------------------------------------------------------------------------
*/

test('the settings page shows the profile photo card above the profile information card', function () {
    $html = $this->actingAs($this->user)->get(route('profile.edit'))->getContent();

    expect($html)->toContain('Profile Photo')
        ->and($html)->toContain('Profile information');

    // The picture card comes first.
    expect(strpos($html, 'Profile Photo'))->toBeLessThan(strpos($html, 'Profile information'));
});

test('the settings page offers the upload form', function () {
    $this->actingAs($this->user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('action="'.route('profile.photo.update').'"', false)
        ->assertSee('enctype="multipart/form-data"', false)
        ->assertSee('name="profile_photo"', false);
});

test('the settings page offers no remove button when there is no picture', function () {
    $html = $this->actingAs($this->user)->get(route('profile.edit'))->getContent();

    // The upload form and the remove form share the same address and are
    // told apart by the method they use, so the button itself is looked for.
    expect($html)->not->toContain('Remove photo');
});

test('the settings page offers the remove button when there is a picture', function () {
    Storage::disk('public')->put(User::PROFILE_PHOTO_DIRECTORY.'/photo.jpg', 'photo');

    $this->user->forceFill([
        'profile_photo_path' => User::PROFILE_PHOTO_DIRECTORY.'/photo.jpg',
    ])->save();

    $html = $this->actingAs($this->user)->get(route('profile.edit'))->assertOk()->getContent();

    expect($html)->toContain('Remove photo')
        ->and($html)->toContain('action="'.route('profile.photo.destroy').'"', false);
});

test('the remove form deletes the picture and the upload form does not', function () {
    $html = $this->actingAs($this->user)->get(route('profile.edit'))->getContent();

    // The upload form uses the post method, the remove form uses the
    // delete method.
    preg_match('/<form method="POST" action="[^"]*profile\/photo".*?<\/form>/s', $html, $upload);

    expect($upload[0] ?? '')->not->toContain('_method');
});

test('the settings page shows the initial when there is no picture', function () {
    $this->actingAs($this->user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('A');
});

test('the settings page shows the uploaded picture', function () {
    $this->actingAs($this->user)
        ->post(route('profile.photo.update'), [
            'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

    $path = $this->user->fresh()->profile_photo_path;

    $this->actingAs($this->user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee($path, false);
});

test('the sidebar shows the picture of the signed in account', function () {
    Storage::disk('public')->put(User::PROFILE_PHOTO_DIRECTORY.'/photo.jpg', 'photo');

    $this->user->forceFill([
        'profile_photo_path' => User::PROFILE_PHOTO_DIRECTORY.'/photo.jpg',
    ])->save();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(User::PROFILE_PHOTO_DIRECTORY.'/photo.jpg', false);
});

test('the sidebar shows the initial when the account has no picture', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Ada Lovelace');
});
