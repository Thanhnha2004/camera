<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show(Request $request)
    {
        // Lấy số lượng mỗi trang (mặc định 10)
        $perPage = $request->query('per_page', 10);

        // Lấy danh sách sản phẩm, kèm brand, category, images
        $products = Product::with(['brand', 'category', 'images'])
            ->paginate($perPage);

        return response()->json([
            'message' => 'Product list retrieved successfully',
            'data' => $products
        ]);
    }

    public function showDetail($slug)
    {
        // 1. Tìm sản phẩm theo slug và Eager Load các mối quan hệ
        $product = Product::where('slug', $slug)
            ->with(['images', 'specifications', 'brand', 'category'])
            ->first();

        // 2. Kiểm tra nếu sản phẩm không tồn tại
        if (!$product) {
            return response()->json([
                'message' => 'Không tìm thấy sản phẩm.'
            ], 404);
        }

        // 3. Trả về chi tiết sản phẩm
        return response()->json([
            'message' => 'Lấy chi tiết sản phẩm thành công.',
            'data' => $product
        ]);
    }
}
