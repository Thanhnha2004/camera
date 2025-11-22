<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MailController extends Controller
{
    public function sendMail()
    {
        // 1. Lấy API Key từ biến môi trường (ƯU TIÊN VÀ BẢO MẬT)
        // Bạn cần thêm BREVO_API_KEY="xkeysib-..." vào file .env
        $apiKey = env('BREVO_API_KEY');
        
        // Kiểm tra xem API Key đã được cấu hình chưa
        if (empty($apiKey)) {
            Log::error('BREVO_API_KEY is not set in environment variables.');
            return response()->json([
                'error' => 'Email not sent',
                'details' => 'Brevo API Key chưa được thiết lập trong file .env.'
            ], 500);
        }

        // Cấu hình dữ liệu gửi mail
        $payload = [
            'sender' => [
                'name' => 'Camerashop',
                // Địa chỉ email này PHẢI thuộc tên miền đã được xác thực (Bước 1 hướng dẫn trước)
                'email' => 'tranthanhnha.28032004@gmail.com' 
            ],
            'to' => [
                // Thêm tên người nhận để Brevo ghi nhận tốt hơn
                ['email' => 'tranthanhnha.28032004@gmail.com', 'name' => 'Người Nhận Test']
            ],
            'subject' => 'Test Email từ Laravel qua Brevo API',
            'htmlContent' => '<html><body style="font-family: Arial, sans-serif;">
                                <h2>Xin chào từ Camerashop!</h2>
                                <p>Đây là email kiểm tra được gửi thành công qua Brevo API.</p>
                                <p>Vui lòng kiểm tra hộp thư đến của bạn.</p>
                              </body></html>',
            // Bạn có thể thêm 'textContent' nếu cần
            // 'textContent' => 'Hello. This is a test email sent via Brevo API.'
        ];

        // 2. Thực hiện gọi API
        $response = Http::withHeaders([
            // Sử dụng biến $apiKey đã được load từ env()
            'api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', $payload);

        // 3. Xử lý phản hồi và Debugging
        if ($response->successful()) {
            // Brevo trả về status 201 Created khi gửi thành công
            $body = $response->json();
            
            // Ghi log thành công
            Log::info('Brevo Email Sent Successfully', [
                'recipient' => $payload['to'][0]['email'],
                'messageId' => $body['messageId'] ?? 'N/A'
            ]);

            return response()->json([
                'message' => 'Email đã được gửi thành công!',
                'messageId' => $body['messageId'] ?? 'N/A' // Brevo trả về messageId
            ], 200);

        } else {
            // Gửi thất bại: Ghi lại chi tiết lỗi để debug
            $statusCode = $response->status();
            $errorBody = $response->json();

            Log::error('Brevo Email Sending Failed', [
                'status_code' => $statusCode,
                'error_response' => $errorBody,
                'payload' => $payload // Ghi lại payload đã gửi để kiểm tra
            ]);

            return response()->json([
                'error' => 'Gửi Email thất bại.',
                'status_code' => $statusCode,
                'brevo_error_details' => $errorBody, // Nội dung lỗi từ Brevo
                'debugging_tip' => 'Kiểm tra Brevo Dashboard (Logs) và đảm bảo email người gửi đã được xác thực.'
            ], $statusCode);
        }
    }
}
