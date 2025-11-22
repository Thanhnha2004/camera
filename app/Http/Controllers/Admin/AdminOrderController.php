<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;

/**
 * Controller quản lý các thao tác liên quan đến Đơn hàng trong khu vực Admin.
 */
class AdminOrderController extends Controller
{
    /**
     * Lấy và hiển thị danh sách tất cả đơn hàng (có phân trang và tìm kiếm).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Khởi tạo truy vấn, eagerly load mối quan hệ user và items
        $query = Order::with(['user', 'items']);

        // 1. Lọc theo Trạng thái (status)
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // 2. Tìm kiếm theo ID hoặc Tên người dùng
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                // Tìm kiếm theo order_number
                $q->where('order_number', 'like', "%{$search}%")
                    // Hoặc tìm kiếm theo tên/email người dùng (Giả định Order belongsTo User)
                    ->orWhereHas('user', function ($subQuery) use ($search) {
                        $subQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // 3. Sắp xếp và Phân trang (mặc định sắp xếp theo thời gian tạo mới nhất)
        $sortField = $request->query('sort_field', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');

        $orders = $query->orderBy($sortField, $sortOrder)->paginate(15);

        return response()->json($orders);
    }

    /**
     * Hiển thị chi tiết một đơn hàng cụ thể.
     *
     * @param  string $id ID của đơn hàng
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id)
    {
        // Tải chi tiết đơn hàng, người dùng, và thông tin sản phẩm trong OrderItem
        $order = Order::with(['user', 'items.product'])->findOrFail($id);

        return response()->json([
            'order' => $order,
        ]);
    }

    /**
     * Cập nhật trạng thái đơn hàng và ghi lại timestamp tương ứng.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string $id ID của đơn hàng
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, string $id)
    {
        // Danh sách các trạng thái hợp lệ
        $validStatuses = [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PROCESSING,
            Order::STATUS_SHIPPING,
            Order::STATUS_DELIVERED,
            Order::STATUS_FAILED,
        ];

        // 1. Validation: Kiểm tra 'new_status' có hợp lệ không
        $request->validate([
            'new_status' => [
                'required',
                'string',
                'in:' . implode(',', $validStatuses) // Phải là một trong các hằng số
            ],
        ]);

        // 2. Tìm đơn hàng
        $order = Order::findOrFail($id);
        $newStatus = $request->input('new_status');
        $currentStatus = $order->status;

        // 3. Kiểm tra điều kiện cấm cập nhật từ trạng thái cuối
        // STATUS_DELIVERED (Đã giao) và STATUS_FAILED (Thất bại/Hủy) là các trạng thái cuối cùng.
        if ($currentStatus === Order::STATUS_DELIVERED || $currentStatus === Order::STATUS_FAILED) {
            return response()->json([
                'message' => "Lỗi: Đơn hàng đã ở trạng thái cuối cùng ({$currentStatus}). Không thể cập nhật.",
                'current_status' => $currentStatus
            ], 422);
        }

        // 4. Thực hiện cập nhật trạng thái trong Transaction
        try {
            DB::beginTransaction();

            // Cập nhật trạng thái mới
            $order->status = $newStatus;

            // XÓA BỎ LOGIC GÁN TIMESTAMP
            // (Chỉ đơn giản cập nhật trạng thái)

            $order->save();

            DB::commit();

            return response()->json([
                'message' => 'Cập nhật trạng thái đơn hàng thành công!',
                'order' => $order->refresh(),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            // Ghi log lỗi để debug
            \Log::error("Lỗi khi cập nhật trạng thái đơn hàng (ID: {$id}): " . $e->getMessage());
            return response()->json([
                'message' => 'Lỗi server khi cập nhật trạng thái đơn hàng. Vui lòng kiểm tra log.',
            ], 500);
        }
    }

    /**
     * Xóa mềm (soft delete) một đơn hàng.
     *
     * @param  string $id ID của đơn hàng
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id)
    {
        $order = Order::findOrFail($id);

        // Sử dụng Soft Deletes (nếu Order model của bạn dùng trait SoftDeletes)
        if ($order->delete()) {
            return response()->json([
                'message' => 'Đơn hàng đã được xóa (soft delete) thành công.',
            ], 200);
        }

        return response()->json([
            'message' => 'Không thể xóa đơn hàng.',
        ], 500);
    }
}