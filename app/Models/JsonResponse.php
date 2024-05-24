<?php


namespace Modules\SystemBase\app\Models;

use Illuminate\Support\Facades\Response;

class JsonResponse
{
    public array $response = [
        'success'  => true,
        'messages' => [
            'error'   => [],
            'success' => [],
            'warning' => [],
            'info'    => [],
            //            'debug'   => [],
        ],
        'data'     => [],
    ];

    public function __construct()
    {
        //
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function response(): \Illuminate\Http\JsonResponse
    {
        return Response::json($this->response);
    }

    /**
     * @param bool $success
     */
    public function setSuccess(bool $success = true): void
    {
        $this->response['success'] = $success;
    }

    /**
     * @param $data
     */
    public function setData($data): void
    {
        $this->response['data'] = $data;
    }

    /**
     * @param string $msg
     */
    public function addSuccessMessage(string $msg): void
    {
        $this->response['messages']['success'][] = $msg;
    }

    /**
     * @param string $msg
     */
    public function addErrorMessage(string $msg): void
    {
        $this->response['messages']['error'][] = $msg;
    }

}