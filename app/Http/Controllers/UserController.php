<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * List users with role=driver that don't already have a Driver profile.
     * Used to populate the "assign to user" picker when creating a Driver.
     */
    public function index(Request $request)
    {
        $users = User::where('role', 'driver')
            ->whereDoesntHave('driver')
            ->paginate(15);

        return $users;
    }
}
