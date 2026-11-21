<?php

namespace App\Http\Controllers\PublicCollection;

use App\Enums\SpaceFieldKey;
use App\Enums\TestimonialStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitTestimonialRequest;
use App\Models\Space;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The public testimonial collection page.
 *
 * These actions are reachable without a session. A visitor submits a
 * testimonial to a space, and the testimonial is stored as pending for the
 * owner to moderate.
 *
 * The submission route is limited by the throttle middleware, which counts
 * every attempt against the address the request came from. The controller
 * therefore does not count attempts itself.
 */
class SpaceCollectionController extends Controller
{
    /**
     * Show the collection page for a space.
     */
    public function show(Space $space): View
    {
        $space->load('fields');

        return view('public.collection.show', [
            'space' => $space,
            'fields' => $space->enabledFields(),
        ]);
    }

    /**
     * Store a testimonial submitted to a space.
     */
    public function store(SubmitTestimonialRequest $request, Space $space): RedirectResponse
    {
        // The plan limits how many testimonials a space may collect. The
        // check happens before the submission is stored so that a customer
        // is never told their testimonial was received when it was not.
        if ($space->hasReachedTestimonialLimit()) {
            return redirect()
                ->route('spaces.public.show', ['space' => $space->slug])
                ->withErrors([
                    'error' => 'This space is not accepting new testimonials right now.',
                ]);
        }

        $validated = $request->validated();

        $photoPath = null;

        if ($request->hasFile('profile_photo')) {
            $photoPath = $request->file('profile_photo')->store('profile-photos', 'public');
        }

        DB::transaction(function () use ($space, $validated, $photoPath): void {
            $space->testimonials()->create([
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'company_name' => $this->optionalValue($space, SpaceFieldKey::CompanyName, $validated),
                'social_url' => $this->optionalValue($space, SpaceFieldKey::SocialUrl, $validated),
                'profile_photo_path' => $photoPath,
                'testimonial_text' => $validated['testimonial_text'],
                'rating' => $space->rating_enabled ? ($validated['rating'] ?? null) : null,
                'sharing_consent' => (bool) ($validated['sharing_consent'] ?? false),
                'status' => TestimonialStatus::Pending,
            ]);
        });

        return redirect()
            ->route('spaces.public.thanks', ['space' => $space->slug]);
    }

    /**
     * Show the confirmation screen shown after a submission.
     */
    public function thanks(Space $space): View
    {
        return view('public.collection.thanks', [
            'space' => $space,
        ]);
    }

    /**
     * The value submitted for an optional field, when the space collects it.
     *
     * A field that the space does not collect is never stored, so a
     * submission cannot smuggle a value into a column that is switched off.
     */
    private function optionalValue(Space $space, SpaceFieldKey $key, array $validated): ?string
    {
        if (! $space->hasFieldEnabled($key)) {
            return null;
        }

        $value = $validated[$key->value] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
