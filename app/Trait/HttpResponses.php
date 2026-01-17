<?php

namespace App\Trait;


use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

trait HttpResponses
{
    protected function success($data, $code = Response::HTTP_OK, $message = null): JsonResponse
    {
        return response()->json([
            'status' => 'Request was successful',
            'data' => $data,
            'message' => $message,
        ], $code);
    }

    protected function error($data, $code, $message = null): JsonResponse
    {
        return response()->json([
            'status' => 'Error has occurred',
            'data' => $data,
            'message' => $message,
        ], $code);
    }
}
