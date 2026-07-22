<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * List users with the given role that don't already have a matching
     * profile record. Used to populate the "assign to user" picker when
     * creating a Driver or a Passenger. ?role= must be "driver" or
     * "passenger" — both are valid relationship names on User.
     */
    public function index(Request $request)
    {
        $role = $request->query('role', 'driver');

        if (! in_array($role, ['driver', 'passenger'])) {
            return response()->json(['message' => 'Invalid role filter.'], 422);
        }

        $users = User::where('role', $role)
            ->whereDoesntHave($role)
            ->paginate(15);

        return $users;
    }
}
