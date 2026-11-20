<?php

namespace App\Http\Controllers;

use App\Enums\TestimonialStatus;
use App\Http\Requests\ModerateTestimonialRequest;
use App\Http\Requests\UpdateTestimonialRequest;
use App\Models\Testimonial;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The moderation actions for a single testimonial.
 *
 * Every action re-checks the ownership at the point the change is made.
 * The form requests perform the policy pre-check, but the authoritative
 * check happens in the action so that a direct call cannot bypass it.
 *
 * Favourites and the wall of love are separate flags. Neither one changes
 * the moderation status, and neither one makes a testimonial public.
 */
class TestimonialModerationController extends Controller
{
    /**
     * Show the form for editing a testimonial.
     */
    public function edit(Request $request, Testimonial $testimonial): View
    {
        $this->authorizeTestimonial($request, $testimonial, 'update');

        $testimonial->load('space:id,name,slug');

        return view('inbox.edit', [
            'testimonial' => $testimonial,
        ]);
    }

    /**
     * Update a testimonial.
     */
    public function update(UpdateTestimonialRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $this->authorizeTestimonial($request, $testimonial, 'update');

        $validated = $request->validated();

        $testimonial->update([
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'],
            'company_name' => $validated['company_name'] ?? null,
            'social_url' => $validated['social_url'] ?? null,
            'testimonial_text' => $validated['testimonial_text'],
            'rating' => $validated['rating'] ?? null,
        ]);

        return redirect()
            ->route('inbox.index')
            ->with('status', 'testimonial-updated');
    }

    /**
     * Approve a testimonial so that it becomes eligible for public display.
     */
    public function approve(ModerateTestimonialRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $this->authorizeTestimonial($request, $testimonial, 'approve');

        $testimonial->update(['status' => TestimonialStatus::Approved]);

        return redirect()
            ->route('inbox.index')
            ->with('status', 'testimonial-approved');
    }

    /**
     * Hide a testimonial so that it is removed from public display.
     */
    public function hide(ModerateTestimonialRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $this->authorizeTestimonial($request, $testimonial, 'hide');

        $testimonial->update(['status' => TestimonialStatus::Hidden]);

        return redirect()
            ->route('inbox.index')
            ->with('status', 'testimonial-hidden');
    }

    /**
     * Mark a testimonial as a favourite, or clear the favourite.
     *
     * Favouriting pins the testimonial above the non favourites in the
     * inbox. It never changes the moderation status.
     */
    public function toggleFavorite(ModerateTestimonialRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $this->authorizeTestimonial($request, $testimonial, 'favorite');

        $testimonial->update([
            'is_favorite' => ! $testimonial->is_favorite,
        ]);

        return redirect()
            ->route('inbox.index')
            ->with('status', $testimonial->is_favorite ? 'testimonial-favorited' : 'testimonial-unfavorited');
    }

    /**
     * Add a testimonial to the wall of love, or remove it from the wall.
     *
     * The heart flag marks the testimonial for the owner's wall. A
     * testimonial still has to be approved and carry sharing consent
     * before it appears on the public wall.
     */
    public function toggleWallOfLove(ModerateTestimonialRequest $request, Testimonial $testimonial): RedirectResponse
    {
        $this->authorizeTestimonial($request, $testimonial, 'wallOfLove');

        $testimonial->update([
            'is_wall_of_love' => ! $testimonial->is_wall_of_love,
        ]);

        return redirect()
            ->route('inbox.index')
            ->with('status', $testimonial->is_wall_of_love ? 'testimonial-added-to-wall' : 'testimonial-removed-from-wall');
    }

    /**
     * Delete a testimonial.
     *
     * The testimonial is soft deleted. The row stays in the table with a
     * deleted_at timestamp so that the customer's data is never destroyed
     * by a deletion. The testimonial disappears from the inbox and from
     * every public page.
     *
     * The uploaded profile photo is removed from storage. The photo is a
     * copy of the customer's picture that stays publicly reachable at its
     * storage URL even when the testimonial is gone, so it must not outlive
     * the testimonial it belongs to.
     */
    public function destroy(Request $request, Testimonial $testimonial): RedirectResponse
    {
        $this->authorizeTestimonial($request, $testimonial, 'delete');

        $this->deleteProfilePhoto($testimonial);

        $testimonial->delete();

        return redirect()
            ->route('inbox.index')
            ->with('status', 'testimonial-deleted');
    }

    /**
     * Remove the uploaded profile photo from storage.
     *
     * A testimonial without a photo has no path, so nothing is removed. A
     * photo that is already gone from storage is not an error: the goal is
     * that the file does not remain, and it does not.
     */
    private function deleteProfilePhoto(Testimonial $testimonial): void
    {
        $path = $testimonial->profile_photo_path;

        if (! is_string($path) || $path === '') {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    /**
     * Ensure the account owns the testimonial.
     *
     * @throws AuthorizationException
     */
    private function authorizeTestimonial(Request $request, Testimonial $testimonial, string $ability): void
    {
        abort_unless($request->user()?->can($ability, $testimonial), 403);
    }
}
