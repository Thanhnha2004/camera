<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    /**
     * Tên bảng liên kết với Model.
     * @var string
     */
    protected $table = 'brands';

    /**
     * Khóa chính của bảng.
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Các thuộc tính có thể gán hàng loạt (mass assignable).
     * Bảng này có các cột: id, name, slug, logo, is_active, created_at, updated_at
     * @var array
     */
    protected $fillable = [
        'name',
        'slug',
        'logo',
        'is_active'
    ];

    /**
     * Tắt tính năng tự động quản lý timestamps (created_at và updated_at) nếu cần.
     * Nếu BẬT, hệ thống sẽ tự động cập nhật chúng.
     * @var bool
     */
    // public $timestamps = true; // Mặc định là true
    
    // Cột 'is_active' có kiểu tinyint(1), thường được coi là boolean
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(Product::class); // giả sử bạn có model Product
    }

}