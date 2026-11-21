<?php

namespace App\Http\Requests;

use App\Enums\SpaceFieldKey;
use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validates a public testimonial submission.
 *
 * The customer name, email and testimonial text are always collected. The
 * optional fields are only validated when the space has them enabled, and
 * the required flag of the space decides whether they must be filled in.
 * This keeps the server-side rules in step with the rendered form.
 */
class SubmitTestimonialRequest extends FormRequest
{
    /**
     * The maximum size of an uploaded profile photo, in kilobytes.
     */
    public const MAX_PHOTO_KILOBYTES = 2048;

    /**
     * The name of the field that is hidden from people and filled in by
     * automated submissions.
     */
    public const HONEYPOT_FIELD = 'website';

    /**
     * Determine if the user is authorized to make this request.
     *
     * The collection page is public, so the visitor is not authorized
     * against the space, which is resolved from the route and is not owned
     * by them.
     *
     * The one thing that is checked is the hidden field. A person filling
     * in the form never sees it and leaves it empty. A script that fills
     * in every field it finds is caught here, before anything is stored.
     */
    public function authorize(): bool
    {
        return blank($this->input(self::HONEYPOT_FIELD));
    }

    /**
     * Handle a request that failed the authorization check.
     *
     * The submission is quietly dropped and the visitor is sent to the
     * same page a successful submission reaches. Nothing is stored and the
     * response gives away that the hidden field was noticed.
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            redirect()->route('spaces.public.thanks', ['space' => $this->space()?->slug])
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $space = $this->space();

        if (! $space instanceof Space) {
            return [];
        }

        $rules = [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'string', 'email', 'max:255'],
            'testimonial_text' => ['required', 'string', 'max:5000'],
            'sharing_consent' => ['nullable', 'boolean'],
            self::HONEYPOT_FIELD => ['nullable', 'string', 'max:255'],
        ];

        if ($space->rating_enabled) {
            $rules['rating'] = ['required', 'integer', Rule::in([1, 2, 3, 4, 5])];
        } else {
            $rules['rating'] = ['nullable', 'integer', Rule::in([1, 2, 3, 4, 5])];
        }

        if ($space->hasFieldEnabled(SpaceFieldKey::CompanyName)) {
            $rules['company_name'] = $space->hasFieldRequired(SpaceFieldKey::CompanyName)
                ? ['required', 'string', 'max:255']
                : ['nullable', 'string', 'max:255'];
        }

        if ($space->hasFieldEnabled(SpaceFieldKey::SocialUrl)) {
            $rules['social_url'] = $space->hasFieldRequired(SpaceFieldKey::SocialUrl)
                ? ['required', 'url', 'max:255']
                : ['nullable', 'url', 'max:255'];
        }

        if ($space->hasFieldEnabled(SpaceFieldKey::ProfilePhoto)) {
            $rules['profile_photo'] = $space->hasFieldRequired(SpaceFieldKey::ProfilePhoto)
                ? ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_PHOTO_KILOBYTES]
                : ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_PHOTO_KILOBYTES];
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
            'rating.required' => 'Please choose a rating.',
            'profile_photo.image' => 'The profile photo must be an image.',
            'profile_photo.mimes' => 'The profile photo must be a JPG, PNG or WEBP image.',
            'profile_photo.max' => 'The profile photo may not be larger than 2 MB.',
        ];
    }

    /**
     * The space the testimonial is being submitted to.
     */
    public function space(): ?Space
    {
        $space = $this->route('space');

        return $space instanceof Space ? $space : null;
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
