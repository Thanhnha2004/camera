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

    public function showDetail($value)
    {
        // Tìm theo slug hoặc theo id
        $product = Product::with(['images', 'specifications', 'brand', 'category'])
            ->where('slug', $value)
            ->orWhere('id', $value)
            ->first();

        // Nếu không tìm thấy
        if (!$product) {
            return response()->json([
                'message' => 'Không tìm thấy sản phẩm.'
            ], 404);
        }

        // Trả về chi tiết
        return response()->json([
            'message' => 'Lấy chi tiết sản phẩm thành công.',
            'data' => $product
        ]);
    }


    

   public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string',
            'brand_id' => 'sometimes|integer|exists:brands,id',
            'category_id' => 'sometimes|integer|exists:categories,id',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $keyword = $request->q;
        $perPage = $request->per_page ?? 10;

        $query = Product::query();

        // Filter theo từ khóa
        $query->where(function($q) use ($keyword) {
            $q->where('name', 'LIKE', "%{$keyword}%")
            ->orWhere('description', 'LIKE', "%{$keyword}%")
            ->orWhere('sku', 'LIKE', "%{$keyword}%");
        });

        // Filter theo brand nếu có
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        // Filter theo category nếu có
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->paginate($perPage);

        if ($products->isEmpty() && $request->page > $products->lastPage()) {
            return response()->json([
                'message' => 'Trang vượt quá tổng số trang.',
                'data' => [],
                'total' => $products->total()
            ], 404);
        }

        return response()->json([
            'message' => 'Search results retrieved successfully',
            'data' => $products
        ]);
    }



    public function filter(Request $request)
    {
        $query = Product::query()->with(['brand', 'category', 'images']);

        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('brand_id') && $request->brand_id) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->has('price_min') && is_numeric($request->price_min)) {
            $query->where('price', '>=', $request->price_min);
        }

        if ($request->has('price_max') && is_numeric($request->price_max)) {
            $query->where('price', '<=', $request->price_max);
        }

        $perPage = $request->query('per_page', 10);
        $products = $query->paginate($perPage);

        return response()->json([
            'message' => 'Products filtered successfully',
            'data' => $products
        ]);
    }
    public function sort(Request $request)
    {
        $query = Product::query()->with(['brand', 'category', 'images']);

        $sort = $request->query('sort', 'newest');

        switch($sort) {
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            case 'popular':
                $query->orderBy('views_count', 'desc');
                break;
            case 'best_selling':
                $query->orderBy('sales_count', 'desc');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->query('per_page', 10);
        $products = $query->paginate($perPage);

        return response()->json([
            'message' => 'Products sorted successfully',
            'data' => $products
        ]);
    }

   public function autocomplete(Request $request)
    {
        $keyword = $request->query('q');

        $products = Product::with(['images' => function($query) {
            $query->where('is_primary', 1); // chỉ lấy ảnh chính
        }])
        ->where('name', 'like', "%{$keyword}%")
        ->orWhere('sku', 'like', "%{$keyword}%")
        ->limit(10)
        ->get()
        ->map(function($product) {
            return [
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->price,
                'image' => $product->images->first()?->image_url, // lấy ảnh chính
            ];
        });

        return response()->json($products);
    }

   public function searchPriceRange(Request $request)
{
    $request->validate([
        'min_price' => 'required|numeric|min:0',
        'max_price' => 'required|numeric|min:0',
        'page' => 'sometimes|integer|min:1',
        'per_page' => 'sometimes|integer|min:1|max:100',
    ]);

    $minPrice = (float) $request->min_price;
    $maxPrice = (float) $request->max_price;
    $perPage = $request->per_page ?? 10;
    $page = max(1, (int) ($request->page ?? 1)); // ép page về >= 1

    $products = Product::with(['brand', 'category', 'images'])
        ->whereBetween('price', [$minPrice, $maxPrice])
        ->paginate($perPage, ['*'], 'page', $page);

    // ❗ KHÔNG dùng isEmpty()
    if ($products->total() === 0) {
        return response()->json([
            'message' => "Không có sản phẩm trong khoảng giá {$minPrice} - {$maxPrice}",
            'data' => [],
            'total' => 0
        ], 404);
    }

    return response()->json([
        'message' => "Tìm thấy sản phẩm trong khoảng {$minPrice} - {$maxPrice}",
        'data' => $products
    ]);
}





}
