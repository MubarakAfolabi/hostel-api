<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Admin;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'id' => 'required|string',
            'password' => 'required',
        ]);

        // Find the profile by admin_id or student_id, then get its user
        $profile = Admin::with('user')->find($request->id)
            ?? Student::with('user')->find($request->id);

        $user = $profile?->user;

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return response()->json([
            'token' => $user->createToken('web')->plainTextToken,
            'user'  => $user,
        ]);
    }

    public function me(Request $request)
    {
        return $request->user()->load(['student', 'admin']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
