<?php

namespace App\Providers;

use App\Actions\Access\UpdateOwnPassword;
use App\Domain\Access\Accounts;
use App\Http\Auth\AuthenticateUser;
use App\Http\Auth\Limits;
use App\Http\Auth\Responses;
use App\Http\Auth\StartSignInSession;
use App\Http\Requests\Access\SignInRequest;
use App\Models\User;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Contracts\FailedPasswordConfirmationResponse;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\PasswordConfirmedResponse;
use Laravel\Fortify\Contracts\PasswordUpdateResponse;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest;

/**
 * The authentication boundary of the Access module on Laravel Fortify
 * (ADR-004; SECURITY_MODEL section 2; ROUTE_CONTRACTS section 4; TASK-006),
 * registered in bootstrap/providers.php:
 *
 * - Fortify's own routes are not loaded; routes/web.php registers RA-01 to
 *   RA-04 on its controllers and RA-05 on the application's
 *   OwnPasswordController, which runs Fortify's updater (config/fortify.php);
 * - the sign-in pipeline checks the request (SignInRequest), the credentials
 *   (AuthenticateUser), regenerates the session (Fortify's
 *   PrepareAuthenticatedSession) and starts the sign-in session's limits
 *   (StartSignInSession); "remember me" never exists;
 * - a password confirmation checks the signed-in, ACTIVE account's password;
 * - the own password change is UpdateOwnPassword;
 * - the answers of section 4 (App\Http\Auth\Responses) and the rate buckets
 *   signin, link and confirm (App\Http\Auth\Limits).
 */
final class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();

        $this->app->bind(LoginRequest::class, SignInRequest::class);
        $this->app->bind(UpdatesUserPasswords::class, UpdateOwnPassword::class);

        $this->app->singleton(LoginResponse::class, static fn (): Responses => Responses::signedIn());
        $this->app->singleton(LogoutResponse::class, static fn (): Responses => Responses::signedOut());
        $this->app->singleton(PasswordUpdateResponse::class, static fn (): Responses => Responses::passwordChanged());
        $this->app->singleton(PasswordConfirmedResponse::class, static fn (): Responses => Responses::confirmed());
        $this->app->singleton(FailedPasswordConfirmationResponse::class, static fn (): Responses => Responses::confirmationFailed());
    }

    public function boot(): void
    {
        Fortify::authenticateThrough(static fn (): array => [
            AuthenticateUser::class,
            PrepareAuthenticatedSession::class,
            StartSignInSession::class,
        ]);

        Fortify::confirmPasswordsUsing(function (mixed $user, ?string $password): bool {
            return $user instanceof User && $user->isActive() && $this->app->make(Accounts::class)->passwordMatches($user, $password ?? '');
        });

        Limits::register();
    }
}
