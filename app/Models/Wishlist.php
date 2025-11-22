<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    /**
     * Tên bảng liên kết.
     * Table name associated with the model.
     */
    protected $table = 'wishlists';

    /**
     * Các thuộc tính có thể gán hàng loạt.
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'product_id',
    ];

    /**
     * Mối quan hệ: Một mục Wishlist thuộc về một người dùng.
     * Relationship: A wishlist item belongs to a User.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mối quan hệ: Một mục Wishlist liên kết đến một sản phẩm.
     * Relationship: A wishlist item belongs to a Product.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}