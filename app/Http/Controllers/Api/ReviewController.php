<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReviewHelpful;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

// Giả định Model đã tồn tại
use App\Models\Product;
use App\Models\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    /**
     * GET /api/products/{id}/reviews
     * Lấy danh sách đánh giá cho một sản phẩm, hỗ trợ phân trang và sắp xếp.
     *
     * @param Request $request
     * @param int $productId ID của sản phẩm
     * @return JsonResponse
     */
    /**
     * GET /api/products/{id}/reviews
     * Lấy danh sách đánh giá cho một sản phẩm, hỗ trợ phân trang và sắp xếp.
     *
     * @param Request $request
     * @param int $productId ID của sản phẩm
     * @return JsonResponse
     */
    public function index(Request $request, int $productId): JsonResponse
    {
        // 1. Kiểm tra Sản phẩm
        $product = Product::find($productId);
        if (!$product) {
            return response()->json(['message' => 'Sản phẩm không tồn tại.'], 404);
        }

        // 2. Xây dựng Query cơ bản
        $reviewsQuery = $product->reviews()
            // Eager load thông tin người dùng (reviewer)
            ->with('user');

        // 3. Xử lý Sắp xếp
        $sortBy = $request->query('sort', 'newest'); // Mặc định là 'newest'

        switch ($sortBy) {
            case 'highest':
                // Sắp xếp theo điểm cao nhất (từ 5 đến 1)
                $reviewsQuery->orderBy('rating', 'desc');
                break;
            case 'lowest':
                // Sắp xếp theo điểm thấp nhất (từ 1 đến 5)
                $reviewsQuery->orderBy('rating', 'asc');
                break;
            case 'helpful':
                // Sắp xếp theo số lượt 'Hữu ích' (dùng cột helpful_count có sẵn)
                $reviewsQuery->orderBy('helpful_count', 'desc');
                break;
            case 'newest':
            default:
                // Sắp xếp theo mới nhất (cũng là mặc định)
                $reviewsQuery->orderBy('created_at', 'desc');
                break;
        }

        // 4. Phân trang
        $perPage = $request->query('limit', 10); // Mặc định 10 items/trang
        $reviews = $reviewsQuery->paginate(max(1, (int) $perPage));

        // 5. Trả về kết quả
        return response()->json($reviews);
    }

    /**
     * POST /api/products/{id}/reviews
     * Tạo một đánh giá mới cho sản phẩm (phiên bản đơn giản).
     *
     * @param Request $request
     * @param int $productId ID của sản phẩm (route parameter)
     * @return JsonResponse
     */
    public function store(Request $request, int $productId): JsonResponse
    {
        $user = auth()->user();

        $product = Product::find($productId);
        if (!$product) {
            return response()->json(['message' => 'Sản phẩm không tồn tại.'], 404);
        }

        $userId = $user->id;

        // 2. Validation Cơ bản
        $validatedData = $request->validate([
            'rating' => 'required|integer|min:1|max:5', // Rating 1-5
            'content' => 'required|string|max:2000', // Nội dung
            'title' => 'nullable|string|max:255',
        ]);

        // 3. Kiểm tra Nghiệp vụ Đơn giản: Đã đánh giá chưa?
        $existingReview = Review::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();

        if ($existingReview) {
            return response()->json([
                'message' => 'Bạn đã đánh giá sản phẩm này rồi.'
            ], 409); // 409 Conflict
        }

        // 4. Tạo Review
        try {
            $review = Review::create([
                'user_id' => $userId,
                'product_id' => $productId,
                'rating' => $validatedData['rating'],
                'content' => $validatedData['content'],
                'title' => $validatedData['title'] ?? null,
            ]);

            // 5. Cập nhật Rating
            $this->updateProductRating($product);


            return response()->json([
                'message' => 'Đánh giá đã được tạo thành công.',
                'review' => $review
            ], 201);

        } catch (\Exception $e) {
            // Ghi log lỗi
            \Log::error("Error simple creating review: " . $e->getMessage());

            return response()->json([
                'message' => 'Lỗi hệ thống khi tạo đánh giá.'
            ], 500);
        }
    }

    /**
     * Hàm hỗ trợ đơn giản để cập nhật rating sản phẩm.
     * Chỉ tính các review approved.
     *
     * @param Product $product
     * @return void
     */
    protected function updateProductRating(Product $product): void
    {
        $product->rating_average = $product->reviews()
            ->avg('rating') ?? 0;

        $product->reviews_count = $product->reviews()
            ->count();

        $product->save();
    }

    /**
     * Đánh dấu hoặc gỡ bỏ đánh dấu "Hữu ích" cho một bài đánh giá.
     * Hàm này giả định ID của review được truyền qua tham số route.
     *
     * @param int $id ID của Review cần thao tác
     * @return \Illuminate\Http\JsonResponse
     */
    public function markHelpful(int $id): JsonResponse
    {
        // Sử dụng findOrFail để tự động ném ra 404 nếu không tìm thấy Review
        $review = Review::findOrFail($id);

        // Lấy thông tin người dùng đang đăng nhập (Middleware 'auth' là bắt buộc)
        $user = auth()->user();

        // 1. Kiểm tra: Ngăn người dùng tự đánh dấu review của chính họ
        if ($review->user_id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không thể đánh dấu review của chính mình là hữu ích.',
                'helpful_count' => $review->helpful_count,
                'action' => 'none'
            ], 403); // 403 Forbidden
        }

        // Bắt đầu giao dịch cơ sở dữ liệu
        DB::beginTransaction();

        try {
            // 2. Tìm kiếm bản ghi đánh dấu hữu ích của người dùng này cho review này
            $helpful = ReviewHelpful::where('review_id', $review->id)
                ->where('user_id', $user->id)
                ->first();

            if ($helpful) {
                // TRƯỜNG HỢP 1: Người dùng đã bấm "Hữu ích" (Đang muốn gỡ bỏ/un-mark)
                $helpful->delete();
                $review->decrement('helpful_count'); // Giảm số lượng
                $action = 'un-marked';
                $message = 'Đã gỡ đánh dấu hữu ích.';
            } else {
                // TRƯỜNG HỢP 2: Người dùng chưa bấm "Hữu ích" (Đang muốn bấm/mark)
                ReviewHelpful::create([
                    'review_id' => $review->id,
                    'user_id' => $user->id,
                ]);
                $review->increment('helpful_count'); // Tăng số lượng
                $action = 'marked';
                $message = 'Cảm ơn bạn, đã đánh dấu review này là hữu ích.';
            }

            // Hoàn tất giao dịch nếu không có lỗi
            DB::commit();

            // Lấy lại review mới nhất để trả về helpful_count đã cập nhật
            $review->refresh();

            return response()->json([
                'success' => true,
                'message' => $message,
                'action' => $action,
                'helpful_count' => $review->helpful_count,
            ], 200);

        } catch (\Exception $e) {
            // Hoàn tác giao dịch nếu có lỗi
            DB::rollBack();

            // Ghi log lỗi để dễ dàng gỡ lỗi
            Log::error("Lỗi khi đánh dấu review hữu ích [Review ID: {$id}, User ID: {$user->id}]: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi hệ thống trong quá trình xử lý. Vui lòng thử lại.',
            ], 500);
        }
    }
}