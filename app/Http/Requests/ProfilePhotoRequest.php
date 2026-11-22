<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a profile picture that the account holder uploads for themself.
 *
 * The picture belongs to the signed in account, so the request carries no
 * account of its own. The account is always taken from the session, which
 * means a request can never name a different account.
 */
class ProfilePhotoRequest extends FormRequest
{
    /**
     * The maximum size of an uploaded profile picture, in kilobytes.
     */
    public const MAX_PHOTO_KILOBYTES = 2048;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Only a signed in account holder may change their own picture. The
     * account is the one in the session, so there is nothing in the request
     * that could point at another account.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'profile_photo' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.self::MAX_PHOTO_KILOBYTES,
            ],
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
            'profile_photo.required' => 'Choose a picture to upload.',
            'profile_photo.image' => 'The profile photo must be an image.',
            'profile_photo.mimes' => 'The profile photo must be a JPG, PNG or WEBP image.',
            'profile_photo.max' => 'The profile photo may not be larger than 2 MB.',
        ];
    }
}
