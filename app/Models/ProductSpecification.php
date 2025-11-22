<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSpecification extends Model
{
    /**
     * Tên bảng liên kết với Model.
     * @var string
     */
    protected $table = 'product_specifications';

    /**
     * Tắt tính năng tự động quản lý updated_at vì bảng này chỉ có created_at.
     * @var bool
     */
    const UPDATED_AT = null;
    
    /**
     * Các thuộc tính có thể gán hàng loạt (mass assignable).
     * @var array
     */
    protected $fillable = [
        'product_id',
        'spec_key',
        'spec_value',
    ];

    /**
     * Tự động chuyển đổi kiểu dữ liệu của một số cột.
     * @var array
     */
    protected $casts = [
        'product_id' => 'integer',
    ];

    // --- Mối quan hệ (Relationships) ---

    /**
     * Lấy thông tin Sản phẩm mà thông số này thuộc về.
     */
    public function product()
    {
        // product_id là Khóa ngoại trỏ đến bảng products
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}