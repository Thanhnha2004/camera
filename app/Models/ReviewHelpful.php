<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewHelpful extends Model
{
    use HasFactory;

    /**
     * Tên bảng trong cơ sở dữ liệu (được đặt tên là 'review_helpfuls'
     * theo đề xuất của bạn, thay vì quy ước 'review_helpfuls').
     */
    protected $table = 'review_helpfuls';

    /**
     * Các trường có thể được gán hàng loạt (Mass Assignable).
     * Bảng này dùng để lưu trữ mối quan hệ N:M giữa User và Review,
     * đồng thời mang theo thông tin 'is_helpful'.
     */
    protected $fillable = [
        'user_id',
        'review_id',
        'is_helpful', // Giá trị boolean: 1 (Hữu ích) hoặc 0 (Không hữu ích, nếu cần theo dõi cả hai trạng thái)
    ];

    /**
     * Quan hệ: Một lượt đánh dấu hữu ích thuộc về một Người dùng (User).
     * Khóa ngoại: user_id
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Quan hệ: Một lượt đánh dấu hữu ích thuộc về một Bài đánh giá (Review).
     * Khóa ngoại: review_id
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}