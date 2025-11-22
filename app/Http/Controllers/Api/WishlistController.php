<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wishlist;
use App\Models\Product; // Cần dùng để kiểm tra sản phẩm tồn tại
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    /**
     * Lấy danh sách yêu thích của người dùng hiện tại.
     * Endpoint: GET /api/wishlist
     */
    public function show()
    {
        // Eager load thông tin sản phẩm cần thiết (id, name, slug, price, stock)
        $wishlistItems = Auth::user()->wishlistItems()
            ->with('product:id,name,slug,price,stock_quantity')
            ->get();

        return response()->json([
            'message' => 'Lấy danh sách yêu thích thành công.',
            'items' => $wishlistItems,
            'total' => $wishlistItems->count(),
        ]);
    }

    /**
     * Thêm sản phẩm vào danh sách yêu thích.
     * Endpoint: POST /api/wishlist/add/{product_id}
     */
    public function add($productId)
    {
        $user = Auth::user();

        // 1. Kiểm tra sản phẩm có tồn tại không
        if (!Product::where('id', $productId)->exists()) {
            return response()->json([
                'message' => 'Sản phẩm không tồn tại.',
            ], 404);
        }

        // 2. Kiểm tra xem sản phẩm đã có trong wishlist chưa
        $existingItem = $user->wishlistItems()
            ->where('product_id', $productId)
            ->first();

        if ($existingItem) {
            return response()->json([
                'message' => 'Sản phẩm đã có trong danh sách yêu thích.',
                'item' => $existingItem,
            ], 200); // Trả về 200 vì về mặt logic, yêu cầu đã được đáp ứng
        }

        // 3. Thêm mới
        $wishlistItem = $user->wishlistItems()->create([
            'product_id' => $productId,
        ]);

        return response()->json([
            'message' => 'Đã thêm sản phẩm vào danh sách yêu thích thành công.',
            'item' => $wishlistItem->load('product:id,name,slug,price,stock_quantity'),
        ], 201);
    }

    /**
     * Xóa sản phẩm khỏi danh sách yêu thích.
     * Endpoint: DELETE /api/wishlist/remove/{product_id}
     */
    public function remove($productId)
    {
        $user = Auth::user();

        // 1. Tìm và xóa mục Wishlist
        $deletedCount = $user->wishlistItems()
            ->where('product_id', $productId)
            ->delete();

        if ($deletedCount === 0) {
            return response()->json([
                'message' => 'Sản phẩm không có trong danh sách yêu thích hoặc không tồn tại.',
            ], 404);
        }

        // 2. Trả về phản hồi thành công
        return response()->json([
            'message' => 'Đã xóa sản phẩm khỏi danh sách yêu thích thành công.',
        ], 200);
    }

    /**
     * Chuyển sản phẩm từ Wishlist sang Giỏ hàng.
     * Endpoint: POST /api/wishlist/move-to-cart/{product_id}
     */
    public function moveToCart($productId)
    {

        $user = Auth::user();

        // 2. Kiểm tra sản phẩm có tồn tại trong Wishlist không
        $wishlistItem = $user->wishlistItems()
            ->where('product_id', $productId)
            ->first();

        if (!$wishlistItem) {
            return response()->json([
                'message' => 'Sản phẩm không có trong danh sách yêu thích.',
            ], 404);
        }

        // 3. Chuẩn bị Request để gọi CartController::add
        $request = Request::create('/api/cart/add', 'POST', [
            'product_id' => $productId,
            'quantity' => 1, // Mặc định chuyển 1 sản phẩm
        ]);
        
        // 4. Gọi CartController::add để thêm vào giỏ hàng (sử dụng IoC Container)
        $cartController = app(CartController::class);
        $cartResponse = $cartController->add($request);

        // 5. Kiểm tra phản hồi từ CartController
        // Nếu CartController::add thành công (200/201), tiến hành xóa khỏi Wishlist
        if ($cartResponse->status() === 200 || $cartResponse->status() === 201) {
            
            // Xóa khỏi Wishlist sau khi thêm vào Cart thành công
            $wishlistItem->delete();

            return response()->json([
                'message' => 'Đã chuyển sản phẩm vào giỏ hàng thành công và xóa khỏi danh sách yêu thích.',
                'cart_response' => $cartResponse->getData(true), // Trả về chi tiết giỏ hàng mới
            ], 200);
        }

        // Nếu CartController::add thất bại (ví dụ: Hết hàng - 422, Sản phẩm không tồn tại - 404)
        return $cartResponse;
    }
}