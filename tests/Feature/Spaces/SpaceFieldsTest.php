<?php

use App\Enums\SpaceFieldKey;
use App\Models\Space;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Configurable collection fields
|--------------------------------------------------------------------------
|
| Covers App\Http\Controllers\SpacesController::fields and updateFields, and
| App\Http\Requests\UpdateSpaceFieldsRequest.
|
*/

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->owner->forceFill(['plan_slug' => 'free'])->save();

    $this->other = User::factory()->create();
    $this->other->forceFill(['plan_slug' => 'free'])->save();

    $this->space = Space::factory()->for($this->owner)->create();

    foreach (SpaceFieldKey::cases() as $index => $key) {
        $this->space->fields()->create([
            'field_key' => $key->value,
            'enabled' => true,
            'required' => false,
            'sort_order' => $index,
        ]);
    }
});

/**
 * Build a valid field configuration payload.
 */
function fieldPayload(array $overrides = []): array
{
    $fields = [];

    foreach (SpaceFieldKey::cases() as $key) {
        $fields[$key->value] = [
            'enabled' => true,
            'required' => false,
        ];
    }

    return ['fields' => array_replace_recursive($fields, $overrides)];
}

test('the fields page can be rendered by the owner', function () {
    $this->actingAs($this->owner)
        ->get(route('spaces.fields', ['space' => $this->space]))
        ->assertOk()
        ->assertSee('Collection fields');
});

test('another account cannot reach the fields page', function () {
    $this->actingAs($this->other)
        ->get(route('spaces.fields', ['space' => $this->space]))
        ->assertForbidden();
});

test('the owner can disable a field', function () {
    $this->actingAs($this->owner)
        ->put(route('spaces.fields.update', ['space' => $this->space]), fieldPayload([
            'company_name' => ['enabled' => false],
        ]))
        ->assertRedirect(route('spaces.index'))
        ->assertSessionHas('status', 'fields-updated');

    expect($this->space->hasFieldEnabled(SpaceFieldKey::CompanyName))->toBeFalse();
});

test('the owner can mark a field as required', function () {
    $this->actingAs($this->owner)
        ->put(route('spaces.fields.update', ['space' => $this->space]), fieldPayload([
            'social_url' => ['required' => true],
        ]))
        ->assertSessionHasNoErrors();

    expect($this->space->hasFieldRequired(SpaceFieldKey::SocialUrl))->toBeTrue();
});

test('a disabled field is never stored as required', function () {
    $this->actingAs($this->owner)
        ->put(route('spaces.fields.update', ['space' => $this->space]), fieldPayload([
            'company_name' => ['enabled' => false, 'required' => true],
        ]))
        ->assertSessionHasNoErrors();

    $field = $this->space->fields()
        ->where('field_key', SpaceFieldKey::CompanyName->value)
        ->sole();

    expect($field->enabled)->toBeFalse()
        ->and($field->required)->toBeFalse();
});

test('another account cannot update the fields', function () {
    $this->actingAs($this->other)
        ->put(route('spaces.fields.update', ['space' => $this->space]), fieldPayload([
            'company_name' => ['enabled' => false],
        ]))
        ->assertForbidden();

    expect($this->space->hasFieldEnabled(SpaceFieldKey::CompanyName))->toBeTrue();
});

test('a guest cannot update the fields', function () {
    $this->put(route('spaces.fields.update', ['space' => $this->space]), fieldPayload())
        ->assertRedirect(route('login'));
});

test('an unknown field key is rejected', function () {
    $payload = fieldPayload();
    $payload['fields']['not_a_field'] = ['enabled' => true, 'required' => false];

    $this->actingAs($this->owner)
        ->put(route('spaces.fields.update', ['space' => $this->space]), $payload)
        ->assertSessionHasErrors('fields');
});

test('a missing field entry is rejected', function () {
    $payload = fieldPayload();
    unset($payload['fields'][SpaceFieldKey::ProfilePhoto->value]);

    $this->actingAs($this->owner)
        ->put(route('spaces.fields.update', ['space' => $this->space]), $payload)
        ->assertSessionHasErrors('fields.'.SpaceFieldKey::ProfilePhoto->value);
});
