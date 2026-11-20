<?php

namespace App\Http\Requests;

use App\Enums\SpaceTheme;
use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the update of a space.
 */
class UpdateSpaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $space = $this->route('space');

        return $space instanceof Space && $this->user()?->can('update', $space) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $space = $this->route('space');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('spaces', 'slug')->ignore($space instanceof Space ? $space->getKey() : null),
            ],
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'ask' => ['required', 'string', 'max:1000'],
            'theme' => ['required', Rule::enum(SpaceTheme::class)],
            'rating_enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.alpha_dash' => 'The link may only contain letters, numbers, dashes and underscores.',
            'slug.unique' => 'That link is already taken. Please choose another one.',
        ];
    }
}
