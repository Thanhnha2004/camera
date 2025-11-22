<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coupon;

class CouponController extends Controller
{
    // Validate coupon
    public function validateCoupon($code, Request $request)
    {
        $order_amount = $request->order_amount ?? 0;

        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon) {
            return response()->json(['valid' => false, 'message' => 'Coupon không tồn tại'], 404);
        }

        if (!$coupon->is_active) {
            return response()->json(['valid' => false, 'message' => 'Coupon không hoạt động']);
        }

        $today = now()->toDateString();
        if ($today < $coupon->start_date->toDateString() || $today > $coupon->end_date->toDateString()) {
            return response()->json(['valid' => false, 'message' => 'Coupon đã hết hạn']);
        }

        if ($coupon->usage_limit > 0 && $coupon->used_count >= $coupon->usage_limit) {
            return response()->json(['valid' => false, 'message' => 'Coupon đã đạt giới hạn sử dụng']);
        }

        if ($order_amount < $coupon->min_order_value) {
            return response()->json(['valid' => false, 'message' => 'Chưa đủ giá trị đơn hàng tối thiểu']);
        }

        // Tính discount
        $discount = $coupon->type === 'percentage'
                    ? $order_amount * ($coupon->value / 100)
                    : $coupon->value;

        return response()->json([
            'valid' => true,
            'discount' => $discount,
            'message' => 'Coupon hợp lệ'
        ]);
    }

    // Apply coupon vào cart (session)
    public function applyCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'order_amount' => 'required|numeric'
        ]);

        $response = $this->validateCoupon($request->code, $request);
        $result = $response->getData();

        if (!$result->valid) {
            return $response;
        }

        // Lưu vào session
        session()->put('cart_coupon', [
            'code' => $request->code,
            'discount' => $result->discount
        ]);

        return response()->json([
            'message' => 'Áp dụng coupon thành công',
            'coupon' => session('cart_coupon')
        ]);
    }

    // Remove coupon khỏi cart
    public function removeCoupon()
    {
        session()->forget('cart_coupon');

        return response()->json([
            'message' => 'Đã xóa coupon khỏi giỏ hàng'
        ]);
    }
}
