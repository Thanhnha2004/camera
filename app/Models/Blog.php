<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Blog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'featured_image',
        'author_id',
        'status',
        'views_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'views_count' => 'integer',
        'status' => 'string',
    ];

    /**
     * Mối quan hệ: Bài viết thuộc về một Tác giả (User).
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // Bạn có thể thêm các mối quan hệ khác sau này, ví dụ: Tags, Categories, Comments.
}