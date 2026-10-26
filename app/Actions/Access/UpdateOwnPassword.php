<?php

namespace App\Actions\Access;

use App\Database\CommandPoints;
use App\Domain\Access\Accounts;
use App\Domain\Access\PasswordProfile;
use App\Domain\Audit\Actor;
use App\Domain\Audit\AuditCategory;
use App\Domain\Audit\AuditLog;
use App\Domain\Audit\SecurityActor;
use App\Domain\Audit\SecurityEvent;
use App\Domain\Audit\SecurityLog;
use App\Domain\Audit\SecurityOutcome;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

/**
 * RA-05, the change of one's own password (ROUTE_CONTRACTS section 4;
 * INTERACTION_CONTRACTS IC-12; SECURITY_MODEL SEC-AUTH-11; TECHNICAL_RULES
 * section 7.2, "Own password change"), the updater of Fortify's
 * updatePasswords feature. It takes exactly current_password, password and
 * password_confirmation (TR-VAL-01); the new password meets AICWDF-COMPAT-8
 * (PasswordProfile). In one transaction, under the account's own update lock,
 * taken once (TR-LOCK-05), the account must still be ACTIVE and the current
 * password must match; then the password is set, the user's other sign-in
 * sessions are revoked — this one stays — and the audit event PASSWORD_CHANGE
 * is written. A wrong current password gives the one uniform answer (UXS-42,
 * TR-ERR-04) and is recorded in the security log as a password confirmation
 * failure; so, after the commit, are the sessions it ended (SEC-LOG-03).
 *
 * Write steps (TEST_STRATEGY TS-21): 1 the password, 2 the sessions, 3 the
 * audit event.
 */
final class UpdateOwnPassword implements UpdatesUserPasswords
{
    /** The fields of the request; the framework's form fields are ignored. */
    private const array FIELDS = ['current_password', 'password', 'password_confirmation'];

    private const array FRAMEWORK_FIELDS = ['_token', '_method'];

    public function __construct(
        private readonly Accounts $accounts,
        private readonly AuditLog $audit,
        private readonly SecurityLog $securityLog,
        private readonly CommandPoints $points,
        private readonly Request $request,
    ) {}

    /**
     * @param  array<array-key, mixed>  $input
     *
     * @throws ValidationException
     * @throws AuthenticationException
     */
    public function update(User $user, #[\SensitiveParameter] array $input): void
    {
        $unknown = array_diff(array_keys($input), [...self::FIELDS, ...self::FRAMEWORK_FIELDS]);

        if ($unknown !== []) {
            throw ValidationException::withMessages(['form' => __('ux.state.validation.body')])->redirectTo(route('account.password'));
        }

        $validator = Validator::make($input, [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', new PasswordProfile],
            'password_confirmation' => ['required', 'string', 'same:password'],
        ], [
            'required' => __('ux.validation.required'),
            'string' => __('ux.validation.required'),
            'password_confirmation.same' => __('ux.validation.password_repeat'),
        ]);

        if ($validator->fails()) {
            throw (new ValidationException($validator))->redirectTo(route('account.password'));
        }

        /** @var array{current_password: string, password: string, password_confirmation: string} $valid */
        $valid = $validator->validated();

        $sessionId = $this->request->hasSession() ? $this->request->session()->getId() : null;

        $outcome = DB::transaction(function () use ($user, $valid, $sessionId): ?int {
            $account = $this->accounts->lockForUpdate($user->id);
            $this->points->afterLock('2.2');

            if ($account === null || ! $account->isActive()) {
                throw new AuthenticationException;
            }

            if (! $this->accounts->passwordMatches($account, $valid['current_password'])) {
                return null;
            }

            $this->accounts->setPassword($account, $valid['password']);
            $this->points->afterWrite(1);

            $revoked = $this->accounts->revokeSessions($account->id, $sessionId);
            $this->points->afterWrite(2);

            $this->audit->record(
                AuditCategory::Security,
                'PASSWORD_CHANGE',
                Actor::user($account->id, $account->display_name),
                changeData: ['sessionsRevoked' => $revoked],
                subject: ['type' => 'user', 'id' => $account->id],
            );
            $this->points->afterWrite(3);

            $this->points->beforeCommit();

            return $revoked;
        });

        if ($outcome === null) {
            $this->securityLog->record(SecurityEvent::PasswordConfirmationFailed, SecurityActor::user($user->id), SecurityOutcome::Refused, $this->request, $user->id);

            throw ValidationException::withMessages(['form' => __('ux.state.signin_failed.current_password.title')])->redirectTo(route('account.password'));
        }

        if ($outcome > 0) {
            $this->securityLog->record(SecurityEvent::SessionRevoked, SecurityActor::user($user->id), SecurityOutcome::Succeeded, $this->request, $user->id);
        }
    }
}
