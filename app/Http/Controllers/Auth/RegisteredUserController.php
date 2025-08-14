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
        // التحقق من جميع الحقول مع تحسين validation rules
        $validated = $request->validate([
            'first_name' => 'required|string|min:2|max:50|regex:/^[\p{L}\s]+$/u',
            'last_name' => 'required|string|min:2|max:50|regex:/^[\p{L}\s]+$/u',
            'email' => 'required|string|email:rfc,dns|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            'phone_number' => 'required|string|regex:/^[+]?[0-9]{10,15}$/|unique:users,phone_number',
            'date_of_birth' => 'required|date|before:today|after:1900-01-01',
            'gender' => 'required|in:male,female,other',
            'profile_picture_url' => 'nullable|url|max:500',
            'type' => 'required|in:sender,traveler',
        ]);

        try {
            // حفظ المستخدم
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'name' => $validated['first_name'] . ' ' . $validated['last_name'],
                'email' => strtolower($validated['email']),
                'password' => Hash::make($validated['password']),
                'phone_number' => $validated['phone_number'],
                'date_of_birth' => $validated['date_of_birth'],
                'gender' => $validated['gender'],
                'profile_picture_url' => $validated['profile_picture_url'] ?? null,
                'type' => $validated['type'],
                'account_status' => 'pending_verification',
                'email_verified' => false,
                'phone_verified' => false,
                'identity_verified' => false,
            ]);

            // إنشاء توكن
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'تم إنشاء المستخدم بنجاح',
                'user' => $user->makeHidden(['password']),
                'token' => $token
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء إنشاء الحساب',
                'error' => config('app.debug') ? $e->getMessage() : 'خطأ في الخادم'
            ], 500);
        }
    }
}