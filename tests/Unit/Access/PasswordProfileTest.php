<?php

namespace Tests\Unit\Access;

use App\Domain\Access\PasswordProfile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * AICWDF-COMPAT-8 (ADR-004 item 6; SECURITY_MODEL SEC-AUTH-05; TECHNICAL_RULES
 * section 11.3; suite LINK01): 8 to 128 characters with an uppercase letter, a
 * lowercase letter, a number and a symbol, in Unicode as Laravel's Password
 * rule reads them; never trimmed; with its two COPY_DECK messages.
 */
final class PasswordProfileTest extends TestCase
{
    public function test_the_profile_accepts_and_refuses_as_it_says(): void
    {
        $cases = [
            ['Aa1#aaaa', true],
            ['Aa1 aaaa', true],
            ['Ée1€éééé', true],
            ['Пароль#1', true],
            [str_repeat('Aa1#', 32), true],
            ['Aa1#aaa', false],
            [str_repeat('Aa1#', 32).'a', false],
            ['aa1#aaaa', false],
            ['AA1#AAAA', false],
            ['Aaa#aaaa', false],
            ['Aa1aaaaa', false],
            ['', false],
            ["Aa1#aaa\xff", false],
        ];

        foreach ($cases as [$password, $accepted]) {
            $this->assertSame($accepted, PasswordProfile::satisfiedBy($password), $password);
        }

        $this->assertFalse(PasswordProfile::satisfiedBy(null));
        $this->assertFalse(PasswordProfile::satisfiedBy(12345678));
    }

    public function test_the_messages_are_those_of_copy_deck(): void
    {
        $short = Validator::make(['p' => 'Aa1#'], ['p' => [new PasswordProfile]]);
        $long = Validator::make(['p' => str_repeat('Aa1#', 33)], ['p' => [new PasswordProfile]]);

        $this->assertSame(['p' => ['Kata sandi belum memenuhi syarat: minimal 8 karakter, dengan huruf besar, huruf kecil, angka, dan simbol.']], $short->errors()->toArray());
        $this->assertSame(['p' => ['Paling banyak 128 karakter.']], $long->errors()->toArray());
    }
}
