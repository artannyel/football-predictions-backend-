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
            $credentials = env('FIREBASE_CREDENTIALS');

            if (!$credentials) {
                throw new \Exception('FIREBASE_CREDENTIALS not defined in .env');
            }

            // Se for um JSON válido (começa com {), usa direto
            if (str_starts_with(trim($credentials), '{')) {
                // Salva em um arquivo temporário porque a lib exige arquivo ou array
                // Mas a lib aceita array direto no withServiceAccount? Sim!
                $credentialsArray = json_decode($credentials, true);
                if (!$credentialsArray) {
                    throw new \Exception('Invalid JSON in FIREBASE_CREDENTIALS');
                }
                $factory = (new Factory)->withServiceAccount($credentialsArray);
            } else {
                // É um caminho de arquivo
                $credentialsPath = $credentials;
                if (!str_starts_with($credentials, '/')) {
                    $credentialsPath = base_path($credentials);
                }

                if (!file_exists($credentialsPath)) {
                     $storagePath = storage_path('app/' . $credentials);
                     if (file_exists($storagePath)) {
                         $credentialsPath = $storagePath;
                     } else {
                         throw new \Exception("Firebase credentials file not found at: $credentialsPath");
                     }
                }
                $factory = (new Factory)->withServiceAccount($credentialsPath);
            }

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
