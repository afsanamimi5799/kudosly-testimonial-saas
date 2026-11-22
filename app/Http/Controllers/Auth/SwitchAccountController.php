<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Signs the current account out so that another one may be used.
 *
 * Switching accounts is a full sign out followed by a sign in. The session
 * is ended in exactly the same way an ordinary sign out ends it, so there
 * is nothing left of the previous account behind. The visitor lands on the
 * sign in page, where another account may be used or a new one may be
 * created.
 *
 * Only one account is signed in at a time. The account that is signed out
 * cannot be reached through the session afterwards, and the data of the two
 * accounts is never mixed.
 */
class SwitchAccountController extends Controller
{
    use SignsOutTheAccount;

    /**
     * End the session of the current account.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return $this->signOut($request, route('login'));
    }
}
