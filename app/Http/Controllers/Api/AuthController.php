<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginUserRequest;

use App\Models\User;
use App\Trait\HttpResponses;
use Illuminate\Http\JsonResponse;

use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;


class AuthController extends Controller
{
    use HttpResponses;

    public function login(LoginUserRequest $request): JsonResponse
    {
        $request->validated($request->only(['email', 'password']));

        if(!Auth::attempt($request->only(['email', 'password']))) {
            return $this->error('', Response::HTTP_UNAUTHORIZED,'Credentials do not match');
        }

        $user = User::where('email', $request->email)->first();

        return $this->success([
            'user' => $user,
            'token' => $user->createToken('API Token')->plainTextToken
        ]);
    }



    public function logout(): JsonResponse
    {
        Auth::user()->currentAccessToken()->delete();

        return $this->success([
            'message' => 'You have successfully been logged out and your token has been removed'
        ]);
    }
}
