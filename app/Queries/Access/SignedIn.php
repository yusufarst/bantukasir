<?php

namespace App\Queries\Access;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Role;
use App\DTO\Access\SignedInData;
use App\Http\Auth\SignInSession;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * The shared props of a signed-in page (SECURITY_MODEL SEC-CH-02), from the
 * account of the sign-in session only (SEC-AZ-05) and the session's two
 * times; null without them, so that a request outside a sign-in session
 * shares nothing of anyone.
 */
final class SignedIn
{
    public function __construct(private readonly SignInSession $signInSession) {}

    public function __invoke(?User $user, ?Session $session): ?SignedInData
    {
        if ($user === null || $session === null) {
            return null;
        }

        $remaining = $this->signInSession->remaining($session);

        if ($remaining === null) {
            return null;
        }

        $roles = $user->roles();

        return new SignedInData(
            id: $user->id,
            displayName: $user->display_name,
            roles: array_map(static fn (Role $role): string => $role->value, $roles),
            capabilities: Capabilities::flags($roles),
            idleLimit: SignInSession::IDLE_SECONDS,
            idleEndsIn: $remaining['idleEndsIn'],
            absoluteEndsAt: $remaining['absoluteEndsAt']->format('Y-m-d\TH:i:s\Z'),
            absoluteEndsIn: $remaining['absoluteEndsIn'],
        );
    }
}
