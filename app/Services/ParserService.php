<?php

namespace Modules\SystemBase\app\Services;

use Exception;
use Modules\SystemBase\app\Services\Base\BaseService;

class ParserService extends BaseService
{
    /**
     * Do not ever change this default value here!
     * You should override it by your own class if needed.
     *
     * @var string
     */
    protected string $regexDelimiter = '/';

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
     * @var string
     */
    protected string $regexAnySpacesOrNothing = '(?:\s*)';

    /**
     * If true and a parser error occurs, the parsed a result is an error text
     * like ((PARSER_ERR:42))
     *
     * @var bool
     */
    protected bool $useInlineErrors = true;

    /**
     * @var bool
     */
    public bool $allowThrowExceptions = true;

    /**
     * @var array|array[]
     */
    protected array $placeholders = [];

    /**
     *
     */
    public function __construct()
    {
        parent::__construct();

        /**
         * callback like: function (array $placeholderParameters, array $parameters, array $recursiveData)
         */
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

        //
        $this->init();
    }

    /**
     * Overwrite in needed to prepare fixed placeholders or something else.
     *
     * @return void
     */
    protected function init(): void
    {
    }

    public function getParserPlaceholderErrorCode(string $code): string
    {
        if (!$this->useInlineErrors) {
            return '';
        }

        return "((PARSER_ERR:$code))";
    }

    /**
     * callback like: function (array $placeholderParameters, array $parameters, array $recursiveData)
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
            'config'       => [
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
            ... $placeholders
        ];
    }

    /**
     * @return string
     */
    protected function getRegexPlaceholderPattern(): string
    {
        return $this->regexDelimiter.preg_quote($this->placeholderPrefix,
                $this->regexDelimiter).$this->regexAnySpacesOrNothing.'(.+?)(\s+.*?|)'.$this->regexAnySpacesOrNothing.preg_quote($this->placeholderSuffix,
                $this->regexDelimiter).$this->regexDelimiter;
    }

    /**
     * @return string
     */
    protected function getRegexPlaceholderParametersPattern(): string
    {
        return $this->regexDelimiter.$this->regexAnySpacesOrNothing.//                                  '(' . $placeholderKey . ')' .
            '(.+?)(\s*=\s*(".*?"|\'.*?\')|\s|$)'.$this->regexAnySpacesOrNothing.$this->regexDelimiter;
    }

    /**
     * Parsing a text by default and returns the parsed text.
     *
     * @param  string  $subject
     * @param  array  $recursiveData
     *
     * @return string
     * @throws Exception
     */
    public function parse(string $subject, array $recursiveData = []): string
    {
        $placeholderPattern = $this->getRegexPlaceholderPattern();

        return preg_replace_callback($placeholderPattern, function ($matches) use ($recursiveData) {
            $placeholder = trim($matches[1]);
            $placeholderParameter = trim($matches[2]);
            $paramsPattern = $this->getRegexPlaceholderParametersPattern();

            $parameterList = [];
            if (preg_match_all($paramsPattern, $placeholderParameter, $paramMatches, PREG_SET_ORDER)) {
                foreach ($paramMatches as $paramMatch) {
                    $parameterName = $paramMatch[1];
                    $parameterContent = $this->prepareParameterContent($paramMatch[3] ?? '');
                    $parameterList[$parameterName] = $parameterContent;
                }
            }

            foreach ($this->placeholders as $placeholderKey => $placeholderData) {
                if ($placeholder === $placeholderKey) {
                    if ($callback = ($placeholderData['callback'] ?? null)) {
                        $res = $callback($parameterList, $placeholderData['parameters'] ?? [], $recursiveData);
                        if (!is_scalar($res)) {
                            $res = json_encode($res);
                        }

                        return $res;
                    }
                }
            }

            if ($this->allowThrowExceptions) {
                throw new Exception(sprintf("Parser Error! Placeholder: %s", $placeholder));
            }

            return $this->getParserPlaceholderErrorCode('01');
        }, $subject);
    }

    /**
     * Parsing a text and returns an array of all matched results.
     * The results can have any type.
     * This method is especially used to parse arrays.
     *
     * Returns an array like:
     * ['placeholder_x'][0] = [1,2,3]
     * ['placeholder_x'][1] = 'hello'
     * ['placeholder_x'][2] = 'world'
     * ['placeholder_x'][3] = []
     * ['placeholder_y'][0] = ['a','b']
     * ['placeholder_y'][1] = ['y','z']
     *
     * @param  string  $subject
     * @param  array  $recursiveData
     *
     * @return array
     */
    public function parseSpecial(string $subject, array $recursiveData = []): array
    {
        $resultList = [];
        $placeholderPattern = $this->getRegexPlaceholderPattern();

        if (preg_match_all($placeholderPattern, $subject, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $placeholder = trim($match[1]);
                $placeholderParameter = trim($match[2]);
                $paramsPattern = $this->getRegexPlaceholderParametersPattern();

                $parameterList = [];
                if (preg_match_all($paramsPattern, $placeholderParameter, $paramMatches, PREG_SET_ORDER)) {
                    foreach ($paramMatches as $paramMatch) {
                        $parameterName = $paramMatch[1];
                        $parameterContent = $this->prepareParameterContent($paramMatch[3] ?? '');
                        $parameterList[$parameterName] = $parameterContent;
                    }
                }

                $debugListItem = [
                    'placeholder'            => $placeholder,
                    'placeholder_parameters' => $parameterList,
                    'executed'               => 0,
                ];

                foreach ($this->placeholders as $placeholderKey => $placeholderData) {
                    if ($placeholder === $placeholderKey) {
                        if ($callback = ($placeholderData['callback'] ?? null)) {
                            $resultList[$placeholderKey][] = $callback($parameterList,
                                $placeholderData['parameters'] ?? [], $recursiveData);
                            $debugListItem['executed'] = 1;
                        }
                    }
                }
            }

        }

        return $resultList;
    }

    /**
     * Prepare the content of placeholder parameters by cutting quotes at
     * start and end if they exist.
     *
     * @param  string  $parameterContent
     *
     * @return string
     */
    public function prepareParameterContent(string $parameterContent): string
    {
        $parameterContent = trim($parameterContent);
        if (($strLen = strlen($parameterContent) > 1) && ($parameterContent[0] === $parameterContent[$strLen - 1])) {
            if (($parameterContent[0] === '"') || ($parameterContent[0] === "'")) {
                $parameterContent = substr($parameterContent, 1, $strLen - 2);
            }
        }

        return $parameterContent;
    }

    /**
     * @param  array  $varDataArray
     * @param  array  $params
     * @param  array  $recursiveData
     *
     * @return array
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
     * @param  array  $ediArray
     * @param  array  $params
     * @param $recursiveData
     * @return array
     */
    public function parseArraySpecial(array $ediArray, array $params = [], $recursiveData = []): array
    {
        $resultArray = [];

        foreach ($ediArray as $v) {
            if (is_string($v)) {
                if ($res = $this->parseSpecial($v, $recursiveData)) {

                    foreach ($res as $placeholderNameFound => $placeholderObjectList) {
                        if (is_array($placeholderObjectList)) {
                            foreach ($placeholderObjectList as $placeholderObject) {
                                if (is_array($placeholderObject)) {
                                    // deeper nesting parsing ...
                                    // @TODO: adjust params?
                                    $placeholderObject = $this->parseArraySpecial($placeholderObject, $params,
                                        $recursiveData);
                                    foreach ($placeholderObject as $item) {
                                        $resultArray[] = $item;
                                    }
                                } else {
                                    $resultArray[] = $v;
                                }
                            }
                        } else {
                            $resultArray[] = $v;
                        }
                    }
                } else {
                    $resultArray[] = $v;
                }
            } elseif (is_array($v)) {
                $recursiveData['parent_index'] = count($resultArray); // the index we will create within the next row ...
                $resultArray[] = $this->parseArraySpecial($v, $params, $recursiveData);
            } else {
                $resultArray[] = $v;
            }
        }

        return $resultArray;
    }

    /**
     * Add/change placeholder parameters.
     * Useful to set in loop where objects are changed.
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
