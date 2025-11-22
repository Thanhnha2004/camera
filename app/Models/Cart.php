<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cart extends Model
{
    use HasFactory;

    // Tên bảng
    protected $table = 'carts';

    // Các cột có thể gán giá trị hàng loạt
    protected $fillable = [
        'user_id',
        'session_id',
        'product_id',
        'quantity',
        'price', // Giá sản phẩm tại thời điểm thêm vào giỏ hàng
    ];

    /**
     * Mối quan hệ: Một mục giỏ hàng thuộc về một sản phẩm.
     * Dùng để lấy thông tin chi tiết của sản phẩm (tên, slug, v.v.).
     */
    public function product(): BelongsTo
    {
        // Liên kết product_id với id trong bảng products
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    /**
     * Mối quan hệ: Một mục giỏ hàng thuộc về một người dùng (nếu user_id không null).
     */
    public function user(): BelongsTo
    {
        // Giả định Model User là App\Models\User
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    
}