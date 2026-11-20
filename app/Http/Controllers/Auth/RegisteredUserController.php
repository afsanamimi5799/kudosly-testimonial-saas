<?php

namespace App\Http\Controllers\Auth;

use App\Billing\PlanType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration form.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * The user is instantiated with the fillable attributes only. The plan is
     * assigned explicitly from the enum so that it can never be influenced by
     * request input.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = new User();

        $user->name = $request->string('name')->toString();
        $user->email = $request->string('email')->toString();
        $user->password = $request->string('password')->toString();
        $user->plan_slug = PlanType::Free->value;

        $user->save();

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
