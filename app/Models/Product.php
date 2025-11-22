<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /**
     * Tên bảng liên kết với Model.
     * @var string
     */
    protected $table = 'products';

    /**
     * Các thuộc tính có thể được gán hàng loạt (mass assignable).
     * Bao gồm hầu hết các cột có thể thay đổi/điền bởi người dùng.
     * @var array
     */
    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'short_description',
        'description',
        'price',
        'compare_price',
        'stock_quantity',
        'product_condition',
        'status',
        'is_featured',
        'views_count',
        'sales_count',
        'rating_average',
        'reviews_count',
    ];

    /**
     * Tự động chuyển đổi kiểu dữ liệu của một số cột.
     * @var array
     */
    protected $casts = [
        'category_id' => 'integer',
        'brand_id' => 'integer',
        'price' => 'decimal:2', // decimal(15,2)
        'compare_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_featured' => 'boolean', // tinyint(1)
        'views_count' => 'integer',
        'sales_count' => 'integer',
        'rating_average' => 'decimal:2', // decimal(3,2)
        'reviews_count' => 'integer',
    ];

    // --- Mối quan hệ (Relationships) ---

    /**
     * Quan hệ Belongs To với Brand.
     */
    public function brand()
    {
        // Giả định khóa ngoại là brand_id (mặc định)
        return $this->belongsTo(Brand::class);
    }

    /**
     * Quan hệ Belongs To với Category.
     */
    public function category()
    {
        // Giả định khóa ngoại là category_id (mặc định)
        return $this->belongsTo(Category::class);
    }

    /**
     * Quan hệ Has Many với ProductImage.
     * Lấy tất cả hình ảnh của sản phẩm này.
     * (Model ProductImage phải tồn tại như đã tạo ở lần trước)
     */
    public function images()
    {
        // product_id là khóa ngoại trong bảng product_images
        return $this->hasMany(ProductImage::class, 'product_id');
    }

    /**
     * Quan hệ Has Many với ProductSpecification (specifications) - GIẢ ĐỊNH.
     * Bạn cần đảm bảo có bảng và Model 'ProductSpecification'.
     */
    public function specifications()
    {
        return $this->hasMany(ProductSpecification::class, 'product_id');
    }

    /**
     * Quan hệ Belongs To Many với User thông qua bảng 'wishlists'.
     * Mối quan hệ: Sản phẩm này nằm trong Wishlist của nhiều người dùng.
     */
    public function wishlistedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'wishlists', 'product_id', 'user_id')
            ->withTimestamps(); // Giả định bảng 'wishlists' có timestamps
    }

    /**
     * Quan hệ Has Many với OrderItem.
     * Lấy tất cả các mục chi tiết đơn hàng có chứa sản phẩm này.
     * Đây là cách để theo dõi số lượng bán (sales_count) và lịch sử mua hàng.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_id');
    }

    /**
     * Mối quan hệ: Sản phẩm có thể có nhiều đánh giá (product HAS MANY reviews).
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Quan hệ HasMany với LowStockNotification.
     * Một sản phẩm có thể có nhiều thông báo tồn kho thấp được tạo ra.
     *
     * @return HasMany
     */
    public function lowStockNotifications(): HasMany
    {
        // Khóa ngoại mặc định là 'product_id' trong bảng low_stock_notifications
        return $this->hasMany(LowStockNotification::class);
    }
}