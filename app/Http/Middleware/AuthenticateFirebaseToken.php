<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Kreait\Firebase\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Auth as LaravelAuth;

class AuthenticateFirebaseToken
{
    protected $auth;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idToken = $request->bearerToken();

        if (!$idToken) {
            return response()->json(['message' => 'Unauthorized: No token provided'], 401);
        }

        try {
            $verifiedIdToken = $this->auth->verifyIdToken($idToken);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Unauthorized: Invalid token', 'error' => $e->getMessage()], 401);
        }

        $uid = $verifiedIdToken->claims()->get('sub');
        $email = $verifiedIdToken->claims()->get('email');
        $name = $verifiedIdToken->claims()->get('name');

        // Find or create user in your local database
        $user = User::firstOrCreate(
            ['firebase_uid' => $uid],
            ['email' => $email, 'name' => $name ?? explode('@', $email)[0]] // Use email part as name if not provided by Firebase
        );

        // If the user was just created and email is not verified, you might want to handle it
        if ($user->wasRecentlyCreated && !$user->email_verified_at) {
            $user->email_verified_at = now(); // Mark as verified if Firebase already verified it
            $user->save();
        }

        // Log in the user in Laravel
        LaravelAuth::login($user);

        return $next($request);
    }
}
