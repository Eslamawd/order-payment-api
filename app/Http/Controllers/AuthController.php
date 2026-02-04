<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\UserResource;

class AuthController extends Controller
{
   public function register(RegisterRequest $request)
{
    // 1. إنشاء المستخدم
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
    ]);

    // 2. محاولة تسجيل الدخول للحصول على التوكن
    // تأكد أن الـ Guard 'api' مضبوط صح في config/auth.php
    $credentials = $request->only('email', 'password');
    
    if (! $token = auth('api')->attempt($credentials)) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    // 3. الرد الناجح
    return response()->json([
        'status' => 'success',
        'message' => 'User registered successfully',
        'user' => new UserResource($user),
        'authorization' => [
            'token' => $token,
            'type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]
    ], 201);
}
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        return response()->json([
            'status' => 'success',
            'user' => new UserResource(auth('api')->user()),
            'authorization' => [
                'token' => $token,
                'type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60
            ]
        ]);
    }

    public function logout()
    {
        auth('api')->logout();

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out'
        ]);
    }

    public function refresh()
    {
        return response()->json([
            'status' => 'success',
            'user' => new UserResource(auth('api')->user()),
            'authorization' => [
                'token' => auth('api')->refresh(),
                'type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60
            ]
        ]);
    }

    public function me()
    {
        return response()->json([
            'status' => 'success',
            'user' => new UserResource(auth('api')->user())
        ]);
    }
}