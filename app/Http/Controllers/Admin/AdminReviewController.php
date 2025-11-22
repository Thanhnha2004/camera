<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use App\Models\Review;
use App\Models\Product;

class AdminReviewController extends Controller
{
    /**
     * GET /api/admin/reviews
     * Lấy danh sách tất cả đánh giá, không cần lọc theo trạng thái.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('limit', 15);

        $reviews = Review::query()
            // Tải thông tin Người dùng và Sản phẩm
            ->with(['user', 'product'])
            // Sắp xếp mặc định theo review mới nhất
            ->orderBy('created_at', 'desc')
            ->paginate(max(1, (int)$perPage));

        return response()->json($reviews);
    }

    /**
     * DELETE /api/admin/reviews/{id}
     * Xóa một đánh giá
     *
     * @param int $id ID của đánh giá
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $review = Review::find($id);

        if (!$review) {
            return response()->json(['message' => 'Đánh giá không tồn tại.'], 404);
        }

        $product = $review->product;

        // Xóa review
        $review->delete();

        // Cập nhật Rating của Sản phẩm sau khi xóa
        if ($product) {
            $this->updateProductRating($product);
        }

        return response()->json([
            'message' => 'Đánh giá đã được xóa thành công.'
        ]);
    }

    /**
     * Hàm hỗ trợ đơn giản để cập nhật rating sản phẩm.
     *
     * @param Product $product
     * @return void
     */
    protected function updateProductRating(Product $product): void
    {
        // Tính điểm trung bình (average rating) của TẤT CẢ reviews
        $product->rating_average = $product->reviews()->avg('rating') ?? 0;

        // Đếm số lượng đánh giá
        $product->reviews_count = $product->reviews()->count();

        $product->save();
    }
}