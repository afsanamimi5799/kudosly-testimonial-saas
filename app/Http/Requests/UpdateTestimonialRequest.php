<?php

namespace App\Http\Requests;

use App\Models\Testimonial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the manual edit of a testimonial by its owner.
 *
 * The moderation status is deliberately not editable here. The status is
 * changed by the approve and hide actions so that the moderation history
 * always goes through a single, authorized route.
 */
class UpdateTestimonialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $testimonial = $this->route('testimonial');

        return $testimonial instanceof Testimonial
            && $this->user()?->can('update', $testimonial) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'string', 'email', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'social_url' => ['nullable', 'url', 'max:255'],
            'testimonial_text' => ['required', 'string', 'max:5000'],
            'rating' => ['nullable', 'integer', Rule::in([1, 2, 3, 4, 5])],
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
            'rating.in' => 'Please choose a rating between 1 and 5.',
        ];
    }

    /**
     * Normalise the email address before validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->customer_email)) {
            $this->merge([
                'customer_email' => mb_strtolower(trim($this->customer_email)),
            ]);
        }
    }
}
