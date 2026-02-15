<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ChangePasswordController extends Controller
{
    public function edit()
    {
        return inertia('ChangePassword');
    }

    public function update(Request $request)
    {
        $request->validate([
            'old_password' => ['required', 'current_password'],
            'new_password' => ['required', 'min:8'],
        ]);

        $request->user()->update([
            'password' => Hash::make(request('new_password')),
        ]);

        return redirect()->to('/change-password')->with('success', 'Password changed successfully');
    }
}
