<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    /**
     * @var array Trạng thái được phép hủy đơn hàng.
     */
    private const ALLOWED_CANCEL_STATUS = [
        Order::STATUS_PENDING,
        Order::STATUS_CONFIRMED
    ];

    /**
     * Lấy danh sách các đơn hàng của người dùng hiện tại (Lịch sử đơn hàng).
     * Bổ sung tính năng lọc theo status và phân trang.
     * @param \Illuminate\Http\Request $request
     */
    public function index(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $status = $request->query('status'); // Lấy tham số 'status' từ query string

        $query = Order::where('user_id', $userId)
            ->with('items.product');

        // 1. Lọc theo Status nếu tham số tồn tại
        if ($status) {
            // Đảm bảo status là hợp lệ (có thể thêm validation chi tiết hơn)
            $query->where('status', $status);
        }

        // 2. Phân trang, sắp xếp và trả về
        $orders = $query->latest()
            ->paginate(10);

        return response()->json($orders);
    }

    /**
     * Xem chi tiết một đơn hàng cụ thể. Tìm kiếm đơn hàng bằng ID.
     *
     * @param  string $id ID của đơn hàng cần xem
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        // 1. Tìm đơn hàng bằng ID. Dùng findOrFail() để tự động trả về 404 nếu không tìm thấy.
        $order = Order::findOrFail($id);

        // 2. Bảo vệ: Chỉ chủ sở hữu đơn hàng mới được xem
        if ($order->user_id !== Auth::id()) {
            return response()->json(['message' => 'Bạn không có quyền truy cập đơn hàng này.'], 403);
        }

        // 3. Tải các mối quan hệ cần thiết và trả về
        // Thông tin vận chuyển (shipping info) đã được lưu trực tiếp trên model Order
        // nên nó sẽ tự động được đưa vào response JSON.
        $order->load('items.product');
        return response()->json($order);
    }

    /**
     * POST /api/orders
     * Tạo một đơn hàng mới từ giỏ hàng của người dùng.
     */
    public function store(Request $request): JsonResponse
    {

        // 2. Định nghĩa quy tắc kiểm tra
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:20',
            'shipping_address' => 'required|string|max:500',
            'payment_method' => 'required|string|in:cod,vnpay,momo',
            'customer_note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = Auth::id();

        // 3. Lấy Giỏ hàng và Kiểm tra Tồn kho
        $cartItems = Cart::where('user_id', $userId)->with('product')->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Giỏ hàng của bạn đang trống.'], 400);
        }

        DB::beginTransaction();

        try {
            $totalAmount = 0;
            $subtotalAmount = 0;
            $orderItemsData = [];

            foreach ($cartItems as $cartItem) {
                $product = $cartItem->product;
                $requestedQuantity = $cartItem->quantity;

                if (!$product || $product->stock_quantity < $requestedQuantity) {
                    DB::rollBack();
                    $productName = $product->name ?? 'Không xác định';
                    $availableStock = $product->stock_quantity ?? 0;
                    return response()->json([
                        'message' => "Lỗi: Sản phẩm {$productName} không đủ hàng.",
                        'available_stock' => $availableStock,
                    ], 400);
                }

                $itemTotal = $requestedQuantity * $product->price;
                $subtotalAmount += $itemTotal;

                // FIX LỖI: CẬP NHẬT CÁC KHÓA ĐỂ KHỚP VỚI CỘT TRONG BẢNG `order_items`
                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'quantity' => $requestedQuantity,

                    // THÊM TRƯỜNG BẮT BUỘC BỊ THIẾU:
                    'product_name' => $product->name,

                    // ĐỔI TÊN KHÓA ĐỂ KHỚP VỚI CỘT DB (price_per_unit -> price)
                    'price' => $product->price,

                    // ĐỔI TÊN KHÓA ĐỂ KHỚP VỚI CỘT DB (total -> subtotal)
                    'subtotal' => $itemTotal,

                    // Thêm SKU và Image nếu chúng là NOT NULL
                    'product_sku' => $product->sku ?? null,
                    'product_image' => $product->image_url ?? $product->image ?? null, // Giả định tên cột image trong Product
                ];
            }

            $shippingFee = 0;
            $FREE_SHIP_THRESHOLD = 500000; // Miễn phí nếu Subtotal > 500k VNĐ
            $DEFAULT_SHIPPING_FEE = 30000;  // Phí cố định 30k

            if ($subtotalAmount < $FREE_SHIP_THRESHOLD) {
                $shippingFee = $DEFAULT_SHIPPING_FEE;
            } else {
                $shippingFee = 0;
            }

            $discountAmount = 0;
            $couponCode = $request->input('coupon_code');

            if ($couponCode) {
                if ($couponCode === 'DOAN2025') {
                    $discountAmount = $subtotalAmount * 0.20; // Giảm 20%
                    $discountAmount = min($discountAmount, 100000);
                }
            }

            $totalAmount = $subtotalAmount + $shippingFee - $discountAmount;

            $orderNumber = 'ORD-' . now()->format('YmdHis') . str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);

            // 4. Tạo đơn hàng mới
            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $userId,

                // THÔNG TIN KHÁCH HÀNG
                'customer_name' => $request->input('customer_name'),
                'customer_email' => $request->input('customer_email'),
                'customer_phone' => $request->input('customer_phone'),

                // THÔNG TIN VẬN CHUYỂN CHI TIẾT
                'shipping_address' => $request->input('shipping_address'),
                'shipping_ward' => $request->input('shipping_ward', ''),
                'shipping_district' => $request->input('shipping_district', ''),
                'shipping_city' => $request->input('shipping_city', ''),

                // TÀI CHÍNH
                'subtotal' => $subtotalAmount,
                'shipping_fee' => $shippingFee,
                'discount_amount' => $discountAmount,
                'total' => $totalAmount,
                'coupon_code' => $request->input('coupon_code'),

                // TRẠNG THÁI
                'payment_method' => $request->input('payment_method'),
                'status' => Order::STATUS_PENDING,
                'notes' => $request->input('notes'),
            ]);

            // 5. Lưu Order Items và Trừ Stock
            foreach ($orderItemsData as $itemData) {
                $itemData['order_id'] = $order->id;

                // OrderItem::create() giờ đây nhận đủ 6 trường bắt buộc
                OrderItem::create($itemData);

                // Trừ stock_quantity
                Product::where('id', $itemData['product_id'])->decrement('stock_quantity', amount: $itemData['quantity']);
            }

            // 6. Xóa giỏ hàng
            Cart::where('user_id', $userId)->delete();

            DB::commit();

            return response()->json([
                'message' => 'Đơn hàng đã được tạo thành công!',
                'order_number' => $order->order_number,
                'order_id' => $order->id,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Order creation failed for user {$userId}: " . $e->getMessage());

            return response()->json([
                'message' => 'Đã xảy ra lỗi trong quá trình tạo đơn hàng.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/orders/{id}/cancel
     * Hủy đơn hàng và cập nhật lại tồn kho sản phẩm.
     *
     * @param string $id ID của đơn hàng cần hủy
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel(string $id): JsonResponse
    {
        $userId = Auth::id();

        // 1. Tìm và Tải Order cùng Items
        $order = Order::where('id', $id)
            ->where('user_id', $userId) // Chỉ chủ sở hữu mới được hủy
            ->with('items')
            ->firstOrFail(); // Nếu không tìm thấy, tự động trả về 404

        // 2. Validation: Chỉ cho phép hủy nếu trạng thái là 'pending' hoặc 'confirmed'
        if (!in_array($order->status, self::ALLOWED_CANCEL_STATUS)) {
            $allowedStatus = implode(', ', self::ALLOWED_CANCEL_STATUS);
            return response()->json([
                'message' => "Lỗi: Đơn hàng ở trạng thái '{$order->status}' không thể hủy. Chỉ có thể hủy đơn hàng ở trạng thái ({$allowedStatus})."
            ], 400);
        }

        DB::beginTransaction();

        try {
            // 3. Khôi phục Tồn kho
            foreach ($order->items as $item) {
                // Tăng stock_quantity trở lại
                Product::where('id', $item->product_id)->increment('stock_quantity', $item->quantity);
            }

            // 4. Cập nhật trạng thái đơn hàng: Sử dụng hằng số STATUS_FAILED (được bạn định nghĩa là 'cancelled')
            $order->update([
                'status' => Order::STATUS_FAILED,
                'updated_at' => now(), // Đặt timestamp hủy
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Đơn hàng đã được hủy thành công. Tồn kho đã được cập nhật lại.',
                'order' => $order->load('items.product')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Order cancellation failed for order {$id}: " . $e->getMessage());

            return response()->json([
                'message' => 'Đã xảy ra lỗi trong quá trình hủy đơn hàng.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/orders/{id}/reorder
     * Đặt hàng lại bằng cách thêm tất cả các sản phẩm của đơn hàng cũ vào giỏ hàng.
     *
     * @param string $id ID của đơn hàng cũ
     * @return \Illuminate\Http\JsonResponse
     */
    public function reorder(string $id): JsonResponse
    {
        $userId = Auth::id();

        // 1. Lấy Order và Order Items
        $order = Order::where('id', $id)
            ->where('user_id', $userId)
            ->with('items')
            ->firstOrFail();

        DB::beginTransaction();

        try {
            $outOfStockItems = [];
            $productsToCart = [];

            // 2. Kiểm tra tồn kho và chuẩn bị dữ liệu cho giỏ hàng
            foreach ($order->items as $item) {
                $product = Product::find($item->product_id);

                // Kiểm tra sản phẩm còn tồn tại và đủ hàng
                if (!$product || $product->stock_quantity < $item->quantity) {
                    $outOfStockItems[] = $item->product_name;
                } else {
                    $productsToCart[] = [
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'price' => $item->price
                    ];
                }
            }

            // Nếu có sản phẩm hết hàng, rollback và thông báo
            if (!empty($outOfStockItems)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Lỗi: Một số sản phẩm trong đơn hàng không đủ tồn kho để đặt lại.',
                    'out_of_stock_products' => $outOfStockItems
                ], 400);
            }

            // 3. Thêm/Cập nhật sản phẩm vào giỏ hàng
            foreach ($productsToCart as $data) {
                $cartItem = Cart::firstOrNew([
                    'user_id' => $userId,
                    'product_id' => $data['product_id']
                ]);

                // Cộng dồn số lượng. Nếu đã có trong giỏ, cộng thêm số lượng cũ.
                $cartItem->quantity += $data['quantity'];
                $cartItem->price = $data['price'];
                $cartItem->save();
            }

            DB::commit();

            // 4. Trả về thông tin giỏ hàng đã được cập nhật
            $newCart = Cart::where('user_id', $userId)->with('product')->get();

            return response()->json([
                'message' => 'Các sản phẩm từ đơn hàng cũ đã được thêm vào giỏ hàng của bạn.',
                'cart_items' => $newCart
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Order reorder failed for order {$id}: " . $e->getMessage());

            return response()->json([
                'message' => 'Đã xảy ra lỗi trong quá trình đặt hàng lại.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}