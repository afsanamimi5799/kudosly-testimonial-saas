<?php

namespace App\Http\Requests;

use App\Enums\WidgetLayout;
use App\Enums\WidgetThemeMode;
use App\Models\Widget;
use App\Widgets\WidgetSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the update of a widget.
 *
 * The space of a widget is fixed when it is created, so the update carries
 * the appearance settings only.
 */
class UpdateWidgetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $widget = $this->widget();

        if (! $widget instanceof Widget) {
            return false;
        }

        return $this->user()?->can('update', $widget) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'layout' => ['required', Rule::enum(WidgetLayout::class)],
            'theme_mode' => ['required', Rule::enum(WidgetThemeMode::class)],
            'background_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'animation_enabled' => ['required', 'boolean'],
            'display_limit' => ['required', 'integer', 'min:'.WidgetSettings::DISPLAY_LIMIT_MIN, 'max:'.WidgetSettings::DISPLAY_LIMIT_MAX],
        ];
    }

    /**
     * The widget being updated.
     */
    public function widget(): ?Widget
    {
        $widget = $this->route('widget');

        return $widget instanceof Widget ? $widget : null;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'background_color.regex' => 'The background colour must be a six digit hex value, for example #eef2ff.',
            'display_limit.min' => 'A widget must show at least '.WidgetSettings::DISPLAY_LIMIT_MIN.' testimonial.',
            'display_limit.max' => 'A widget can show at most '.WidgetSettings::DISPLAY_LIMIT_MAX.' testimonials.',
        ];
    }
}
