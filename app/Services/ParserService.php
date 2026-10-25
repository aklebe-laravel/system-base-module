<?php

namespace Modules\SystemBase\app\Services;

use Exception;
use Modules\SystemBase\app\Services\Base\BaseService;

/**
 * Parser for placeholders like ${{name attr='value' attr2="value2"}}.
 *
 * - Placeholders are registered by setPlaceholders() as name => ['parameters' => [], 'callback' => fn].
 * - The callback is called like callback(array $placeholderParameters, array $parameters, array $recursiveData).
 *   $placeholderParameters are the attributes written in the placeholder,
 *   $parameters are the fixed 'parameters' of the registration.
 * - Attribute values can contain placeholders themselves (resolved innermost first).
 * - Inside quoted values \' \" and \\ are escape sequences.
 * - Non-scalar callback results are returned as JSON.
 * - Env vars like ${APP_URL} are NOT handled here, see SystemService::parseEnv().
 *
 * Unknown placeholders: exception ($allowThrowExceptions), inline error code ($useInlineErrors)
 * or the placeholder is left in the text.
 */
class ParserService extends BaseService
{
    /**
     * Do not ever change this default value here!
     * You should override it by your own class if needed.
     *
     * @var string
     */
    protected string $placeholderPrefix = '${{';

    /**
     * Do not ever change this default value here!
     * You should override it by your own class if needed.
     *
     * @var string
     */
    protected string $placeholderSuffix = '}}';

    /**
     * If true and a placeholder is unknown, it is replaced by an error text like ((PARSER_ERR:01)).
     * Only used if $allowThrowExceptions is false.
     *
     * @var bool
     */
    public bool $useInlineErrors = true;

    /**
     * If true, an unknown placeholder throws an Exception.
     *
     * @var bool
     */
    public bool $allowThrowExceptions = true;

    /**
     * Safety guard against pathological / malicious deeply nested input.
     * Each level of ${{ ... }} nesting inside an attribute value increases
     * the depth by one. Throws an Exception once exceeded.
     *
     * @var int
     */
    public int $maxNestingDepth = 200;

    /**
     * Registered placeholders: name => ['parameters' => array, 'callback' => callable].
     *
     * @var array<string, array{parameters: array, callback: callable}>
     */
    protected array $placeholders = [];

    /**
     * Registers two demo placeholders ('test' and 'array_result'), see setPlaceholders().
     */
    public function __construct()
    {
        parent::__construct();

        // callback like: function (array $placeholderParameters, array $parameters, array $recursiveData)
        $this->placeholders = [
            'test'         => [
                'parameters' => [],
                'callback'   => function (array $placeholderParameters, array $parameters, array $recursiveData) {
                    return "(Test success!".json_encode($placeholderParameters).")";
                },
            ],
            'array_result' => [
                'parameters' => [],
                'callback'   => function (array $placeholderParameters, array $parameters, array $recursiveData) {
                    return [
                        [1, 2, 3],
                        [4, 5, '99'],
                    ];
                },
            ],
        ];

        $this->init();
    }

    /**
     * Overwrite if needed to prepare fixed placeholders or something else.
     *
     * @return void
     */
    protected function init(): void
    {
    }

    /**
     * Inline error text like ((PARSER_ERR:01)), or '' if $useInlineErrors is false.
     *
     * @param  string  $code
     *
     * @return string
     */
    public function getParserPlaceholderErrorCode(string $code): string
    {
        if (!$this->useInlineErrors) {
            return '';
        }

        return "((PARSER_ERR:$code))";
    }

    /**
     * Replaces all placeholders (also the demo ones from the constructor) by $placeholders
     * plus the built-in ones:
     * - base_path path='...'  base_path() of the given path
     * - env name='...'        env() value or ''
     * - config name='...'     config() value or ''
     * - time                  current date time like 2026-10-19 13:45:00
     * Entries in $placeholders with the same name overwrite the built-in ones.
     *
     * Callback like: function (array $placeholderParameters, array $parameters, array $recursiveData)
     *
     * @param  array  $placeholders
     *
     * @return void
     */
    public function setPlaceholders(array $placeholders): void
    {
        $this->placeholders = [
            'base_path' => [
                'parameters' => [],
                'callback'   => function (array $placeholderParameters, array $parameters, array $recursiveData) {
                    return base_path(data_get($placeholderParameters, 'path', ''));
                },
            ],
            'env'       => [
                'parameters' => [],
                'callback'   => function (array $placeholderParameters, array $parameters, array $recursiveData) {
                    if (!($name = data_get($placeholderParameters, 'name', data_get($placeholderParameters, 'default', '')))) {
                        return '';
                    }

                    return env($name, '');
                },
            ],
            'config'    => [
                'parameters' => [],
                'callback'   => function (array $placeholderParameters, array $parameters, array $recursiveData) {
                    if (!($name = data_get($placeholderParameters, 'name', data_get($placeholderParameters, 'default', '')))) {
                        return '';
                    }

                    return config($name, '');
                },
            ],
            'time'      => [
                'parameters' => [],
                'callback'   => function (array $placeholderParameters, array $parameters, array $recursiveData) {
                    return date(SystemService::dateIsoFormat8601);
                },
            ],
            ... $placeholders,
        ];
    }

    /**
     * Parsing a text by default and returns the parsed text.
     *
     * Supports arbitrarily deep nesting, e.g.:
     *   ${{outer a='${{inner x='1'}}' b="${{inner2}}"}}
     *
     * @param  string  $subject
     * @param  array   $recursiveData
     *
     * @return string
     * @throws Exception
     */
    public function parse(string $subject, array $recursiveData = []): string
    {
        $pos = 0;

        return $this->parseText($subject, $pos, null, $recursiveData, 0);
    }

    /**
     * Scans $subject starting at $pos, copying plain text through unchanged
     * and expanding every ${{ ... }} placeholder it encounters (recursively,
     * via parsePlaceholder()) until either the end of the string or -
     * when called while reading a quoted attribute value - the matching
     * $stopQuote character is reached (the quote itself is NOT consumed
     * here, the caller does that).
     *
     * When $stopQuote is set (i.e. we're inside a quoted attribute value),
     * a backslash directly followed by the quote character or another
     * backslash is treated as an escape sequence (\' \" \\) and unescaped
     * in the output - this lets a value contain its own quote character.
     *
     * @param  string       $subject
     * @param  int          $pos        current scan position, advanced by reference
     * @param  string|null  $stopQuote  stop (without consuming) at this
     *                                  unescaped character, or null to scan
     *                                  until the end of $subject
     * @param  array        $recursiveData
     * @param  int          $depth      current nesting depth, used for maxNestingDepth
     *
     * @return string
     * @throws Exception
     */
    protected function parseText(
        string $subject,
        int &$pos,
        ?string $stopQuote,
        array $recursiveData,
        int $depth
    ): string {
        $len = strlen($subject);
        $prefixLen = strlen($this->placeholderPrefix);
        $out = '';

        while ($pos < $len) {
            if ($stopQuote !== null && $subject[$pos] === $stopQuote) {
                break; // caller consumes the closing quote itself
            }

            if ($stopQuote !== null
                && $subject[$pos] === '\\'
                && $pos + 1 < $len
                && ($subject[$pos + 1] === $stopQuote || $subject[$pos + 1] === '\\')
            ) {
                // Escaped quote/backslash inside a quoted value: \' \" \\
                $out .= $subject[$pos + 1];
                $pos += 2;
                continue;
            }

            if ($this->matchesAt($subject, $pos, $this->placeholderPrefix, $prefixLen, $len)) {
                $out .= $this->parsePlaceholder($subject, $pos, $recursiveData, $depth);
                continue;
            }

            $out .= $subject[$pos];
            $pos++;
        }

        return $out;
    }

    /**
     * Parses a single ${{ name attr='value' attr2="value2" }} block starting
     * exactly at $pos (which must point at the placeholder prefix). Attribute
     * values are parsed via parseText() with the opening quote as $stopQuote,
     * which is what makes nested placeholders inside attribute values work:
     * any ${{ ... }} found while reading a value is fully resolved first
     * (recursively, depth-first / innermost first), and the resulting string
     * becomes that attribute's value before the outer placeholder's own
     * callback is finally invoked.
     *
     * @param  string  $subject
     * @param  int     $pos
     * @param  array   $recursiveData
     * @param  int     $depth
     *
     * @return string
     * @throws Exception
     */
    protected function parsePlaceholder(
        string $subject,
        int &$pos,
        array $recursiveData,
        int $depth
    ): string {
        if ($depth > $this->maxNestingDepth) {
            throw new Exception(sprintf("Parser Error! Max nesting depth of %d exceeded.",
                $this->maxNestingDepth));
        }

        $len = strlen($subject);
        $prefixLen = strlen($this->placeholderPrefix);
        $suffixLen = strlen($this->placeholderSuffix);

        $pos += $prefixLen;

        // $raw mirrors everything consumed for this placeholder, but with
        // any nested ${{ ... }} inside attribute values already resolved.
        // This is what gets returned if the placeholder itself can't be
        // resolved (unknown name) or is malformed - so nested placeholders
        // that WERE successfully resolved don't get lost again.
        $raw = $this->placeholderPrefix;

        $raw .= $this->skipWhitespace($subject, $pos);

        // --- placeholder name -------------------------------------------
        $name = '';
        while ($pos < $len
               && !ctype_space($subject[$pos])
               && !$this->matchesAt($subject, $pos, $this->placeholderSuffix, $suffixLen, $len)) {
            $name .= $subject[$pos];
            $pos++;
        }
        $raw .= $name;
        $name = trim($name);

        $raw .= $this->skipWhitespace($subject, $pos);

        // --- attributes ---------------------------------------------------
        $parameterList = [];
        $terminated = false;

        while ($pos < $len) {
            if ($this->matchesAt($subject, $pos, $this->placeholderSuffix, $suffixLen, $len)) {
                $pos += $suffixLen;
                $raw .= $this->placeholderSuffix;
                $terminated = true;
                break;
            }

            $attrName = '';
            while ($pos < $len
                   && !ctype_space($subject[$pos])
                   && $subject[$pos] !== '='
                   && !$this->matchesAt($subject, $pos, $this->placeholderSuffix, $suffixLen, $len)) {
                $attrName .= $subject[$pos];
                $pos++;
            }
            $raw .= $attrName;
            $attrName = trim($attrName);

            $raw .= $this->skipWhitespace($subject, $pos);

            $attrValue = '';
            if ($pos < $len && $subject[$pos] === '=') {
                $pos++; // consume '='
                $raw .= '=';
                $raw .= $this->skipWhitespace($subject, $pos);

                if ($pos < $len && ($subject[$pos] === '"' || $subject[$pos] === "'")) {
                    $quoteChar = $subject[$pos];
                    $pos++; // consume opening quote
                    $raw .= $quoteChar;

                    // Recursively resolves any ${{ ... }} found inside the
                    // value, and unescapes \' \" \\ along the way.
                    $attrValue = $this->parseText($subject, $pos, $quoteChar, $recursiveData, $depth + 1);
                    $raw .= $attrValue;

                    if ($pos < $len && $subject[$pos] === $quoteChar) {
                        $pos++; // consume closing quote
                        $raw .= $quoteChar;
                    }
                } else {
                    // unquoted value: read until whitespace or the suffix
                    while ($pos < $len
                           && !ctype_space($subject[$pos])
                           && !$this->matchesAt($subject, $pos, $this->placeholderSuffix, $suffixLen, $len)) {
                        $attrValue .= $subject[$pos];
                        $pos++;
                    }
                    $raw .= $attrValue;
                }
            }

            if ($attrName !== '') {
                $parameterList[$attrName] = $attrValue;
            }

            $raw .= $this->skipWhitespace($subject, $pos);
        }

        if (!$terminated) {
            // Unterminated / malformed placeholder (no closing suffix found).
            // Mirrors the old regex behaviour: a non-matching pattern is
            // simply left untouched - but here as the already-reconstructed
            // text, so already-resolved nested placeholders survive.
            return $raw;
        }

        // --- resolve --------------------------------------------------
        $placeholderData = $this->placeholders[$name] ?? null;
        if ($placeholderData && ($callback = ($placeholderData['callback'] ?? null))) {
            $res = $callback($parameterList, $placeholderData['parameters'] ?? [], $recursiveData);

            if (!is_scalar($res)) {
                $res = json_encode($res);
            }

            return (string) $res;
        }

        // from here: nothing was resolved ...

        if ($this->allowThrowExceptions) {
            throw new Exception(sprintf("Parser Error! Placeholder: %s", $name));
        }

        if ($this->useInlineErrors) {
            return $this->getParserPlaceholderErrorCode('01');
        }

        // Same reasoning as the unterminated case above: return the
        // reconstructed text (nested placeholders already resolved),
        // not a raw slice of the untouched original input.
        return $raw;
    }

    /**
     * Compares $needle against $subject at position $pos without allocating
     * a substring first (unlike `substr($subject, $pos, $len) === $needle`,
     * which builds a throwaway string on every single call). Bails out at
     * the first mismatching character, so this stays cheap even when
     * scanning very large inputs character by character.
     *
     * @param  string  $subject
     * @param  int     $pos
     * @param  string  $needle
     * @param  int     $needleLen   strlen($needle), passed in so callers that
     *                              already know it (prefix/suffix length) don't
     *                              recompute it on every call
     * @param  int     $subjectLen  strlen($subject), same reasoning
     *
     * @return bool
     */
    protected function matchesAt(string $subject, int $pos, string $needle, int $needleLen, int $subjectLen): bool
    {
        if ($pos + $needleLen > $subjectLen) {
            return false;
        }

        for ($i = 0; $i < $needleLen; $i++) {
            if ($subject[$pos + $i] !== $needle[$i]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Advances $pos past any whitespace and returns the whitespace that was
     * skipped (needed so the raw fallback reconstruction in
     * parsePlaceholder() can stay byte-for-byte faithful to the input).
     *
     * @param  string  $subject
     * @param  int     $pos
     *
     * @return string
     */
    protected function skipWhitespace(string $subject, int &$pos): string
    {
        $len = strlen($subject);
        $whitespace = '';
        while ($pos < $len && ctype_space($subject[$pos])) {
            $whitespace .= $subject[$pos];
            $pos++;
        }

        return $whitespace;
    }

    /**
     * Parses all string values of a nested array (keys are kept).
     * $recursiveData['parent_index'] is set to the index of the current sub array for the callbacks.
     *
     * @param  array  $varDataArray
     * @param  array  $params  passed through to nested levels only (not used by the parser itself)
     * @param  array  $recursiveData
     *
     * @return array
     * @throws Exception
     */
    public function parseArray(array $varDataArray, array $params = [], array $recursiveData = []): array
    {
        $resultArray = [];

        foreach ($varDataArray as $k => $v) {
            if (is_string($v)) {
                $resultArray[$k] = $this->parse($v, $recursiveData);
            } elseif (is_array($v)) {
                $recursiveData['parent_index'] = count($resultArray); // the index we will create within the next row ...
                $resultArray[$k] = $this->parseArray($v, $params, $recursiveData);
            } else {
                $resultArray[$k] = $v;
            }
        }

        return $resultArray;
    }

    /**
     * Add/change the fixed 'parameters' of all registered placeholders.
     * Useful in loops where the current object changes.
     *
     * @param  array  $params
     *
     * @return void
     */
    public function addAllPlaceholderParameters(array $params): void
    {
        foreach ($this->placeholders as &$placeholder) {
            $placeholder['parameters'] = array_merge($placeholder['parameters'], $params);
        }
    }
}