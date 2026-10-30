<?php

namespace App\Domain\Configuration;

/**
 * The accent set of DESIGN_SYSTEM section 3.3 (SCHEMA `accent_token`;
 * TECHNICAL_RULES section 11.3; D-086, D-087): the shop chooses one, checked
 * on the server; a revision without one renders as the default, green.
 */
final class Accents
{
    public const string DEFAULT = 'green';

    /** @var list<string> */
    public const array TOKENS = ['green', 'teal', 'blue', 'purple', 'graphite'];

    /**
     * The accent a revision renders with: its own, or the default.
     */
    public static function rendered(?string $token): string
    {
        return $token !== null && in_array($token, self::TOKENS, true) ? $token : self::DEFAULT;
    }
}
