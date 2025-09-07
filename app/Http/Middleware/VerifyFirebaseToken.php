<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerifyFirebaseToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): BaseResponse
    {
        $token = $this->extractToken($request);

        if (! $token) {
            return response()->json(['error' => 'No token provided'], 401);
        }

        try {
            $decoded = $this->verifyToken($token);
            $request->attributes->set('firebase_user', $decoded);

            return $next($request);
        } catch (\Exception $e) {
            Log::warning('Firebase token verification failed', [
                'token' => substr($token, 0, 20).'...',
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid token'], 401);
        }
    }

    /**
     * Extract token from request
     */
    private function extractToken(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');

        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        return substr($authHeader, 7);
    }

    /**
     * Verify Firebase JWT token
     */
    private function verifyToken(string $token): object
    {
        $publicKeys = $this->getFirebasePublicKeys();
        $header = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $token)[0]))), true);

        if (! isset($header['kid'])) {
            throw new \Exception('Token header missing kid');
        }

        $kid = $header['kid'];

        if (! isset($publicKeys[$kid])) {
            throw new \Exception('Invalid token kid');
        }

        $publicKey = $publicKeys[$kid];

        return JWT::decode($token, new Key($publicKey, 'RS256'));
    }

    /**
     * Get Firebase public keys (cached for 1 hour)
     */
    private function getFirebasePublicKeys(): array
    {
        return Cache::remember('firebase_public_keys', 3600, function () {
            $response = Http::get('https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com');

            if ($response->failed()) {
                throw new \Exception('Failed to fetch Firebase public keys');
            }

            return $response->json();
        });
    }
}
