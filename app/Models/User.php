<?php

namespace App\Models;

// Import thêm cho Sanctum và Verify Email
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany; // Import HasMany

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'role',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        // Thêm role và status để đảm bảo chúng là chuỗi
        'role' => 'string',
        'status' => 'string',
    ];

    // --- MỐI QUAN HỆ ---

    /**
     * Định nghĩa mối quan hệ 1-nhiều với Model Address (Địa chỉ).
     */
    public function addresses(): HasMany // Dùng HasMany::class
    {
        return $this->hasMany(Address::class, 'user_id');
    }

    /**
     * Mối quan hệ: Người dùng có nhiều mục giỏ hàng.
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(Cart::class, 'user_id', 'id');
    }

    /**
     * Mối quan hệ: Người dùng có nhiều Đơn hàng (1-to-Many).
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    // --- CÁC PHƯƠNG THỨC HỖ TRỢ ---

    /**
     * Kiểm tra xem người dùng có phải là Admin hay không (Dùng cho Admin Middleware).
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Mối quan hệ: Người dùng có nhiều mục trong danh sách yêu thích.
     * Liên kết với Wishlist Model.
     */
    public function wishlistItems(): HasMany
    {
        return $this->hasMany(Wishlist::class, 'user_id');
    }

    /**
     * Mối quan hệ: Người dùng có thể có nhiều đánh giá (user HAS MANY reviews).
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Quan hệ: Một người dùng có nhiều lượt đánh dấu 'Hữu ích'.
     */
    public function helpfuls(): HasMany
    {
        return $this->hasMany(ReviewHelpful::class);
    }

    /**
     * Mối quan hệ: Một User có thể viết nhiều Blog. (One-to-Many)
     * Dùng HasMany để định nghĩa tác giả của nhiều bài blog.
     * Cột foreign key là 'author_id' trong bảng 'blogs'.
     */
    public function blogs(): HasMany
    {
        return $this->hasMany(Blog::class, 'author_id');
    }
}