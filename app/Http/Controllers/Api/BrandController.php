<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    // GET /api/brands
    public function index()
    {
        $brands = Brand::where('is_active', 1)->get();
        return response()->json($brands);
    }

    // GET /api/brands/{slug}
    public function showBySlug($slug)
    {
        $brand = Brand::where('slug', $slug)->first();
        if (!$brand) {
            return response()->json(['message' => 'Brand not found'], 404);
        }
        return response()->json($brand);
    }

    // GET /api/brands/{id}/products
    public function products($id)
    {
        $brand = Brand::find($id);
        if (!$brand) {
            return response()->json(['message' => 'Brand not found'], 404);
        }
        // Giả sử Brand có relation products
        $products = $brand->products; // cần tạo relationship trong model Brand
        return response()->json($products);
    }
}
