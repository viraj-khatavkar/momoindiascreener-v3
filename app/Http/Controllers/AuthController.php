<?php

namespace App\Http\Controllers;

use App\Mail\ForgotPassword;
use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function loginView()
    {
        return inertia('Auth/Login', [
            'pageHeader' => 'Sign in to your account',
        ]);
    }

    public function loginPost(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'max:250'],
            'password' => ['required', 'min:8'],
        ]);

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials, $request->has('remember_me'))) {
            $request->session()->regenerate();

            return redirect()->intended('/');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    public function registerView()
    {
        return inertia('Auth/Register');
    }

    public function registerPost(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:250'],
            'email' => ['required', 'email', 'max:250', 'unique:users'],
            'password' => ['required', 'min:8'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user, true);

        return redirect()->intended('/profile');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to('/login');
    }

    public function forgotPassword()
    {
        return inertia('Auth/ForgotPassword');
    }

    public function resetLink()
    {
        request()->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', request('email'))->first();

        if (! $user) {
            return redirect('forgot-password')->with('success', 'Password Reset Link Sent!');
        }

        $passwordResetToken = PasswordResetToken::where('email', request('email'))->first();

        if ($passwordResetToken) {
            DB::table('password_reset_tokens')->where('email', request('email'))->delete();
        }

        $passwordResetToken = PasswordResetToken::create([
            'email' => request('email'),
            'token' => Str::random(60),
            'created_at' => Date::now(),
        ]);

        Mail::to(request('email'))->send(new ForgotPassword($passwordResetToken->token, $user));

        return redirect('forgot-password')->with('success', 'Password Reset Link Sent!');
    }

    public function resetPassword()
    {
        $token = request('token');

        $token = PasswordResetToken::where('token', $token)->first();

        if (! $token || Date::parse($token->created_at)->addMinutes(60)->isPast()) {
            return redirect('forgot-password');
        }

        return inertia('Auth/ResetPassword', ['token' => $token->token]);
    }

    public function resetPasswordStore(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'password' => ['required', 'min:8'],
        ]);

        $token = PasswordResetToken::where('token', $request->get('token'))->first();

        $user = User::where('email', $token->email)->first();

        if (! $user) {
            return redirect('forgot-password');
        }

        $user->password = Hash::make($request->get('password'));
        $user->save();

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect('login')->with('success', 'New password set successfully!');
    }
}
