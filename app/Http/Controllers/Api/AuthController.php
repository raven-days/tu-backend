<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'identitas' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $data['identitas'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Identitas atau kata sandi salah.'], 422);
        }

        return response()->json([
            'token' => $user->createToken('web')->plainTextToken,
            'user' => $this->tampil($user),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->tampil($request->user())]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Berhasil keluar.']);
    }

    private function tampil(User $u): array
    {
        return ['name' => $u->name, 'username' => $u->username, 'role' => $u->role];
    }
}