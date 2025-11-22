<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory;

    /**
     * Tên bảng mà Model liên kết tới.
     * Mặc định Laravel sẽ đoán là 'addresses'.
     *
     * @var string
     */
    protected $table = 'addresses';

    /**
     * Các thuộc tính có thể được gán giá trị hàng loạt (Mass Assignable).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'address_line',
        'ward',
        'district',
        'city',
        'is_default',
    ];

    /**
     * Các thuộc tính cần được chuyển đổi kiểu dữ liệu.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Định nghĩa mối quan hệ ngược lại: Một địa chỉ thuộc về một người dùng (User).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}