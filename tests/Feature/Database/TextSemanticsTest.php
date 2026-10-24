<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Str;
use Tests\Harness\DatabaseTestCase;

/**
 * The text semantics of the database (AC-004-08; SCHEMA SCH-14): UTF-8 with
 * the libc provider and the locale en_US.utf8 of glibc 2.41 in the pinned
 * image. "Control character" and "outer whitespace" mean what [[:cntrl:]]
 * and [[:space:]] match there, and lower() is the only authority for
 * comparisons without regard to letter case. The vectors below are the
 * record; a change of the image or of its collation library that changes one
 * of them fails here, and is followed by the reindex step of OPERATIONS_RUNBOOK
 * RB-13.
 *
 * The same vectors show the framework's trimming of input (Str::trim, which
 * App\Http\Middleware\TrimStrings applies), recorded beside the database's:
 * it removes the no-break spaces and the other white space that [[:space:]]
 * does not match (TASK-004 evidence, finding F-2).
 */
final class TextSemanticsTest extends DatabaseTestCase
{
    /**
     * [code point, [[:space:]], [[:cntrl:]], Str::trim removes it around a letter]
     */
    private const array CLASSES = [
        [0x0020, true, false, true],   // space
        [0x0009, true, true, true],    // tab
        [0x000A, true, true, true],    // line feed
        [0x000B, true, true, true],    // vertical tab
        [0x000C, true, true, true],    // form feed
        [0x000D, true, true, true],    // carriage return
        [0x0001, false, true, false],  // start of heading
        [0x001F, false, true, false],  // unit separator
        [0x007F, false, true, false],  // delete
        [0x0080, false, true, false],  // padding character (C1)
        [0x0085, false, true, true],   // next line (C1)
        [0x009F, false, true, false],  // application program command (C1)
        [0x00A0, false, false, true],  // no-break space
        [0x00AD, false, false, true],  // soft hyphen
        [0x1680, true, false, true],   // ogham space mark
        [0x2000, true, false, true],   // en quad
        [0x2007, false, false, true],  // figure space (no-break)
        [0x2009, true, false, true],   // thin space
        [0x200B, false, false, true],  // zero width space
        [0x200E, false, false, true],  // left-to-right mark
        [0x2028, true, false, true],   // line separator
        [0x2029, true, false, true],   // paragraph separator
        [0x202F, false, false, true],  // narrow no-break space
        [0x205F, true, false, true],   // medium mathematical space
        [0x3000, true, false, true],   // ideographic space
        [0xFEFF, false, false, true],  // zero width no-break space
        [0x0061, false, false, false], // a
        [0x00E9, false, false, false], // e with acute
    ];

    /**
     * [text, lower(text)]
     */
    private const array LOWER = [
        ['PEN-01', 'pen-01'],
        ['ÉCLAIR', 'éclair'],
        ['KÖLN', 'köln'],
        ['İstanbul', 'istanbul'],
        ['STRASSE', 'strasse'],
        ['straße', 'straße'],
        ['ẞ', 'ß'],
        ['ΣΑΠΟΥΝΙ', 'σαπουνι'],
        ['ǅ', 'ǆ'],
        ['ﬁ', 'ﬁ'],
        // An address in the reserved top-level domain .example (RFC 2606).
        ['Kasir@Toko-Contoh.EXAMPLE', 'kasir@toko-contoh.example'],
    ];

    public function test_the_character_classes_of_the_database_match_the_record(): void
    {
        $expected = [];
        $actual = [];

        foreach (self::CLASSES as [$code, $space, $control]) {
            $character = mb_chr($code, 'UTF-8');
            $label = sprintf('U+%04X', $code);
            $expected[$label] = ['space' => $space, 'control' => $control];
            $actual[$label] = (array) $this->runtime()->selectOne(
                "select ? ~ '^[[:space:]]$' as space, ? ~ '^[[:cntrl:]]$' as control",
                [$character, $character],
            );
        }

        $this->assertSame($expected, $actual);
    }

    public function test_the_framework_trimming_of_input_matches_the_record(): void
    {
        $expected = [];
        $actual = [];

        foreach (self::CLASSES as [$code, , , $trimmed]) {
            $character = mb_chr($code, 'UTF-8');
            $label = sprintf('U+%04X', $code);
            $expected[$label] = $trimmed;
            $actual[$label] = Str::trim($character.'a'.$character) === 'a';
        }

        $this->assertSame($expected, $actual);
    }

    public function test_lower_maps_letters_as_recorded(): void
    {
        foreach (self::LOWER as [$text, $lower]) {
            $this->assertSame($lower, $this->runtime()->scalar('select lower(?)', [$text]), $text);
        }
    }

    public function test_the_locale_and_its_library_version_are_recorded(): void
    {
        $this->assertSame(
            ['datlocprovider' => 'c', 'datcollate' => 'en_US.utf8', 'datctype' => 'en_US.utf8', 'datcollversion' => '2.41'],
            (array) $this->runtime()->selectOne('select datlocprovider::text, datcollate, datctype, datcollversion from pg_catalog.pg_database where datname = pg_catalog.current_database()'),
        );
    }
}
