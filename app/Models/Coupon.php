<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    // Tên bảng (nếu khác chuẩn Laravel)
    protected $table = 'coupons';

    // Các cột có thể gán mass assignment
    protected $fillable = [
        'code',
        'type',
        'value',
        'min_order_value',
        'usage_limit',
        'used_count',
        'start_date',
        'end_date',
        'is_active'
    ];

    // Nếu muốn tự động cast các cột
    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'value' => 'decimal:2',
        'min_order_value' => 'decimal:2',
    ];
}
