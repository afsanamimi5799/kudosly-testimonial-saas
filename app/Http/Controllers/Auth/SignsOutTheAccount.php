<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Ends the session of the signed in account.
 *
 * Signing out and switching accounts are the same act with a different
 * destination. Both go through this one place, so the two cannot drift
 * apart and the session is always ended in the same way.
 */
trait SignsOutTheAccount
{
    /**
     * Sign the current account out.
     *
     * The account is forgotten by the guard, the session is emptied so that
     * nothing of it is left behind, and the token that protects the forms
     * is replaced. The visitor is then sent to the page named by the
     * argument.
     */
    protected function signOut(Request $request, string $redirectTo): mixed
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect($redirectTo);
    }
}
