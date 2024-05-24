<?php


namespace Modules\SystemBase\app\Models;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Application;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Used for standard json results.
 */
class JsonViewResponse
{
    /**
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
     * @param $defaultMessage
     * @param $defaultStatus
     */
    public function __construct($defaultMessage = '', $defaultStatus = null)
    {
        $this->setMessage($defaultMessage, $defaultStatus);
    }

    /**
     * @param $status
     * @return void
     */
    public function setStatusSuccess($status = 200): void
    {
        $this->responseStatusCode = $status;
    }

    public function setStatusError($status = 422): void
    {
        $this->responseStatusCode = $status;
    }

    /**
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
     * @param $msg
     * @param $newStatusCode
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
    public function getMessage() : string
    {
        return $this->responseData['message'];
    }

    /**
     * @param $msg
     * @param $newStatusCode
     * @return void
     */
    public function setErrorMessage($msg, $newStatusCode = null): void
    {
        $this->setStatusError($newStatusCode ?: 422);
        $this->responseData['message'] = $msg;
    }

    /**
     * @param  bool  $inclusiveMessage
     * @return array
     */
    public function getErrors(bool $inclusiveMessage = true): array
    {
        if ($inclusiveMessage) {
            return array_merge([$this->responseData['message']], $this->responseData['errors']);
        }

        return $this->responseData['errors'];
    }

    /**
     * @param  string  $msg
     * @return void
     */
    public function addMessageToErrorList(string $msg): void
    {
        $this->responseData['errors'][] = $msg;
    }

    /**
     * @param  iterable  $messages
     * @return void
     */
    public function addMessagesToErrorList(iterable $messages): void
    {
        foreach ($messages as $msg) {
            $this->responseData['errors'][] = $msg;
        }
    }

    /**
     * @param $data
     * @return void
     */
    public function setData($data): void
    {
        $this->responseData['data'] = $data;
    }

    /**
     * @param $key
     * @param $data
     * @return void
     */
    public function setRootData($key, $data): void
    {
        $this->responseData[$key] = $data;
    }

    /**
     * Copy all errors if any.
     *
     * @param JsonViewResponse $newResponseObject
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
     * @return bool
     */
    public function hasErrors() : bool
    {
        return (($this->responseStatusCode >= 400) || ($this->responseData['errors']));
    }

}