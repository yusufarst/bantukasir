<?php

namespace Tests\Unit\Catalog;

use App\Domain\Catalog\Barcodes\BarcodeInput;
use App\Domain\Catalog\Barcodes\BarcodeToken;
use App\Domain\Catalog\Barcodes\InternalCodes;
use App\Domain\Catalog\Barcodes\Symbology;
use App\Support\Validation\BarcodeTokenRule;
use Illuminate\Support\Facades\Validator;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The token rules, L1 of TASK-016 (AC-016-01, AC-016-02; BR-CAT-17, BR-CAT-18; G-09;
 * suite SEARCH01): exact round-trip of leading zeros, case and punctuation; only the
 * scanner terminator and outer spaces removed; 1 to 128 printable ASCII characters;
 * the check digit of each declared symbology; the reserved namespace; and the form of
 * an internal code.
 */
final class BarcodeTokenTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function inputs(): iterable
    {
        yield 'as typed' => ['8991234567891', '8991234567891'];
        yield 'leading zeros' => ['000123', '000123'];
        yield 'case and punctuation' => ['aB-c.D/e_f+g', 'aB-c.D/e_f+g'];
        yield 'an inner space' => ['AB 12', 'AB 12'];
        yield 'outer spaces' => ['  AB12   ', 'AB12'];
        yield 'a carriage return' => ["AB12\r", 'AB12'];
        yield 'a line feed' => ["AB12\n", 'AB12'];
        yield 'both' => ["AB12\r\n", 'AB12'];
        yield 'spaces before and after a terminator' => ["  AB12 \r\n  ", 'AB12'];
        yield 'one terminator only' => ["AB12\n\n", "AB12\n"];
        yield 'a tab stays' => ["\tAB12\t", "\tAB12\t"];
        yield 'a no-break space stays' => ["\u{00A0}AB12", "\u{00A0}AB12"];
        yield 'a leading line break stays' => ["\nAB12", "\nAB12"];
        yield 'nothing but a terminator' => ["\r\n", ''];
        yield 'nothing but spaces' => ['    ', ''];
    }

    #[DataProvider('inputs')]
    public function test_only_the_terminator_and_outer_spaces_are_removed(string $input, string $token): void
    {
        $this->assertSame($token, BarcodeToken::normalized($input));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function tokens(): iterable
    {
        yield 'one character' => ['A', true];
        yield 'printable ASCII from space to tilde' => ['!"#$%&\'()*+,-./0123456789:;<=>?@AZ[\]^_`az{|}~', true];
        yield '128 characters' => [str_repeat('a', 128), true];
        yield 'an inner space' => ['A B', true];
        yield 'empty' => ['', false];
        yield '129 characters' => [str_repeat('a', 129), false];
        yield 'a leading space' => [' A', false];
        yield 'a trailing space' => ['A ', false];
        yield 'a line feed at the end' => ["A\n", false];
        yield 'an inner line feed' => ["A\nB", false];
        yield 'an inner carriage return' => ["A\rB", false];
        yield 'a tab' => ["A\tB", false];
        yield 'a NUL' => ["A\u{0000}B", false];
        yield 'a DEL' => ["A\u{007F}B", false];
        yield 'an accented letter' => ['É', false];
        yield 'a no-break space' => ["A\u{00A0}B", false];
    }

    #[DataProvider('tokens')]
    public function test_a_token_holds_1_to_128_printable_ascii_characters(string $token, bool $wellFormed): void
    {
        $this->assertSame($wellFormed, BarcodeToken::isWellFormed($token));
    }

    public function test_the_rule_reads_the_token_that_the_input_carries(): void
    {
        foreach (["AB12 \r\n", ' AB12', '007'] as $valid) {
            $this->assertTrue(Validator::make(['t' => $valid], ['t' => [new BarcodeTokenRule]])->passes(), $valid);
        }

        foreach (["A\nB", "\tAB", 'É', str_repeat('a', 129)] as $invalid) {
            $this->assertFalse(Validator::make(['t' => $invalid], ['t' => [new BarcodeTokenRule]])->passes(), $invalid);
        }

        $this->assertFalse(Validator::make(['t' => 7], ['t' => [new BarcodeTokenRule]])->passes());
    }

    /**
     * @return iterable<string, array{Symbology, string, bool}>
     */
    public static function declared(): iterable
    {
        yield 'EAN-13' => [Symbology::Ean13, '4006381333931', true];
        yield 'EAN-13 of the shop' => [Symbology::Ean13, '8991234567891', true];
        yield 'EAN-13, a wrong check digit' => [Symbology::Ean13, '8991234567890', false];
        yield 'EAN-13, two digits swapped' => [Symbology::Ean13, '4006381339331', false];
        yield 'EAN-13, too short' => [Symbology::Ean13, '400638133393', false];
        yield 'EAN-13, too long' => [Symbology::Ean13, '40063813339311', false];
        yield 'EAN-13, a letter' => [Symbology::Ean13, '400638133393A', false];
        yield 'EAN-8' => [Symbology::Ean8, '96385074', true];
        yield 'EAN-8 again' => [Symbology::Ean8, '73513537', true];
        yield 'EAN-8, a wrong check digit' => [Symbology::Ean8, '96385075', false];
        yield 'UPC-A' => [Symbology::UpcA, '036000291452', true];
        yield 'UPC-A, a wrong check digit' => [Symbology::UpcA, '036000291453', false];
        yield 'UPC-A, the length of EAN-13' => [Symbology::UpcA, '4006381333931', false];
        yield 'UPC-E ending in 0 to 2' => [Symbology::UpcE, '04252614', true];
        yield 'UPC-E ending in 3' => [Symbology::UpcE, '01234531', true];
        yield 'UPC-E ending in 4' => [Symbology::UpcE, '01234543', true];
        yield 'UPC-E ending in 5 to 9' => [Symbology::UpcE, '01234565', true];
        yield 'UPC-E of number system 1' => [Symbology::UpcE, '11234562', true];
        yield 'UPC-E, a wrong check digit' => [Symbology::UpcE, '01234566', false];
        yield 'UPC-E, a number system it does not have' => [Symbology::UpcE, '21234561', false];
        yield 'UPC-E, the length of UPC-A' => [Symbology::UpcE, '036000291452', false];
        yield 'all zeros' => [Symbology::Ean13, '0000000000000', true];
    }

    #[DataProvider('declared')]
    public function test_a_declared_symbology_validates_its_check_digit(Symbology $symbology, string $token, bool $accepted): void
    {
        $this->assertSame($accepted, $symbology->accepts($token));
    }

    public function test_the_namespace_is_reserved_exactly_as_written(): void
    {
        foreach (['BK-', 'BK-123', 'BK-P-'.str_repeat('A', 16)] as $reserved) {
            $this->assertTrue(BarcodeToken::isReserved($reserved), $reserved);
        }

        foreach (['bk-123', 'Bk-1', 'BK', 'XBK-1', ' BK-1'] as $other) {
            $this->assertFalse(BarcodeToken::isReserved($other), $other);
        }
    }

    public function test_an_internal_code_is_the_prefix_and_16_crockford_characters_never_repeating(): void
    {
        $codes = [];
        $source = new InternalCodes;

        foreach (range(1, 2000) as $draw) {
            $code = $source->next();
            $this->assertMatchesRegularExpression('/\ABK-P-[0-9A-HJKMNP-TV-Z]{16}\z/', $code);
            $this->assertTrue(BarcodeToken::isWellFormed($code));
            $codes[$code] = true;
        }

        $this->assertCount(2000, $codes);

        // Every character of the alphabet comes up, and none other: the alphabet is
        // the 32 of Crockford's, read from what is drawn.
        $seen = count_chars(implode('', array_map(static fn (string $code): string => substr($code, 5), array_keys($codes))), 3);
        $this->assertSame('0123456789ABCDEFGHJKMNPQRSTVWXYZ', $seen);
    }

    public function test_an_input_is_read_from_a_validated_payload(): void
    {
        $input = BarcodeInput::fromPayload(['token' => " 0012-Ab\r\n", 'symbology' => null]);
        $this->assertSame('0012-Ab', $input->token);
        $this->assertNull($input->symbology);

        $this->assertSame(Symbology::Ean8, BarcodeInput::fromPayload(['token' => '96385074', 'symbology' => 'EAN_8'])->symbology);
        $this->assertSame(['EAN_13', 'EAN_8', 'UPC_A', 'UPC_E'], Symbology::names());
        $this->assertThrows(static fn () => BarcodeInput::fromPayload(['token' => 7]), LogicException::class);
    }
}
