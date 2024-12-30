<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->symbols()->numbers()->mixedCase()],
        ])->setAttributeNames([
            'current_password' => trans('public.current_password'),
            'password' => trans('public.password'),
        ]);
        $validator->validate();

        $request->user()->update([
            'password' => Hash::make($validator['password']),
        ]);

        return back()->with('toast', [
            'title' => trans("public.toast_reset_password_success"),
            'type' => 'success',
        ]);
    }
}
