<?php

namespace App\Models;

// Import thêm cho Sanctum và Verify Email
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany; // Import HasMany

class User extends Authenticatable
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
        'role',   // Thêm vào để cho phép gán mass assignment nếu cần
        'status', // Thêm vào để cho phép gán mass assignment nếu cần
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
    
    // Bổ sung: Nếu có bảng orders
    // public function orders(): HasMany
    // {
    //     return $this->hasMany(Order::class, 'user_id');
    // }

    // --- CÁC PHƯƠNG THỨC HỖ TRỢ ---

    /**
     * Kiểm tra xem người dùng có phải là Admin hay không (Dùng cho Admin Middleware).
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}