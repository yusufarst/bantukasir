<?php

namespace App\Domain\Access;

use InvalidArgumentException;

/**
 * The capabilities CAP-01 to CAP-34 of SECURITY_MODEL section 6, by their
 * permanent identifiers. What each role holds of each is the map of
 * Capabilities, encoded once (SEC-AZ-02); a route names the capability that
 * opens it, its gate (ROUTE_CONTRACTS RT-05).
 */
enum Capability: string
{
    case Cap01 = 'CAP-01';
    case Cap02 = 'CAP-02';
    case Cap03 = 'CAP-03';
    case Cap04 = 'CAP-04';
    case Cap05 = 'CAP-05';
    case Cap06 = 'CAP-06';
    case Cap07 = 'CAP-07';
    case Cap08 = 'CAP-08';
    case Cap09 = 'CAP-09';
    case Cap10 = 'CAP-10';
    case Cap11 = 'CAP-11';
    case Cap12 = 'CAP-12';
    case Cap13 = 'CAP-13';
    case Cap14 = 'CAP-14';
    case Cap15 = 'CAP-15';
    case Cap16 = 'CAP-16';
    case Cap17 = 'CAP-17';
    case Cap18 = 'CAP-18';
    case Cap19 = 'CAP-19';
    case Cap20 = 'CAP-20';
    case Cap21 = 'CAP-21';
    case Cap22 = 'CAP-22';
    case Cap23 = 'CAP-23';
    case Cap24 = 'CAP-24';
    case Cap25 = 'CAP-25';
    case Cap26 = 'CAP-26';
    case Cap27 = 'CAP-27';
    case Cap28 = 'CAP-28';
    case Cap29 = 'CAP-29';
    case Cap30 = 'CAP-30';
    case Cap31 = 'CAP-31';
    case Cap32 = 'CAP-32';
    case Cap33 = 'CAP-33';
    case Cap34 = 'CAP-34';

    /**
     * The capability a gate names, refused when the name is not one of the
     * 34: a misspelt gate never opens anything.
     */
    public static function named(string $identifier): self
    {
        return self::tryFrom($identifier) ?? throw new InvalidArgumentException('Not a capability of SECURITY_MODEL section 6: '.$identifier);
    }
}
