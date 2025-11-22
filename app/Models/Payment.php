<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;
    
    // Tên bảng thường là 'payments'
    protected $table = 'payments';

    protected $fillable = [
        'order_id',
        'transaction_id', // ID giao dịch từ cổng thanh toán
        'amount',
        'method',
        'status', // e.g., 'completed', 'failed', 'refunded'
        'paid_at',
    ];

    /**
     * Thanh toán thuộc về một đơn hàng (Order).
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}