<?php

namespace App\Http\Requests;

use App\Models\Testimonial;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a moderation action on a testimonial.
 *
 * The moderation actions carry no input of their own. The request exists so
 * that the ownership check is performed by the same mechanism as every
 * other write, and so that a guest or a foreign account can never reach the
 * controller action.
 */
class ModerateTestimonialRequest extends FormRequest
{
    /**
     * The ability the policy is checked against.
     *
     * The route name carries the action, so the request derives the ability
     * from the route instead of each action declaring it separately.
     */
    private const ABILITIES = [
        'testimonials.approve' => 'approve',
        'testimonials.hide' => 'hide',
        'testimonials.favorite' => 'favorite',
        'testimonials.wall-of-love' => 'wallOfLove',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $testimonial = $this->route('testimonial');

        if (! $testimonial instanceof Testimonial) {
            return false;
        }

        $ability = $this->ability();

        if ($ability === null) {
            return false;
        }

        return $this->user()?->can($ability, $testimonial) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * The policy ability that this request checks.
     */
    public function ability(): ?string
    {
        $routeName = $this->route()?->getName();

        if (! is_string($routeName)) {
            return null;
        }

        return self::ABILITIES[$routeName] ?? null;
    }
}
