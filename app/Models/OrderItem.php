<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * Tên bảng liên kết với Model.
     * Dựa trên cấu trúc bảng (image_36f629.png)
     * @var string
     */
    protected $table = 'order_items';

    /**
     * Các trường có thể được gán hàng loạt.
     * @var array
     */
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',   
        'product_sku',
        'product_image',
        'quantity',
        'price',         
        'subtotal',      
        'created_at', 
        'updated_at',
    ];

    /**
     * Ép kiểu các trường Decimal sang float.
     * @var array
     */
    protected $casts = [
        'quantity' => 'integer',
        'price' => 'float',
        'subtotal' => 'float',
    ];

    // --- Mối quan hệ ---

    /**
     * Chi tiết đơn hàng thuộc về một đơn hàng (Order).
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Chi tiết đơn hàng tham chiếu đến Sản phẩm gốc (Product).
     */
    public function product(): BelongsTo
    {
        // Mặc dù OrderItem lưu trữ bản sao dữ liệu,
        // nó vẫn có thể liên kết đến bản ghi Product gốc để lấy thông tin cập nhật (nếu cần).
        return $this->belongsTo(Product::class);
    }
}