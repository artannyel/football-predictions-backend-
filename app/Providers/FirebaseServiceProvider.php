<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;

class FirebaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(Auth::class, function ($app) {
            $envPath = env('FIREBASE_CREDENTIALS');

            if (!$envPath) {
                throw new \Exception('FIREBASE_CREDENTIALS not defined in .env');
            }

            // Check if path is absolute, if not, assume it's relative to base path
            $credentialsPath = $envPath;
            if (!str_starts_with($envPath, '/')) {
                $credentialsPath = base_path($envPath);
            }

            if (!file_exists($credentialsPath)) {
                 // Try storage path as a fallback if the user put it there but used a relative path
                 $storagePath = storage_path('app/' . $envPath);
                 if (file_exists($storagePath)) {
                     $credentialsPath = $storagePath;
                 } else {
                     throw new \Exception("Firebase credentials file not found at: $credentialsPath or $storagePath");
                 }
            }

            $factory = (new Factory)->withServiceAccount($credentialsPath);

            return $factory->createAuth();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
