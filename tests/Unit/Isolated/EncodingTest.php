<?php
declare(strict_types = 1);

namespace Modules\SystemBase\tests\Unit\Isolated;

use Modules\SystemBase\app\Services\Encoding;
use PHPUnit\Framework\TestCase;

class EncodingTest extends TestCase
{
    public function test_toUTF8_keeps_utf8_and_converts_latin1_and_win1252(): void
    {
        $this->assertSame('Grüße €', Encoding::toUTF8('Grüße €'));
        $this->assertSame('Grüße', Encoding::toUTF8("Gr\xFC\xDFe"));
        $this->assertSame('€ „x“', Encoding::toUTF8("\x80 \x84x\x93"));
        $this->assertSame(['a' => 'ä', 'b' => 5], Encoding::toUTF8(['a' => "\xE4", 'b' => 5]));
        $this->assertNull(Encoding::toUTF8(null));
    }

    public function test_toWin1252_keeps_windows_special_chars(): void
    {
        $this->assertSame("Gr\xFC\xDFe", Encoding::toWin1252('Grüße'));
        // € and „“ only exist in Windows-1252 (0x80-0x9F), not in ISO-8859-1
        $this->assertSame("\x80 \x84x\x93", Encoding::toWin1252('€ „x“'));
        // not available in Windows-1252
        $this->assertSame('?', Encoding::toWin1252('😀'));
        $this->assertSame(['x' => "\xE4"], Encoding::toWin1252(['x' => 'ä']));
    }

    public function test_roundtrip(): void
    {
        $text = 'Ein Bär für 10 € – „ok“';

        $this->assertSame($text, Encoding::toUTF8(Encoding::toWin1252($text)));
    }

    public function test_fixUTF8_repairs_double_encoding(): void
    {
        $this->assertSame('Fédération Camerounaise', Encoding::fixUTF8('FÃÂ©dÃÂ©ration Camerounaise'));
        $this->assertSame('Fédération', Encoding::fixUTF8('FÃ©dÃ©ration'));
    }

    public function test_UTF8FixWin1252Chars(): void
    {
        $this->assertSame('€', Encoding::UTF8FixWin1252Chars("\xC2\x80"));
    }

    public function test_removeBOM_and_strlen(): void
    {
        $this->assertSame('abc', Encoding::removeBOM("\xEF\xBB\xBFabc"));
        $this->assertSame('abc', Encoding::removeBOM('abc'));
        $this->assertSame(2, Encoding::strlen('ä'));
    }

    public function test_normalizeEncoding_and_encode(): void
    {
        $this->assertSame('ISO-8859-1', Encoding::normalizeEncoding('latin1'));
        $this->assertSame('ISO-8859-1', Encoding::normalizeEncoding('Windows-1252'));
        $this->assertSame('UTF-8', Encoding::normalizeEncoding('utf8'));
        $this->assertSame('UTF-8', Encoding::normalizeEncoding('something'));

        $this->assertSame("\xE4", Encoding::encode('iso-8859-1', 'ä'));
        $this->assertSame('ä', Encoding::encode('utf-8', "\xE4"));
    }

    public function test_prepareForHtmlTagAttribute(): void
    {
        $this->assertSame('a &quot;b&quot; c', Encoding::prepareForHtmlTagAttribute("  a \"b\"\n  c "));
        // htmlentities() encodes single quotes since PHP 8.1, addslashes() escapes backslashes
        $this->assertSame('a&#039;b \\\\', Encoding::prepareForHtmlTagAttribute("a'b \\"));
        // option is passed to nested arrays
        $this->assertSame(['x' => ["a\n b"]], Encoding::prepareForHtmlTagAttribute(['x' => ["a\n b"]], false));
    }
}
