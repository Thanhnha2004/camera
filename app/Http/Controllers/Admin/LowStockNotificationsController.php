<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LowStockNotification; // Giả định Model này tồn tại

class LowStockNotificationsController extends Controller
{
    /**
     * Lấy số lượng thông báo chưa đọc (is_read = 0).
     * Thường dùng cho huy hiệu (badge) trên giao diện.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUnreadCount()
    {
        try {
            // Lấy số lượng thông báo chưa đọc
            $count = LowStockNotification::where('is_read', 0)->count();

            return response()->json([
                'success' => true,
                'message' => 'Lấy số lượng thông báo chưa đọc thành công.',
                'data' => [
                    'count' => $count,
                ],
            ]);
        } catch (\Exception $e) {
            // Ghi log lỗi nếu cần thiết
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy số lượng thông báo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lấy danh sách thông báo tồn kho thấp (có phân trang).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);
            $isReadFilter = $request->get('is_read'); // Có thể là 0 (chưa đọc), 1 (đã đọc) hoặc null (tất cả)

            $query = LowStockNotification::with(['product' => function($query) {
                // Chỉ lấy các cột cần thiết từ bảng products
                $query->select('id', 'name', 'sku', 'stock_quantity');
            }])
            ->orderBy('created_at', 'desc');

            // Áp dụng bộ lọc trạng thái đọc
            if (in_array($isReadFilter, [0, 1])) {
                $query->where('is_read', (int) $isReadFilter);
            }

            // Thực hiện phân trang
            $notifications = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách thông báo tồn kho thấp thành công.',
                'data' => $notifications,
            ]);
        } catch (\Exception $e) {
            // Ghi log lỗi nếu cần thiết
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải danh sách thông báo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Đánh dấu một thông báo là đã đọc (is_read = 1).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead($id)
    {
        try {
            // Tìm thông báo dựa trên ID
            $notification = LowStockNotification::findOrFail($id);

            // Chỉ cập nhật nếu trạng thái hiện tại là chưa đọc
            if ($notification->is_read === 0) {
                $notification->is_read = 1;
                $notification->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Thông báo đã được đánh dấu là đã đọc.',
                'data' => $notification,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông báo.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật trạng thái: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Xóa một thông báo (đánh dấu là đã xử lý/xóa khỏi danh sách).
     * Lưu ý: Trong nhiều hệ thống, việc xóa thông báo không được khuyến khích
     * mà nên dùng cột 'status' (ví dụ: 'processed') hoặc soft deletes.
     * Tuy nhiên, dựa trên cấu trúc bảng, ta sử dụng hàm delete().
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $notification = LowStockNotification::findOrFail($id);

            // Xóa thông báo khỏi database
            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Thông báo tồn kho thấp đã được xóa.',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy thông báo để xóa.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa thông báo: ' . $e->getMessage(),
            ], 500);
        }
    }
}