<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfilePhotoRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Show the profile page.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile details.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->validated());

        $user->save();

        return redirect()
            ->route('profile.edit')
            ->with('status', 'profile-updated');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->password = $request->string('password')->toString();

        $user->save();

        return redirect()
            ->route('profile.edit')
            ->with('status', 'password-updated');
    }

    /**
     * Store a new profile picture for the signed in account.
     *
     * The picture is stored on the public disk under a name that is made up
     * at random, so the name that was sent with the upload never becomes
     * part of the address of the file.
     *
     * The old picture is only removed once the new one is safely stored and
     * the account points at it. A failure halfway through therefore leaves
     * the account with the picture it already had.
     */
    public function updatePhoto(ProfilePhotoRequest $request): RedirectResponse
    {
        $user = $request->user();

        $previousPath = $user->profile_photo_path;

        $path = $request->file('profile_photo')->store(
            User::PROFILE_PHOTO_DIRECTORY,
            'public'
        );

        // The path is written explicitly rather than filled in from the
        // request, so a request can never point the account at a file it
        // did not upload.
        $user->forceFill(['profile_photo_path' => $path])->save();

        if (is_string($previousPath) && $previousPath !== '' && $previousPath !== $path) {
            Storage::disk('public')->delete($previousPath);
        }

        return redirect()
            ->route('profile.edit')
            ->with('status', 'profile-photo-updated');
    }

    /**
     * Remove the profile picture of the signed in account.
     *
     * The account is pointed at no picture first, so the dashboard shows the
     * initial of the name again even if the file itself cannot be removed.
     */
    public function destroyPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();

        $path = $user->profile_photo_path;

        $user->forceFill(['profile_photo_path' => null])->save();

        if (is_string($path) && $path !== '') {
            Storage::disk('public')->delete($path);
        }

        return redirect()
            ->route('profile.edit')
            ->with('status', 'profile-photo-removed');
    }
}
