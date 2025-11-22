<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // GET /api/categories (tree view)
    public function index()
    {
        $categories = Category::whereNull('parent_id')
            ->with('children')
            ->get();

        return response()->json($categories);
    }

    // GET /api/categories/{slug}
    public function showBySlug($slug)
    {
        $category = Category::where('slug', $slug)
            ->with('children')
            ->firstOrFail();

        return response()->json($category);
    }

    // GET /api/categories/{id}/products
    public function products($id)
    {
        $category = Category::findOrFail($id);
        $products = $category->products()->get();

        return response()->json([
            'category' => $category->name,
            'products' => $products
        ]);
    }
}