<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product; // Cần dùng Model Product để kiểm tra stock, giá, và tồn kho
use Illuminate\Support\Carbon;
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
        $detailedCart = $this->getDetailedCartResponse();

        // Trả về Response theo cấu trúc chi tiết
        return response()->json([
            'message' => 'Lấy giỏ hàng thành công.',
            'cart' => $detailedCart,
        ], 200);
    }

    /**
     * Thêm sản phẩm vào giỏ hàng.
     * Endpoint: POST /api/cart/add
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
        $requestedQuantity = $request->input('quantity');

        // 2. Lấy thông tin sản phẩm và Cart Item hiện tại
        $product = Product::find($productId); // Có tồn tại (do 'exists:products,id')
        $cartItem = $user->cartItems()->where('product_id', $productId)->first();

        // 3. Kiểm tra Tồn kho (Stock Validation)
        $existingQuantity = $cartItem ? $cartItem->quantity : 0;
        $newTotalQuantity = $existingQuantity + $requestedQuantity;

        $validationResult = $this->checkStockAvailability($product, $newTotalQuantity);
        if ($validationResult !== true) {
            return $validationResult; // Trả về lỗi 422
        }

        // 4. Xử lý DB Cart
        $currentPrice = $product->price;

        if ($cartItem) {
            // Cập nhật số lượng mới đã được validation
            $cartItem->quantity = $newTotalQuantity;
            $cartItem->price = $currentPrice; // Cập nhật lại giá theo giá mới nhất
            $cartItem->save();
        } else {
            // Thêm mới mục giỏ hàng
            $user->cartItems()->create([
                'product_id' => $productId,
                'quantity' => $requestedQuantity,
                'price' => $currentPrice,
                'session_id' => null, // Giữ nguyên trường này
            ]);
        }

        // 5. Trả về giỏ hàng mới nhất
        return response()->json([
            'message' => 'Sản phẩm đã được thêm vào giỏ hàng thành công.',
            'cart' => $this->getDetailedCartResponse(),
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
            'quantity' => 'required|integer|min:0', // Cho phép quantity = 0 để xóa
        ]);

        $newQuantity = $request->input('quantity');
        $user = Auth::user();

        // 2. Tìm Cart Item và Kiểm tra quyền sở hữu
        $cartItem = $user->cartItems()->where('id', $id)->first();

        if (!$cartItem) {
            return response()->json([
                'message' => 'Mục giỏ hàng không tồn tại hoặc không thuộc về người dùng này.',
            ], 404);
        }

        // 3. Nếu số lượng mới là 0, tiến hành xóa
        if ($newQuantity === 0) {
            $cartItem->delete();
            return response()->json([
                'message' => 'Sản phẩm đã bị xóa khỏi giỏ hàng.',
                'cart' => $this->getDetailedCartResponse(),
            ], 200);
        }

        // 4. Kiểm tra Stock (Số lượng yêu cầu chính là số lượng MỚI)
        $product = Product::find($cartItem->product_id);

        if (!$product) {
            $cartItem->delete();
            return response()->json(['message' => 'Sản phẩm không tồn tại trong hệ thống. Đã xóa khỏi giỏ hàng.'], 410);
        }

        // --- STOCK VALIDATION ---
        $validationResult = $this->checkStockAvailability($product, $newQuantity);
        if ($validationResult !== true) {
            return $validationResult; // Trả về lỗi 422
        }
        // --- END STOCK VALIDATION ---

        // 5. Cập nhật và Lưu
        $cartItem->quantity = $newQuantity;
        $cartItem->price = $product->price; // Cập nhật lại giá
        $cartItem->save();

        // 6. Trả về giỏ hàng mới nhất
        return response()->json([
            'message' => 'Số lượng giỏ hàng đã được cập nhật thành công.',
            'cart' => $this->getDetailedCartResponse(),
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
            'cart' => $this->getDetailedCartResponse(),
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
            'cart' => $this->getDetailedCartResponse(),
        ], 200);
    }

    /**
     * [PUT] Tăng số lượng của một mục giỏ hàng lên 1 đơn vị.
     * Endpoint: PUT /api/cart/items/{id}/increase (id là Cart Item ID)
     */
    public function increase($id)
    {
        $user = Auth::user();
        $cartItem = $user->cartItems()->where('id', $id)->first();

        if (!$cartItem) {
            return response()->json([
                'message' => 'Mục giỏ hàng không tồn tại hoặc không thuộc về người dùng này.',
            ], 404);
        }

        $newQuantity = $cartItem->quantity + 1;
        $product = Product::find($cartItem->product_id);

        if (!$product) {
            $cartItem->delete();
            return response()->json(['message' => 'Sản phẩm không tồn tại trong hệ thống. Đã xóa khỏi giỏ hàng.'], 410);
        }

        // --- STOCK VALIDATION ---
        $validationResult = $this->checkStockAvailability($product, $newQuantity);
        if ($validationResult !== true) {
            return $validationResult; // Trả về lỗi 422
        }
        // --- END STOCK VALIDATION ---

        $cartItem->quantity = $newQuantity;
        $cartItem->price = $product->price; // Cập nhật lại giá
        $cartItem->save();

        return response()->json([
            'message' => 'Số lượng đã tăng lên 1.',
            'cart' => $this->getDetailedCartResponse(),
        ], 200);
    }

    /**
     * [PUT] Giảm số lượng của một mục giỏ hàng xuống 1 đơn vị. Nếu số lượng về 0, mục sẽ bị xóa.
     * Endpoint: PUT /api/cart/items/{id}/decrease (id là Cart Item ID)
     */
    public function decrease($id)
    {
        $user = Auth::user();
        $cartItem = $user->cartItems()->where('id', $id)->first();

        if (!$cartItem) {
            return response()->json([
                'message' => 'Mục giỏ hàng không tồn tại hoặc không thuộc về người dùng này.',
            ], 404);
        }

        $newQuantity = $cartItem->quantity - 1;
        $product = Product::find($cartItem->product_id);

        if (!$product) {
            $cartItem->delete();
            return response()->json(['message' => 'Sản phẩm không tồn tại trong hệ thống. Đã xóa khỏi giỏ hàng.'], 410);
        }

        if ($newQuantity < 1) {
            // Nếu số lượng mới <= 0, xóa item
            $cartItem->delete();
            $message = 'Sản phẩm đã bị xóa khỏi giỏ hàng.';
        } else {
            // Giảm số lượng
            $cartItem->quantity = $newQuantity;
            // Cập nhật lại giá (Mặc dù không thay đổi, nhưng giữ thói quen cập nhật)
            $cartItem->price = $product->price;
            $cartItem->save();
            $message = 'Số lượng đã giảm xuống 1.';
        }

        return response()->json([
            'message' => $message,
            'cart' => $this->getDetailedCartResponse(),
        ], 200);
    }

    /**
     * Helper: Kiểm tra tính hợp lệ của tồn kho so với số lượng yêu cầu.
     * @param Product $product Thông tin sản phẩm.
     * @param int $requestedQuantity Tổng số lượng MỚI mong muốn (sau khi add/update/increase).
     * @return true|\Illuminate\Http\JsonResponse
     */
    protected function checkStockAvailability(Product $product, int $requestedQuantity): bool|\Illuminate\Http\JsonResponse
    {
        $stock = $product->stock_quantity;
        $productName = $product->name;

        // 1. Kiểm tra nếu sản phẩm hết hàng hoàn toàn
        if ($stock <= 0) {
            return response()->json([
                'message' => "Sản phẩm '{$productName}' đã **hết hàng** (tồn kho: 0) và không thể thêm/cập nhật giỏ hàng.",
                'available_stock' => 0,
            ], 422);
        }

        // 2. Kiểm tra số lượng yêu cầu so với tồn kho
        if ($requestedQuantity > $stock) {
            return response()->json([
                'message' => "Không đủ tồn kho cho sản phẩm '{$productName}'. Số lượng yêu cầu là **{$requestedQuantity}** nhưng chỉ còn **{$stock}** sản phẩm trong kho.",
                'available_stock' => $stock,
            ], 422);
        }

        return true;
    }

    /**
     * Hàm tiện ích để lấy chi tiết giỏ hàng, bao gồm tính toán tổng và kiểm tra tồn kho.
     * @return array
     */
    protected function getDetailedCartResponse()
    {
        $user = Auth::user();

        // Eager load Mở rộng: Tải Product, Brand, và Images chỉ trong MỘT truy vấn
        $cartItems = $user->cartItems()
            ->with([
                'product' => function ($query) {
                    // Chọn các trường cần thiết của Product
                    $query->select('id', 'name', 'slug', 'price', 'sku', 'stock_quantity', 'brand_id');
                },
                'product.brand:id,name', // Tải thông tin Brand
                'product.images:id,product_id,image_url,is_primary' // Tải thông tin Images
            ])
            ->get();

        $subtotal = 0;
        $totalItems = 0;

        $itemsResponse = $cartItems->map(function ($item) use (&$subtotal, &$totalItems) {

            $product = $item->product;
            // Kiểm tra product có tồn tại không (phòng trường hợp product bị xóa sau khi thêm vào giỏ)
            $stock = $product ? $product->stock_quantity : 0;
            $price = $item->price;

            // Trạng thái Sẵn sàng
            $isOutOfStock = $item->quantity > $stock || $stock <= 0;
            // Xác định trạng thái chi tiết hơn
            $availabilityStatus = $isOutOfStock ? 'OUT_OF_STOCK' : ($stock < 5 ? 'LOW_STOCK' : 'AVAILABLE');

            $itemSubtotal = $item->quantity * $price;
            $subtotal += $itemSubtotal;
            $totalItems += $item->quantity;

            // Lấy URL của ảnh chính
            $mainImage = $product ? $product->images->where('is_main', true)->first() : null;
            $imageUrl = $mainImage ? $mainImage->url : null;

            // Chuẩn bị dữ liệu product tối giản
            $productData = $product ? $product->toArray() : [
                'name' => 'Sản phẩm đã bị xóa',
                'sku' => 'N/A'
            ];

            // Xóa các quan hệ không cần thiết trả về
            if (isset($productData['images'])) {
                unset($productData['images']);
            }
            if (isset($productData['brand'])) {
                unset($productData['brand']);
            }

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price_at_checkout' => $price,
                'item_subtotal' => round($itemSubtotal, 2),

                // Thông tin Sản phẩm đã được tối giản
                'product' => array_merge($productData, [
                    'brand_name' => $product && $product->brand ? $product->brand->name : 'N/A',
                    'main_image_url' => $imageUrl,
                ]),

                // Trạng thái Tồn kho
                'stock_info' => [
                    'available_stock' => $stock,
                    'is_out_of_stock' => $isOutOfStock,
                    'availability_status' => $availabilityStatus,
                    'warning_message' => $isOutOfStock ? "Số lượng trong giỏ ({$item->quantity}) vượt quá tồn kho ({$stock}) hoặc sản phẩm đã hết hàng." : null,
                ],
            ];
        });

        // Ước tính thời gian giao hàng (Dựa trên trạng thái giỏ hàng)
        $deliveryEstimate = $this->calculateDeliveryTime(hasItems: $cartItems->count() > 0);

        return [
            'items' => $itemsResponse,
            'subtotal' => round($subtotal, 2),
            'total_items' => $totalItems,
            'total_unique_items' => $cartItems->count(),
            'delivery_estimate' => $deliveryEstimate,
        ];
    }

    /**
     * Tính toán thời gian giao hàng ước tính.
     * Logic giả định: 
     * - Nếu giỏ hàng có item: Giao hàng tiêu chuẩn 3-5 ngày làm việc
     * - Nếu giỏ hàng rỗng: Trả về thông báo
     * * @param bool $hasItems
     * @return array
     */
    protected function calculateDeliveryTime(bool $hasItems): array
    {
        if (!$hasItems) {
            return [
                'days_min' => 0,
                'days_max' => 0,
                'message' => 'Giỏ hàng rỗng, không thể ước tính giao hàng.',
            ];
        }

        // Ước tính từ ngày mai (Carbon::tomorrow())
        $minDays = 3;
        $maxDays = 5;

        $minDate = Carbon::tomorrow()->addDays($minDays)->format('d/m/Y');
        $maxDate = Carbon::tomorrow()->addDays($maxDays)->format('d/m/Y');

        return [
            'days_min' => $minDays,
            'days_max' => $maxDays,
            'message' => "Dự kiến giao hàng trong khoảng từ {$minDays} đến {$maxDays} ngày làm việc ({$minDate} - {$maxDate}).",
        ];
    }
}