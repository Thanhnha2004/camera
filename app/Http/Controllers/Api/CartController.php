<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product; // Cần dùng Model Product để kiểm tra stock và giá
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    /**
     * Lấy giỏ hàng của người dùng hiện tại từ DB.
     * Endpoint: GET /api/cart
     */
    public function show()
    {
        // 1. Lấy tất cả Cart Items của người dùng hiện tại
        // Eager load thông tin sản phẩm cần thiết: id, name, slug, price, sku
        $cartItems = Auth::user()->cartItems()
            ->with('product:id,name,slug,price,sku')
            ->get();

        // 2. Tính toán tổng số lượng và tổng tiền
        $totalQuantity = $cartItems->sum('quantity');

        $totalAmount = $cartItems->sum(callback: function ($item) {
            // Tính tổng dựa trên quantity và price đã lưu trong bảng carts
            return $item->quantity * $item->price;
        });

        // 3. Trả về Response
        return response()->json([
            'message' => 'Lấy giỏ hàng thành công.',
            'items' => $cartItems,
            'total_quantity' => $totalQuantity,
            'total_amount' => $totalAmount,
        ]);
    }

    /**
     * Thêm sản phẩm vào giỏ hàng.
     * Endpoint: POST /api/cart/add
     * (Yêu cầu xác thực Sanctum)
     */
    public function add(Request $request)
    {
        $user = Auth::user();

        // 1. Validation
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $productId = $request->input('product_id');
        $quantity = $request->input('quantity');

        // 2. Kiểm tra Stock (Tồn kho)
        $product = Product::find($productId);

        if ($product->stock_quantity < $quantity) {
            return response()->json([
                'message' => 'Sản phẩm này hiện chỉ còn ' . $product->stock_quantity . ' sản phẩm trong kho.',
            ], 422);
        }

        // 3. Xử lý DB Cart
        $cartItem = $user->cartItems()
            ->where('product_id', $productId)
            ->first();

        // Lấy giá hiện tại của sản phẩm để lưu vào bảng carts
        $currentPrice = $product->price;

        if ($cartItem) {
            // 3a. Cập nhật số lượng nếu đã tồn tại
            $cartItem->quantity += $quantity;
            $cartItem->save();
        } else {
            // 3b. Thêm mới mục giỏ hàng
            $user->cartItems()->create([
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $currentPrice,
                'session_id' => null,
            ]);
        }

        // 4. Trả về giỏ hàng mới nhất
        return response()->json([
            'message' => 'Sản phẩm đã được thêm vào giỏ hàng thành công.',
            'cart' => $this->show()->original,
        ], 200);
    }

    /**
     * [PUT] Cập nhật số lượng của một mục giỏ hàng.
     * Endpoint: PUT /api/cart/items/{id} (id là Cart Item ID)
     */
    public function update(Request $request, $id)
    {
        // 1. Validation
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $quantity = $request->input('quantity');
        $user = Auth::user();

        // 2. Tìm Cart Item và Kiểm tra quyền sở hữu
        $cartItem = $user->cartItems()->where('id', $id)->first();

        if (!$cartItem) {
            return response()->json([
                'message' => 'Mục giỏ hàng không tồn tại hoặc không thuộc về người dùng này.',
            ], 404);
        }

        // 3. Kiểm tra Stock (Cần tải lại Product để lấy stock)
        $product = Product::find($cartItem->product_id);

        if ($product->stock_quantity < $quantity) {
            return response()->json([
                'message' => 'Số lượng yêu cầu (' . $quantity . ') vượt quá số lượng tồn kho: ' . $product->stock_quantity . '.',
            ], 422);
        }

        // 4. Cập nhật và Lưu
        $cartItem->quantity = $quantity;
        $cartItem->save();


        // 5. Trả về giỏ hàng mới nhất
        return response()->json([
            'message' => 'Số lượng giỏ hàng đã được cập nhật thành công.',
            'cart' => $this->show()->original,
        ], 200);
    }

    /**
     * [DELETE] Xóa một mục giỏ hàng cụ thể.
     * Endpoint: DELETE /api/cart/items/{id} (id là Cart Item ID)
     */
    public function remove($id)
    {
        $user = Auth::user();

        // 1. Tìm Cart Item và Kiểm tra quyền sở hữu
        $cartItem = $user->cartItems()->where('id', $id)->first();

        if (!$cartItem) {
            return response()->json([
                'message' => 'Mục giỏ hàng không tồn tại hoặc không thuộc về người dùng này.',
            ], 404);
        }

        // 2. Xóa mục giỏ hàng
        $cartItem->delete();

        // 3. Trả về giỏ hàng mới nhất
        return response()->json([
            'message' => 'Sản phẩm đã được xóa khỏi giỏ hàng.',
        ], 200);
    }

    /**
     * [DELETE] Xóa toàn bộ giỏ hàng của người dùng.
     * Endpoint: DELETE /api/cart
     */
    public function clear()
    {
        $user = Auth::user();

        // 1. Xóa tất cả các mục giỏ hàng liên quan đến user này
        $deletedCount = $user->cartItems()->delete();

        Log::info("Cart cleared for User ID {$user->id}. {$deletedCount} items deleted.");

        // 2. Trả về giỏ hàng rỗng
        return response()->json([
            'message' => "Đã xóa toàn bộ {$deletedCount} mục khỏi giỏ hàng.",
        ], 200);
    }
}