<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class BaseApiController
{
    /**
     * إرسال استجابة نجاح
     */
    protected function sendSuccess(array $data, string $message, int $statusCode = 200)
    {
        return response()->json([
            'status code' => $statusCode,
            'message' => $message,
            'data' => $data,
            'errors' => []
        ], $statusCode);
    }

    /**
     * إرسال استجابة خطأ
     */
    protected function errorResponse(string $message, array $errors = [], int $statusCode = 400)
    {
        return response()->json([
            'status code' => $statusCode,
            'message' => $message,
            'data' => [],
            'errors' => $errors
        ], $statusCode);
    }

    /**
     * معالجة الأخطاء الاستثنائية
     */
    protected function handleException(\Exception $e, string $logMessage)
    {
        Log::error($logMessage . ': ' . $e->getMessage());
        if (config('app.debug')) {
            $errors = [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
            return $this->sendError('حدث خطأ في الخادم.', $errors, 500);
        }
        return $this->sendError('حدث خطأ غير متوقع، يرجى المحاولة مرة أخرى.', [], 500);
    }
}
