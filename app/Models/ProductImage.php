<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $table = 'product_images';
    protected $primaryKey = 'id';

    /**
     * Tắt tính năng tự động quản lý updated_at vì bảng này chỉ có created_at (theo cấu trúc ảnh).
     * Hoặc giữ lại nếu bạn muốn thêm updated_at sau này.
     * Lưu ý: Trong ảnh, chỉ có created_at là timestamp mặc định.
     * Nếu không có updated_at, bạn nên thêm thuộc tính sau:
     * const UPDATED_AT = null;
     */
    
    /**
     * Các thuộc tính có thể gán hàng loạt.
     * Bảng này có các cột: id, product_id, image_url, is_primary, sort_order, created_at
     * @var array
     */
    protected $fillable = [
        'product_id',
        'image_url',
        'is_primary',
        'sort_order',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'is_primary' => 'boolean', // tinyint(1) thường là boolean
        'sort_order' => 'integer',
    ];

    // --- Thiết lập quan hệ (Relationships) ---
    
    /**
     * Lấy thông tin Sản phẩm mà hình ảnh này thuộc về.
     * (Giả định có một Model Product tồn tại)
     */
    public function product()
    {
        // product_id là Khóa ngoại trỏ đến bảng products
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}