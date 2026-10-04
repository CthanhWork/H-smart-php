<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\AccountToken;
use App\Modules\User\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Throwable;

class TokenService
{
    private function jwtConfig(): Configuration
    {
        $secret = (string) config('auth_tokens.jwt_secret');
        if (strlen($secret) < 32) {
            throw new \RuntimeException('JWT_SECRET must contain at least 32 bytes.');
        }

        return Configuration::forSymmetricSigner(new Sha256, InMemory::plainText($secret));
    }

    public function accessToken(User $user): string
    {
        $config = $this->jwtConfig();
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $config->builder()
            ->issuedBy((string) config('auth_tokens.issuer'))
            ->permittedFor((string) config('auth_tokens.audience'))
            ->relatedTo((string) $user->id)
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify('+15 minutes'))
            ->withClaim('type', 'access')
            ->withClaim('role', $user->role)
            ->withClaim('auth_version', (int) $user->auth_version)
            ->getToken($config->signer(), $config->signingKey())
            ->toString();
    }

    /** @return array{id: int, version: int}|null */
    public function verifyAccessToken(string $raw): ?array
    {
        try {
            $config = $this->jwtConfig();
            $token = $config->parser()->parse($raw);
            if (! $token instanceof Plain || ! $config->validator()->validate(
                $token,
                new SignedWith($config->signer(), $config->verificationKey()),
                new StrictValidAt(SystemClock::fromUTC()),
                new IssuedBy((string) config('auth_tokens.issuer')),
                new PermittedFor((string) config('auth_tokens.audience')),
            )) {
                return null;
            }

            $id = $token->claims()->get('sub');

            $version = $token->claims()->get('auth_version');

            return $token->claims()->get('type') === 'access' && ctype_digit((string) $id) && is_int($version) && $version >= 0
                ? ['id' => (int) $id, 'version' => $version]
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function opaqueToken(int $userId, string $type, int $minutes): string
    {
        $raw = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        AccountToken::create([
            'user_id' => $userId,
            'type' => $type,
            'token_hash' => hash('sha256', $raw),
            'expires_at' => now()->addMinutes($minutes),
        ]);

        return $raw;
    }
}
