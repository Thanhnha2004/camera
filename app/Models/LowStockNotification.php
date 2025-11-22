<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class LowStockNotification
 * Đại diện cho thông báo tồn kho thấp.
 *
 * @property int $id
 * @property int $product_id
 * @property string $message
 * @property bool $is_read
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class LowStockNotification extends Model
{
    use HasFactory;

    // Định nghĩa các trường có thể được gán hàng loạt (mass assignable)
    protected $fillable = [
        'product_id',
        'message',
        'is_read',
    ];

    // Định nghĩa ép kiểu cho các thuộc tính
    protected $casts = [
        // Ép kiểu 'is_read' thành boolean (true/false)
        'is_read' => 'boolean',
    ];

    /**
     * Quan hệ BelongsTo: Một thông báo thuộc về (liên kết với) một Sản phẩm duy nhất.
     *
     * @return BelongsTo
     */
    public function product(): BelongsTo
    {
        // Tự động tìm kiếm khóa ngoại 'product_id'
        return $this->belongsTo(Product::class);
    }
}