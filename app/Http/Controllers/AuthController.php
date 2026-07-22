<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string',
        'email' => 'required|email|unique:users',
        'password' => 'required|confirmed',
        'role' => 'required|in:admin,driver,passenger',

    ]);

    $validated['password'] = Hash::make($validated['password']);
     $user = User::create($validated);
     $token = $user->createToken('auth_token')->plainTextToken;

     return response()->json([
         'user' => $user,
         'token' => $token,
         ],201);
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


