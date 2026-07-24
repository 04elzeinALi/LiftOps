<?php

namespace App\Http\Controllers;

use App\Models\Passenger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PassengerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'driver') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = Passenger::with('user');

        // Passengers only ever see their own profile; admins see everyone.
        if ($user->role === 'passenger') {
            $query->where('user_id', $user->id);
        }

        return $query->paginate(15);
    }

    /**
     * Store a newly created resource in storage.
     *
     * Accepts EITHER an existing user_id (an already-created user with no
     * Passenger profile yet — legacy path, rarely applicable now that
     * self-registration creates both atomically) OR an email/password to
     * create a brand new User account alongside the Passenger profile.
     * The latter is how an admin onboards a passenger manually.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'driver') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // A passenger can only ever create their own profile, regardless of
        // what user_id they send — merge it in before validation so the
        // email/password-required-without-user_id rule below doesn't wrongly
        // demand credentials for a passenger's own self-service create.
        if ($user->role === 'passenger') {
            $request->merge(['user_id' => $user->id]);
        }

        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'email' => 'nullable|required_without:user_id|email|unique:users,email',
            'password' => ['nullable', 'required_without:user_id', 'confirmed', Password::min(8)],
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'phone_number' => 'required|string',
            'status' => 'required|in:active,inactive,suspended',
        ]);

        $passenger = DB::transaction(function () use ($validated) {
            $userId = $validated['user_id'] ?? null;

            if (! $userId) {
                $newUser = User::create([
                    'name' => $validated['first_name'] . ' ' . $validated['last_name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'role' => 'passenger',
                ]);
                $userId = $newUser->id;
            }

            return Passenger::create([
                'user_id' => $userId,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone_number' => $validated['phone_number'],
                'status' => $validated['status'],
            ]);
        });

        return response()->json($passenger, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $passenger = Passenger::with('user')->findOrFail($id);

        $this->authorizeOwnership($request, $passenger);

        return $passenger;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $passenger = Passenger::findOrFail($id);

        $this->authorizeOwnership($request, $passenger);

        $validated = $request->validate([
            'user_id' => 'sometimes|required|exists:users,id',
            'first_name' => 'sometimes|required|string',
            'last_name' => 'sometimes|required|string',
            'phone_number' => 'sometimes|required|string',
            'status' => 'sometimes|required|in:active,inactive,suspended',
        ]);

        $passenger->update($validated);

        // Passenger.first_name/last_name is the source of truth for this
        // person's name, but the account (User.name, shown in headers etc.)
        // is a separate field — keep it in sync so it doesn't go stale.
        if (isset($validated['first_name']) || isset($validated['last_name'])) {
            $passenger->user->update([
                'name' => $passenger->first_name . ' ' . $passenger->last_name,
            ]);
        }

        return $passenger;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $passenger = Passenger::findOrFail($id);

        $this->authorizeOwnership($request, $passenger);

        $passenger->delete();

        return response()->json(['message' => "Passenger's data deleted successfully"]);
    }

    /**
     * Admins may act on any passenger; a passenger may only act on their
     * own record; drivers are blocked entirely from this resource.
     */
    private function authorizeOwnership(Request $request, Passenger $passenger): void
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return;
        }

        if ($user->role === 'passenger' && $passenger->user_id === $user->id) {
            return;
        }

        abort(response()->json(['message' => 'Forbidden'], 403));
    }
}
