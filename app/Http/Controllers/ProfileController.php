<?php

namespace App\Http\Controllers;

use App\Enums\CountryEnum;
use App\Enums\StateEnum;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = User::find(auth()->id());

        return inertia('Profile/Edit', [
            'profile' => ProfileResource::make($user),
            'states' => StateEnum::resolveDisplayableValueList(),
            'countries' => CountryEnum::resolveDisplayableValueList(),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:250'],
            'email' => ['required', 'string', 'email', 'max:250', Rule::unique('users')->ignore(auth()->id())],
            'address_line_one' => ['required', 'string', 'max:250'],
            'address_line_two' => ['nullable', 'string', 'max:250'],
            'phone' => ['required', 'string', 'max:15'],
            'city' => ['required', 'string', 'max:250'],
            'postal_code' => ['required', 'string', 'max:6', 'min:6'],
            'state' => ['required', Rule::enum(StateEnum::class)],
            'country' => ['required', Rule::enum(CountryEnum::class)],
        ]);

        $user = User::find(auth()->id());

        $user->name = $request->name;
        $user->email = $request->email;
        $user->address_line_one = $request->address_line_one;
        $user->address_line_two = $request->address_line_two;
        $user->phone = $request->phone;
        $user->city = $request->city;
        $user->postal_code = $request->postal_code;
        $user->state = $request->state;
        $user->country = $request->country;
        $user->save();

        return redirect()->to('/profile')->with(['success' => 'Profile updated!']);
    }
}
