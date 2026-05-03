<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class Response
{
    public static function Success($data, $message, $code=200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'status'=> 1,
            'message' => $message
        ], $code);
    }

    public static function Error($data, $message, $code=500): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'status'=> 0,
            'message' => $message
        ], $code);
    }

    public static function Validation($data, $message, $code=422): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'status'=> 0,
            'message' => $message
        ], $code);
    }
}
