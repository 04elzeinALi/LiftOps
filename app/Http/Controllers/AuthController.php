<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Passenger;
use App\Models\User;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Public passenger self-registration. Creates a User AND its linked
     * Passenger profile in one transaction, then logs them straight in.
     *
     * The role is forced to 'passenger' server-side and never taken from
     * the request — admins and drivers are created by an admin, not
     * self-registered. (This closes a privilege-escalation hole where the
     * endpoint used to trust a client-sent `role`.)
     */
    public function register(Request $request)
{
    $validated = $request->validate([
        'first_name' => 'required|string',
        'last_name' => 'required|string',
        'email' => 'required|email|unique:users',
        'phone_number' => 'required|string',
        'password' => 'required|confirmed',
    ]);

    $user = DB::transaction(function () use ($validated) {
        $user = User::create([
            'name' => $validated['first_name'] . ' ' . $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'passenger',
        ]);

        Passenger::create([
            'user_id' => $user->id,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone_number' => $validated['phone_number'],
            'status' => 'active',
        ]);

        return $user;
    });

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'Registration successful.',
        'data' => [
            'user' => $user,
            'token' => $token,
        ],
    ], 201);
}

public function login(Request $request)
{
    $validated = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (! Auth::attempt($validated)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

     $user = Auth::user();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'data'    => [
                'user'  => $user,
                'token' => $token,
            ]
        ]);
}
public function logout(Request $request)
{
    $request->user()->currentAccessToken()->delete();

    return response()->json(['message' => 'Logged out successfully']);
}


}


