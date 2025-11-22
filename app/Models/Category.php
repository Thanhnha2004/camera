<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'categories';
    protected $primaryKey = 'id';

    /**
     * Các thuộc tính có thể gán hàng loạt.
     * Bảng này có các cột: id, parent_id, name, slug, image, sort_order, is_active, created_at, updated_at
     * @var array
     */
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'image',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'parent_id' => 'integer',
        'sort_order' => 'integer',
    ];

    // --- Thiết lập quan hệ (Relationships) ---

    /**
     * Lấy danh mục cha (Parent Category) của danh mục hiện tại.
     */
    public function parent()
    {
        // Category có quan hệ BelongsTo (thuộc về) với chính nó thông qua parent_id
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Lấy các danh mục con (Child Categories) của danh mục hiện tại.
     */
    public function children()
    {
        // Category có quan hệ HasMany (có nhiều) với chính nó thông qua parent_id
        return $this->hasMany(Category::class, 'parent_id');
    }

     public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}