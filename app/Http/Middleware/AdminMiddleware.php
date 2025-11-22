<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Xử lý request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Kiểm tra xem người dùng đã được xác thực (đã đăng nhập) chưa
        if (!auth()->check()) {
            // Trường hợp: Chưa đăng nhập
            return response()->json([
                'message' => 'Chưa đăng nhập.'
            ], 401);
        }

        // 2. Kiểm tra quyền Admin (Giả định: cột role = 1)
        if (auth()->user()->role !== 'admin') {
            // Trường hợp: Đã đăng nhập nhưng không có quyền Admin
            return response()->json([
                'message' => 'Bạn không có quyền truy cập.'
            ], 403); // 403 Forbidden
        }

        // 3. Nếu mọi thứ đều OK, cho phép request đi tiếp
        return $next($request);
    }
}
