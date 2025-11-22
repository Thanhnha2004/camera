<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Giả định Model này tồn tại để lưu đường dẫn ảnh vào DB
// use App\Models\ProductImage; 

class ProductImageController extends Controller
{
    /**
     * Tải lên nhiều hình ảnh cho một sản phẩm và lưu file gốc vào storage.
     * Endpoint giả định: POST /api/products/{product_id}/images
     * * @param Request $request
     * @param int $productId ID của sản phẩm cần thêm ảnh
     */
    public function uploadImages(Request $request, $productId)
    {
        // 1. Validation (Kiểm tra định dạng, kích thước, và số lượng)
        $validator = Validator::make($request->all(), [
            // images là mảng file, mỗi file trong mảng phải thoả mãn:
            'images' => 'required|array',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // Max 2MB (2048 KB). Thêm webp
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Lỗi kiểm tra dữ liệu hình ảnh.',
                'errors' => $validator->errors()
            ], 422);
        }

        $uploadedImages = [];
        $basePath = 'public/products'; // Đường dẫn gốc trong storage/app

        // 2. Xử lý từng file ảnh
        foreach ($request->file('images') as $index => $imageFile) {
            $isNewMainImage = ($index === 0); // Ảnh đầu tiên trong lô tải lên sẽ là ảnh chính MỚI

            // Đảm bảo chỉ có một ảnh chính bằng cách reset ảnh cũ 
            if ($isNewMainImage) {
                // Đặt tất cả các ảnh hiện có của sản phẩm này thành phụ (is_primary = false)
                ProductImage::where('product_id', $productId)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            // 2.1. Chuẩn bị thông tin file
            $originalName = $imageFile->getClientOriginalName();

            // 2.2. Lưu file ảnh GỐC vào storage
            // Sử dụng store() với tên thư mục: Laravel sẽ tự động tạo tên file duy nhất (hash)
            $pathOriginal = $imageFile->store($basePath);

            // 3. Ghi dữ liệu vào Database 
            ProductImage::create([
                'product_id' => $productId,
                'image_url' => Storage::url($pathOriginal), // Lấy URL công khai
                'is_primary' => $isNewMainImage,
            ]);

            // Lưu lại thông tin để trả về phản hồi
            $uploadedImages[] = [
                'original_name' => $originalName,
                'image_url' => Storage::url($pathOriginal),
                'is_primary' => $isNewMainImage,
            ];
        }

        // 4. Phản hồi thành công
        return response()->json([
            'message' => count($uploadedImages) . ' hình ảnh gốc đã được tải lên thành công cho Sản phẩm ID ' . $productId . '.',
            'product_id' => $productId,
            'images' => $uploadedImages,
        ], 200);
    }

    /**
     * [DELETE] Xóa một hình ảnh sản phẩm.
     * Endpoint: DELETE /api/admin/products/images/{id}
     * * @param int $id ID của ProductImage cần xóa
     */
    public function deleteImage($id)
    {
        // 1. Tìm kiếm hình ảnh
        $image = ProductImage::find($id);

        if (!$image) {
            return response()->json(['message' => 'Hình ảnh không tồn tại.'], 404);
        }

        $productId = $image->product_id;
        $wasMain = $image->is_primary;
        $relativePath = null;

        try {
            $urlPath = parse_url($image->image_url, PHP_URL_PATH);

            // Dòng này rất quan trọng: Lấy phần đường dẫn sau "/storage/"
            // Ví dụ: /storage/products/image.jpg -> products/image.jpg
            if ($urlPath) {
                $relativePath = Str::after($urlPath, '/storage');
            }

            // Kiểm tra sự tồn tại và xóa file trên disk 'public'
            if ($relativePath && Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);
            } else {
                Log::warning("[STORAGE] Không tìm thấy file để xóa hoặc đường dẫn bị lỗi: " . ($relativePath ?? 'NULL'));
            }
        } catch (\Exception $e) {
            Log::error("[ERROR] Lỗi khi xóa file Storage: " . $e->getMessage());
            // Vẫn tiếp tục xóa bản ghi DB dù file chưa được xóa
        }

        // 3. Xóa Bản ghi khỏi Database
        $image->delete();

        // 4. Xử lý Ảnh chính (Fallback)
        if ($wasMain) {
            $newMain = ProductImage::where('product_id', $productId)
                ->orderBy('created_at', 'asc') 
                ->first();
                
            if ($newMain) {
                $newMain->is_primary = true;
                $newMain->save();
            }
        }

        // 5. Phản hồi Kết quả
        return response()->json([
            'message' => 'Hình ảnh đã được xóa thành công.',
            'image_id' => $id,
        ], 200);
    }

    /**
     * [PUT] Đặt một hình ảnh làm ảnh chính (is_primary = true).
     * Endpoint: PUT /api/admin/products/images/{id}/set-primary
     * * @param int $id ID của ProductImage cần đặt làm chính
     */
    public function setPrimary($id)
    {
        // 1. Tìm ảnh cần đặt chính
        $image = ProductImage::find($id);

        if (!$image) {
            return response()->json(['message' => 'Hình ảnh không tồn tại.'], 404);
        }

        $productId = $image->product_id;

        // 2. Đặt tất cả ảnh khác của sản phẩm này thành phụ (is_primary = false)
        ProductImage::where('product_id', $productId)
            ->where('is_primary', true)
            ->where('id', '!=', $id) // Không reset ảnh hiện tại
            ->update(['is_primary' => false]);

        // 3. Đặt ảnh hiện tại làm ảnh chính
        $image->is_primary = true;
        $image->save();

        // 4. Phản hồi thành công
        return response()->json([
            'message' => 'Đã đặt ảnh thành ảnh chính thành công.',
            'image_id' => $id,
            'product_id' => $productId,
        ], 200);
    }
}