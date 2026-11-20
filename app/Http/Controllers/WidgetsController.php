<?php

namespace App\Http\Controllers;

use App\Enums\WidgetLayout;
use App\Enums\WidgetThemeMode;
use App\Http\Requests\StoreWidgetRequest;
use App\Http\Requests\UpdateWidgetRequest;
use App\Models\Space;
use App\Models\Widget;
use App\Widgets\EmbedRenderer;
use App\Widgets\WidgetSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The embeddable widgets of the authenticated account.
 *
 * Every widget belongs to a space the account owns. The ownership is
 * enforced by the WidgetPolicy through the form requests, and every
 * mutation repeats the check at the point the change is made so that a
 * direct call cannot bypass it.
 */
class WidgetsController extends Controller
{
    /**
     * Show the widgets created for the account's spaces.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $widgets = Widget::query()
            ->whereIn('space_id', $user->spaces()->select('spaces.id'))
            ->with('space:id,name,slug')
            ->orderByDesc('id')
            ->get();

        return view('widgets', [
            'user' => $user,
            'widgets' => $widgets,
            'widgetsCount' => $widgets->count(),
        ]);
    }

    /**
     * Show the form for creating a widget.
     */
    public function create(Request $request): View
    {
        $user = $request->user();

        return view('widgets.create', [
            'user' => $user,
            'spaces' => $user->spaces()->orderBy('name')->get(),
            'layouts' => WidgetLayout::cases(),
            'themeModes' => WidgetThemeMode::cases(),
            'backgroundColors' => WidgetSettings::BACKGROUND_COLORS,
            'displayLimitMin' => WidgetSettings::DISPLAY_LIMIT_MIN,
            'displayLimitMax' => WidgetSettings::DISPLAY_LIMIT_MAX,
        ]);
    }

    /**
     * Store a newly created widget.
     */
    public function store(StoreWidgetRequest $request): RedirectResponse
    {
        $space = $request->space();

        if (! $space instanceof Space) {
            return redirect()
                ->route('widgets.index')
                ->with('error', 'Please choose one of your own spaces.');
        }

        // The ownership is checked again here. The form request performs the
        // policy pre-check, but the authoritative check happens at the point
        // the widget is created so that the rule cannot be skipped.
        abort_unless($request->user()?->can('manageWidget', $space), 403);

        $settings = WidgetSettings::fromArray($request->validated());

        $widget = $space->widgets()->create([
            ...$settings->toArray(),
        ]);

        $widget->forceFill(['public_id' => Widget::generatePublicId()])->save();

        return redirect()
            ->route('widgets.edit', ['widget' => $widget])
            ->with('status', 'widget-created');
    }

    /**
     * Show the form for editing a widget.
     */
    public function edit(Request $request, Widget $widget): View
    {
        $this->authorizeWidget($request, $widget, 'update');

        $widget->load('space:id,name,slug');

        return view('widgets.edit', [
            'user' => $request->user(),
            'widget' => $widget,
            'layouts' => WidgetLayout::cases(),
            'themeModes' => WidgetThemeMode::cases(),
            'backgroundColors' => WidgetSettings::BACKGROUND_COLORS,
            'displayLimitMin' => WidgetSettings::DISPLAY_LIMIT_MIN,
            'displayLimitMax' => WidgetSettings::DISPLAY_LIMIT_MAX,
        ]);
    }

    /**
     * Update a widget.
     */
    public function update(UpdateWidgetRequest $request, Widget $widget): RedirectResponse
    {
        $this->authorizeWidget($request, $widget, 'update');

        $settings = WidgetSettings::fromArray($request->validated());

        $widget->update($settings->toArray());

        return redirect()
            ->route('widgets.edit', ['widget' => $widget])
            ->with('status', 'widget-updated');
    }

    /**
     * Delete a widget.
     *
     * The widget is soft deleted so that its configuration is never
     * destroyed by a deletion. The widget disappears from the list and its
     * embed stops rendering.
     */
    public function destroy(Request $request, Widget $widget): RedirectResponse
    {
        $this->authorizeWidget($request, $widget, 'delete');

        $widget->delete();

        return redirect()
            ->route('widgets.index')
            ->with('status', 'widget-deleted');
    }

    /**
     * Show a live preview of a widget.
     *
     * The preview renders the same partial as the public embed, so what the
     * owner sees in the editor is what the embed shows on their website.
     */
    public function preview(Request $request, Widget $widget, EmbedRenderer $renderer): View
    {
        $this->authorizeWidget($request, $widget, 'view');

        $widget->load('space:id,name,slug');

        return view('widgets.preview', [
            'widget' => $widget,
            'testimonials' => $renderer->testimonials($widget),
        ]);
    }

    /**
     * Ensure the account owns the widget.
     *
     * @throws AuthorizationException
     */
    private function authorizeWidget(Request $request, Widget $widget, string $ability): void
    {
        abort_unless($request->user()?->can($ability, $widget), 403);
    }
}
