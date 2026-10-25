<?php
declare(strict_types = 1);

namespace Modules\SystemBase\tests\Unit\Isolated;

use Exception;
use Modules\SystemBase\app\Services\ParserService;

class ParserServiceTest extends IsolatedTestCase
{
    /**
     * Parser with placeholders 'upper' (attr 'v') and 'join' (attrs 'a' and 'b').
     */
    protected function makeParser(): ParserService
    {
        $parser = new ParserService();
        $parser->setPlaceholders([
            'upper'  => [
                'parameters' => [],
                'callback'   => fn(array $p) => strtoupper($p['v'] ?? ''),
            ],
            'join'   => [
                'parameters' => ['sep' => '+'],
                'callback'   => fn(array $p, array $params) => ($p['a'] ?? '').$params['sep'].($p['b'] ?? ''),
            ],
            'list'   => [
                'parameters' => [],
                'callback'   => fn() => ['x' => 1],
            ],
            'parent' => [
                'parameters' => [],
                'callback'   => fn(array $p, array $params, array $recursiveData) => (string) ($recursiveData['parent_index'] ?? '-'),
            ],
        ]);

        return $parser;
    }

    public function test_plain_text_is_unchanged(): void
    {
        $this->assertSame('no placeholders ${ENV} {{x}}', $this->makeParser()->parse('no placeholders ${ENV} {{x}}'));
    }

    public function test_placeholder_with_attributes(): void
    {
        $parser = $this->makeParser();

        $this->assertSame('A HELLO B', $parser->parse("A \${{upper v='hello'}} B"));
        $this->assertSame('x+y', $parser->parse('${{join a="x" b=y}}'));
        $this->assertSame('{"x":1}', $parser->parse('${{list}}'));
    }

    public function test_nested_placeholders_are_resolved_innermost_first(): void
    {
        $this->assertSame('A+B', $this->makeParser()->parse("\${{join a='\${{upper v=\"a\"}}' b=\"\${{upper v='b'}}\"}}"));
    }

    public function test_escaped_quotes_in_values(): void
    {
        $this->assertSame("IT'S", $this->makeParser()->parse("\${{upper v='it\\'s'}}"));
    }

    public function test_unknown_placeholder_throws_by_default(): void
    {
        $this->expectException(Exception::class);
        $this->makeParser()->parse('${{unknown}}');
    }

    public function test_unknown_placeholder_inline_error_or_unchanged(): void
    {
        $parser = $this->makeParser();
        $parser->allowThrowExceptions = false;

        $this->assertSame('x ((PARSER_ERR:01)) y', $parser->parse('x ${{unknown a=1}} y'));

        $parser->useInlineErrors = false;
        $this->assertSame("x \${{unknown a='HI'}} y", $parser->parse("x \${{unknown a='\${{upper v=\"hi\"}}'}} y"));
    }

    public function test_unterminated_placeholder_stays(): void
    {
        $parser = $this->makeParser();
        $parser->allowThrowExceptions = false;

        $this->assertSame('x ${{upper v="a"', $parser->parse('x ${{upper v="a"'));
    }

    public function test_max_nesting_depth(): void
    {
        $parser = $this->makeParser();
        $twoLevels = "\${{upper v='\${{upper v=\"a\"}}'}}";

        $parser->maxNestingDepth = 1;
        $this->assertSame('A', $parser->parse($twoLevels));

        $parser->maxNestingDepth = 0;
        $this->expectException(Exception::class);
        $parser->parse($twoLevels);
    }

    public function test_builtin_placeholders(): void
    {
        $this->setConfig('my.value', 'from-config');
        $parser = $this->makeParser();

        $this->assertSame('from-config', $parser->parse("\${{config name='my.value'}}"));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $parser->parse('${{time}}'));
    }

    public function test_parseArray_keeps_keys_and_sets_parent_index(): void
    {
        $result = $this->makeParser()->parseArray([
            'a' => "\${{upper v='x'}}",
            'b' => 5,
            'c' => ['d' => '${{parent}}'],
        ]);

        $this->assertSame(['a' => 'X', 'b' => 5, 'c' => ['d' => '2']], $result);
    }

    public function test_addAllPlaceholderParameters(): void
    {
        $parser = $this->makeParser();
        $parser->addAllPlaceholderParameters(['sep' => '-']);

        $this->assertSame('x-y', $parser->parse("\${{join a='x' b='y'}}"));
    }
}
