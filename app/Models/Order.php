<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    // --- CÁC HẰNG SỐ TRẠNG THÁI ĐƠN HÀNG ---
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPING = 'shipping';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'cancelled';
    
    // --- CÁC HẰNG SỐ PHƯƠNG THỨC THANH TOÁN ---
    public const PAYMENT_COD = 'COD';
    public const PAYMENT_VNPAY = 'VNPAY';
    public const PAYMENT_MOMO = 'MOMO';

    /**
     * Tên bảng liên kết với Model.
     * @var string
     */
    protected $table = 'orders';

    /**
     * Các trường có thể được gán hàng loạt (mass assignable).
     * Dựa trên cấu trúc bảng của bạn (image_36f608.png)
     * @var array
     */
    protected $fillable = [
        'user_id',
        'order_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_address',
        'shipping_ward',
        'shipping_district',
        'shipping_city',
        'subtotal',
        'shipping_fee',
        'discount_amount',
        'total',
        'coupon_code',
        'payment_method',
        'payment_status',
        'status', // trạng thái đơn hàng (pending, confirmed, etc.)
        'customer_note',
    ];

    /**
     * Ép kiểu các trường Decimal sang float.
     * @var array
     */
    protected $casts = [
        'subtotal' => 'float',
        'shipping_fee' => 'float',
        'discount_amount' => 'float',
        'total' => 'float',
    ];


    // --- Mối quan hệ ---

    /**
     * Đơn hàng thuộc về một người dùng (User).
     * user_id có thể NULL nếu là khách hàng vãng lai (guest checkout).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Đơn hàng có nhiều chi tiết đơn hàng (Order Items).
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Đơn hàng có một chi tiết thanh toán (Giả định có bảng payments).
     */
    public function payment(): HasOne
    {
        // Giả sử bảng payments có cột order_id
        return $this->hasOne(Payment::class);
    }
}