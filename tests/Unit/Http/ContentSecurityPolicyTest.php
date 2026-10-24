<?php

namespace Tests\Unit\Http;

use App\Http\ContentSecurityPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The content security policy of SECURITY_MODEL SEC-HDR-03 (TASK-005,
 * AC-005-05): the baseline directives with the request's nonce, no
 * unsafe-inline or unsafe-eval, and fonts and every other resource from the
 * application's own origin.
 */
final class ContentSecurityPolicyTest extends TestCase
{
    public function test_it_is_the_baseline_of_sec_hdr_03_with_the_nonce(): void
    {
        $this->assertSame(
            "default-src 'self'; script-src 'self' 'nonce-AbCdEf0123456789xyz'; style-src 'self' 'nonce-AbCdEf0123456789xyz'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'",
            ContentSecurityPolicy::header('AbCdEf0123456789xyz'),
        );
    }

    public function test_it_never_allows_inline_code_or_another_origin(): void
    {
        $policy = ContentSecurityPolicy::header(str_repeat('a', 40));

        $this->assertStringNotContainsString('unsafe-inline', $policy);
        $this->assertStringNotContainsString('unsafe-eval', $policy);
        $this->assertStringNotContainsString('http', $policy);
        $this->assertStringNotContainsString('*', $policy);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badNonces(): array
    {
        return [
            'empty' => [''],
            'too short' => ['abc123'],
            'a quote that would end the source' => ["abcdefghijklmnop' 'unsafe-inline"],
            'a space' => ['abcdefgh ijklmnop'],
            'a semicolon' => ['abcdefghijklmnop;script-src'],
        ];
    }

    #[DataProvider('badNonces')]
    public function test_it_refuses_a_nonce_that_could_change_the_policy(string $nonce): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ContentSecurityPolicy::header($nonce);
    }
}
