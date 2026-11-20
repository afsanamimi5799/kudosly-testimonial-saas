<?php

namespace App\Http\Requests;

use App\Enums\WidgetLayout;
use App\Enums\WidgetThemeMode;
use App\Models\Space;
use App\Widgets\WidgetSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the creation of a widget.
 *
 * The request carries the ownership pre-check for the space the widget is
 * created for. The controller repeats the check before the widget is
 * stored, so that the rule cannot be skipped by calling the action
 * directly.
 */
class StoreWidgetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $space = $this->space();

        if (! $space instanceof Space) {
            return false;
        }

        // The ability is resolved against the space, because the widget is
        // always created for a space. SpacePolicy::manageWidget is the
        // ability that carries the ownership check for a space's widgets.
        return $this->user()?->can('manageWidget', $space) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'space_id' => ['required', 'integer', Rule::exists('spaces', 'id')->where('user_id', $this->user()?->getKey())],
            'layout' => ['required', Rule::enum(WidgetLayout::class)],
            'theme_mode' => ['required', Rule::enum(WidgetThemeMode::class)],
            'background_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'animation_enabled' => ['required', 'boolean'],
            'display_limit' => ['required', 'integer', 'min:'.WidgetSettings::DISPLAY_LIMIT_MIN, 'max:'.WidgetSettings::DISPLAY_LIMIT_MAX],
        ];
    }

    /**
     * The space the widget is created for.
     *
     * The lookup is scoped to the spaces of the authenticated account, so a
     * space that belongs to somebody else is never resolved in the first
     * place.
     */
    public function space(): ?Space
    {
        $space = $this->route('space');

        if ($space instanceof Space) {
            return $space;
        }

        $spaceId = $this->integer('space_id');

        if ($spaceId <= 0) {
            return null;
        }

        $user = $this->user();

        if ($user === null) {
            return null;
        }

        return Space::query()
            ->where('user_id', $user->getKey())
            ->find($spaceId);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'space_id.exists' => 'Please choose one of your own spaces.',
            'background_color.regex' => 'The background colour must be a six digit hex value, for example #eef2ff.',
            'display_limit.min' => 'A widget must show at least '.WidgetSettings::DISPLAY_LIMIT_MIN.' testimonial.',
            'display_limit.max' => 'A widget can show at most '.WidgetSettings::DISPLAY_LIMIT_MAX.' testimonials.',
        ];
    }
}
