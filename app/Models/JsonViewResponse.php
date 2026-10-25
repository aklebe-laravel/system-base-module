<?php

namespace Modules\SystemBase\app\Models;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Application;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Standard JSON result like ['message' => '', 'errors' => [], 'data' => []] with a HTTP status code.
 * Collect messages/data and return it by go().
 */
class JsonViewResponse
{
    /**
     * HTTP status code, >= 400 means error.
     *
     * @var int
     */
    public int $responseStatusCode = 200;

    /**
     * @var array
     */
    public array $responseData = [
        'message' => '',
        'errors'  => [],
        'data'    => [],
    ];

    /**
     * @param  string    $defaultMessage
     * @param  int|null  $defaultStatus  null: keep 200
     */
    public function __construct(string $defaultMessage = '', $defaultStatus = null)
    {
        $this->setMessage($defaultMessage, $defaultStatus);
    }

    /**
     * @param  int  $status
     *
     * @return void
     */
    public function setStatusSuccess(int $status = 200): void
    {
        $this->responseStatusCode = $status;
    }

    /**
     * @param  int  $status
     *
     * @return void
     */
    public function setStatusError(int $status = 422): void
    {
        $this->responseStatusCode = $status;
    }

    /**
     * Creates the HTTP response. Errors (status >= 400) are logged.
     *
     * @return Application|Response|\Illuminate\Contracts\Foundation\Application|ResponseFactory
     */
    public function go(): Application|Response|\Illuminate\Contracts\Foundation\Application|ResponseFactory
    {
        if ($this->responseStatusCode >= 400) {
            $this->logMessages();
        }

        return \response($this->responseData, $this->responseStatusCode);
    }

    /**
     * Logs the message and the errors as error.
     *
     * @return void
     */
    public function logMessages(): void
    {
        Log::error($this->responseData['message']);
        if ($this->responseData['errors']) {
            Log::error($this->responseData['errors']);
        }
    }

    /**
     * Sets the message and optionally the status code.
     *
     * @param  string    $msg
     * @param  int|null  $newStatusCode
     *
     * @return void
     */
    public function setMessage($msg, $newStatusCode = null): void
    {
        if ($newStatusCode !== null) {
            $this->setStatusError($newStatusCode);
        }
        $this->responseData['message'] = $msg;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->responseData['message'];
    }

    /**
     * Sets the message, adds it to the error list and sets the status (default 422).
     *
     * @param  string    $msg
     * @param  int|null  $newStatusCode
     *
     * @return void
     */
    public function setErrorMessage($msg, ?int $newStatusCode = null): void
    {
        $this->setStatusError($newStatusCode ?: 422);
        $this->responseData['message'] = $msg;
        $this->addMessageToErrorList($msg);
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->responseData['errors'];
    }

    /**
     * @param  string  $msg
     *
     * @return void
     */
    public function addMessageToErrorList(string $msg): void
    {
        $this->responseData['errors'][] = $msg;
    }

    /**
     * @param  iterable  $messages
     *
     * @return void
     */
    public function addMessagesToErrorList(iterable $messages): void
    {
        foreach ($messages as $msg) {
            $this->responseData['errors'][] = $msg;
        }
    }

    /**
     * If $data is a scalar, it's used as a key inside of data and value will be used.
     * Otherwise, if $data is array or object, 'data' will set to it and $value will be ignored.
     *
     * @param  mixed       $data
     * @param  mixed|null  $value
     *
     * @return void
     */
    public function setData(mixed $data, mixed $value = null): void
    {
        if (is_scalar($data)) {
            data_set($this->responseData['data'], $data, $value);
        } else {
            $this->responseData['data'] = $data;
        }
    }

    /**
     * @param  string      $key
     * @param  mixed|null  $default
     *
     * @return mixed
     */
    public function getData(string $key, mixed $default = null): mixed
    {
        return data_get($this->responseData['data'], $key, $default);
    }

    /**
     * If this response has errors, the message and all errors are added to the error list of $newResponseObject.
     *
     * @param  JsonViewResponse  $newResponseObject
     *
     * @return void
     */
    public function transportAllMessagesToNewErrorList(JsonViewResponse $newResponseObject): void
    {
        if ($this->hasErrors()) {
            if ($this->responseData['message']) {
                $newResponseObject->addMessageToErrorList($this->responseData['message']);
            }
            foreach ($this->responseData['errors'] as $error) {
                $newResponseObject->addMessageToErrorList($error);
            }
        }
    }

    /**
     * True if the status is >= 400 or the error list is not empty.
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return ($this->responseStatusCode >= 400) || !empty($this->responseData['errors']);
    }

}