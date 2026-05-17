<?php
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    protected function apiResponse(mixed $data = null, string $message = '', int $status = 200, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => $status < 400,
            'data'    => $data,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }
}
