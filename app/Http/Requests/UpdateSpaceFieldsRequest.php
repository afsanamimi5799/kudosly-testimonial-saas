<?php

namespace App\Http\Requests;

use App\Enums\SpaceFieldKey;
use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the configurable collection fields of a space.
 *
 * The request carries one entry per optional field, keyed by the field key.
 * Each entry declares whether the field is shown and whether it is required.
 * A field that is disabled is never required, so the required flag is only
 * accepted for enabled fields.
 */
class UpdateSpaceFieldsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $space = $this->route('space');

        return $space instanceof Space && $this->user()?->can('manageFields', $space) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (SpaceFieldKey::cases() as $key) {
            $rules['fields.'.$key->value] = ['required', 'array'];
            $rules['fields.'.$key->value.'.enabled'] = ['required', 'boolean'];
            $rules['fields.'.$key->value.'.required'] = ['required', 'boolean'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fields.required' => 'Each field must declare whether it is enabled and whether it is required.',
        ];
    }

    /**
     * Get the fields that may be configured.
     *
     * Only the optional fields are configurable. The customer's name and
     * email are always collected and are not listed here.
     *
     * @return array<string, array{enabled: bool, required: bool}>
     */
    public function configuredFields(): array
    {
        $fields = [];

        foreach (SpaceFieldKey::cases() as $key) {
            $enabled = (bool) $this->input("fields.{$key->value}.enabled");
            $required = $enabled && (bool) $this->input("fields.{$key->value}.required");

            $fields[$key->value] = [
                'enabled' => $enabled,
                'required' => $required,
            ];
        }

        return $fields;
    }

    /**
     * Determine whether the given key is a configurable field.
     */
    public function isKnownKey(string $key): bool
    {
        return in_array($key, $this->allowedKeys(), true);
    }

    /**
     * Get the field keys that may be configured.
     *
     * @return array<int, string>
     */
    public function allowedKeys(): array
    {
        return array_map(
            fn (SpaceFieldKey $key): string => $key->value,
            SpaceFieldKey::cases(),
        );
    }

    /**
     * Validate that no unknown field key was submitted.
     *
     * The rules above only cover the known keys, so an unexpected key would
     * otherwise be silently ignored. Reject it instead.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $submitted = array_keys((array) $this->input('fields', []));
            $allowed = $this->allowedKeys();

            $unknown = array_diff($submitted, $allowed);

            if ($unknown !== []) {
                $validator->errors()->add('fields', 'One or more submitted fields are not configurable.');
            }
        });
    }
}
