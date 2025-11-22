<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'rating',
        'content',
        'title', // Thêm trường title từ schema review
        'helpful_count', // Thêm trường helpful_count
    ];

    protected $casts = [
        'rating' => 'integer',
        'helpful_count' => 'integer',
    ];

    /**
     * Mối quan hệ: Đánh giá thuộc về một người dùng (review BELONGS TO user).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mối quan hệ: Đánh giá thuộc về một sản phẩm (review BELONGS TO product).
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Quan hệ: Một đánh giá có nhiều lượt đánh dấu 'Hữu ích' (ReviewHelpful).
     */
    public function helpfuls(): HasMany
    {
        return $this->hasMany(ReviewHelpful::class);
    }
}