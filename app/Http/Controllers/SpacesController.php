<?php

namespace App\Http\Controllers;

use App\Enums\SpaceTheme;
use App\Http\Requests\StoreSpaceRequest;
use App\Http\Requests\UpdateSpaceFieldsRequest;
use App\Http\Requests\UpdateSpaceRequest;
use App\Models\Space;
use App\Spaces\SpaceFieldSeeder;
use App\Spaces\SpaceSlugGenerator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Space management for the signed in account.
 *
 * Every action is scoped to the spaces the account owns. Ownership is
 * enforced by the SpacePolicy through the form requests, and the create
 * action additionally enforces the plan's space quota on the server.
 */
class SpacesController extends Controller
{
    /**
     * Show the spaces owned by the authenticated account.
     *
     * Only the account's own spaces are listed. No customer data is exposed
     * here, because a space holds no customer records of its own.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $spaces = $user->spaces()
            ->withCount('testimonials')
            ->orderByDesc('id')
            ->get();

        return view('spaces', [
            'user' => $user,
            'spaces' => $spaces,
            'spacesCount' => $spaces->count(),
            'maxSpaces' => $user->maxSpaces(),
            'spacesRemaining' => $user->spaceQuotaRemaining(),
        ]);
    }

    /**
     * Show the form for creating a space.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->canCreateSpace()) {
            return redirect()
                ->route('spaces.index')
                ->with('error', 'You have reached the number of spaces included in your plan. Upgrade your plan to create another space.');
        }

        return view('spaces.create', [
            'user' => $user,
            'themes' => SpaceTheme::cases(),
        ]);
    }

    /**
     * Store a newly created space.
     */
    public function store(StoreSpaceRequest $request, SpaceSlugGenerator $slugs, SpaceFieldSeeder $fieldSeeder): RedirectResponse
    {
        $user = $request->user();

        // The quota is enforced again here. The form request performs the
        // policy pre-check, but the authoritative limit is applied at the
        // point the space is created so that the rule cannot be skipped.
        if (! $user->canCreateSpace()) {
            return redirect()
                ->route('spaces.index')
                ->with('error', 'You have reached the number of spaces included in your plan. Upgrade your plan to create another space.');
        }

        $validated = $request->validated();

        $space = DB::transaction(function () use ($user, $validated, $slugs, $fieldSeeder): Space {
            $slug = $validated['slug'] ?? null;

            $space = $user->spaces()->create([
                'name' => $validated['name'],
                'slug' => $slug !== null && $slug !== ''
                    ? $slug
                    : $slugs->unique($validated['name']),
                'title' => $validated['title'],
                'subtitle' => $validated['subtitle'] ?? null,
                'ask' => $validated['ask'],
                'theme' => $validated['theme'],
                'rating_enabled' => (bool) $validated['rating_enabled'],
            ]);

            $fieldSeeder->seed($space);

            return $space;
        });

        return redirect()
            ->route('spaces.created', ['space' => $space]);
    }

    /**
     * Show the form for editing a space.
     */
    public function edit(Request $request, Space $space): View
    {
        $this->authorizeSpace($request, $space);

        return view('spaces.edit', [
            'space' => $space,
            'themes' => SpaceTheme::cases(),
        ]);
    }

    /**
     * Update a space.
     */
    public function update(UpdateSpaceRequest $request, Space $space, SpaceSlugGenerator $slugs): RedirectResponse
    {
        $validated = $request->validated();

        $slug = $validated['slug'] ?? null;

        $space->update([
            'name' => $validated['name'],
            'slug' => $slug !== null && $slug !== ''
                ? $slug
                : $slugs->unique($validated['name'], $space),
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'] ?? null,
            'ask' => $validated['ask'],
            'theme' => $validated['theme'],
            'rating_enabled' => (bool) $validated['rating_enabled'],
        ]);

        return redirect()
            ->route('spaces.index')
            ->with('status', 'space-updated');
    }

    /**
     * Delete a space.
     *
     * The space is soft deleted so that the testimonials it collected are
     * never destroyed by a deletion.
     */
    public function destroy(Request $request, Space $space): RedirectResponse
    {
        $this->authorizeSpace($request, $space, 'delete');

        $space->delete();

        return redirect()
            ->route('spaces.index')
            ->with('status', 'space-deleted');
    }

    /**
     * Show the configurable collection fields of a space.
     */
    public function fields(Request $request, Space $space): View
    {
        $this->authorizeSpace($request, $space, 'manageFields');

        return view('spaces.fields', [
            'space' => $space,
            'fields' => $space->fields()->orderBy('sort_order')->get()->keyBy('field_key'),
        ]);
    }

    /**
     * Update the configurable collection fields of a space.
     */
    public function updateFields(UpdateSpaceFieldsRequest $request, Space $space): RedirectResponse
    {
        $configured = $request->configuredFields();

        DB::transaction(function () use ($space, $configured): void {
            foreach ($configured as $fieldKey => $settings) {
                $space->fields()
                    ->where('field_key', $fieldKey)
                    ->update([
                        'enabled' => $settings['enabled'],
                        'required' => $settings['required'],
                    ]);
            }
        });

        return redirect()
            ->route('spaces.index')
            ->with('status', 'fields-updated');
    }

    /**
     * Show the confirmation screen shown after a space has been created.
     */
    public function created(Request $request, Space $space): View
    {
        $this->authorizeSpace($request, $space);

        return view('spaces.created', [
            'space' => $space,
        ]);
    }

    /**
     * Ensure the account owns the space.
     *
     * @throws AuthorizationException
     */
    private function authorizeSpace(Request $request, Space $space, string $ability = 'view'): void
    {
        abort_unless($request->user()?->can($ability, $space), 403);
    }
}
