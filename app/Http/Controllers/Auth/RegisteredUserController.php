<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Handle a registration request for the application.
     */
    public function store(Request $request)
    {
        //  التحقق من جميع الحقول الجديدة
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone_number' => 'required|string|max:20|unique:users',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:male,female,other',
            'profile_picture_url' => 'nullable|url',
            'type' => 'required|in:sender,traveler',
        ]);

        //  حفظ المستخدم
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'name' => $validated['first_name'] . ' ' . $validated['last_name'], // ندمجهم في حقل name القديم
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone_number' => $validated['phone_number'],
            'date_of_birth' => $validated['date_of_birth'],
            'gender' => $validated['gender'],
            'profile_picture_url' => $validated['profile_picture_url'] ?? null,
            'type' => $validated['type'],
            'account_status' => 'pending_verification', // افتراضي
            'email_verified' => false,
            'phone_verified' => false,
            'identity_verified' => false,
        ]);

        //  إنشاء توكن
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'تم إنشاء المستخدم بنجاح',
            'user' => $user,
            'token' => $token
        ], 201);
    }
}