<?php

use App\Http\Controllers\Admin\AdminBlogController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminReviewController;
use App\Http\Controllers\Admin\LowStockNotificationsController;
use App\Http\Controllers\Admin\MailController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// CÁC ROUTE CÔNG KHAI (KHÔNG CẦN TOKEN)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/login/google', [AuthController::class, 'redirect']);
Route::get('/login/google/call-back', [AuthController::class, 'googleAuthCallback']);

Route::post('/sendEmail', [MailController::class, 'sendMail']);

// CÁC ROUTE CẦN XÁC THỰC (YÊU CẦU SANCTUM TOKEN)
Route::middleware('auth:sanctum')->group(function () {

    // Lấy thông tin người dùng hiện tại (Current User Info)
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // API HỒ SƠ NGƯỜI DÙNG 
    Route::get('/profile', [UserProfileController::class, 'show']);
    Route::put('/profile', [UserProfileController::class, 'update']);
    Route::post('/profile/avatar', [UserProfileController::class, 'uploadAvatar']);
    Route::put('/profile/password', [UserProfileController::class, 'changePassword']);

    // API ĐỊA CHỈ NGƯỜI DÙNG
    Route::get('/addresses', [AddressController::class, 'show']);
    Route::put('/addresses/{id}', [AddressController::class, 'update']);
    Route::post('/addresses', [AddressController::class, 'create']);
    Route::delete('/addresses/{id}', [AddressController::class, 'delete']);
    Route::put('/addresses/{id}/set-default', [AddressController::class, 'setDefault']);

    // API SẢN PHẨM
    Route::get('/products', [ProductController::class, 'show']);
    Route::get('/products/{slug}', [ProductController::class, 'showDetail']);

    // API SẢN PHẨM CHO ADMIN
    // Áp dụng middleware 'admin' cho nhóm route này
    Route::middleware(['admin'])->prefix('admin')->group(function () {
        Route::get('products', [AdminProductController::class, 'show']);
        Route::post('/products', [AdminProductController::class, 'create']);

        Route::post('products/{product_id}/images', [ProductImageController::class, 'uploadImages']);
        Route::delete('products/images/{id}', [ProductImageController::class, 'deleteImage']);
        Route::put('products/images/{id}/set-primary', [ProductImageController::class, 'setPrimary']);

        Route::put('order/{id}/status', [AdminOrderController::class, 'updateStatus']);
        Route::get('order/status', [AdminOrderController::class, 'index']);
        Route::get('order/{id}/status', [AdminOrderController::class, 'show']);
        Route::delete('order/{id}/status', [AdminOrderController::class, 'destroy']);

        Route::get('reviews', [AdminReviewController::class, 'index']);
        Route::delete('reviews/{id}', [AdminReviewController::class, 'destroy']);

        Route::get('lowstock', [LowStockNotificationsController::class, 'index']);
        Route::get('lowstock/unread', [LowStockNotificationsController::class, 'getUnreadCount']);

        Route::post('blogs', [AdminBlogController::class, 'store']);
        Route::get('blogs', [AdminBlogController::class, 'index']);
        Route::get('blogs/{id}', [AdminBlogController::class, 'show']);
        Route::put('blogs/{id}', [AdminBlogController::class, 'update']);
        Route::put('blogs/{id}/publish', [AdminBlogController::class, 'publish']);
        Route::delete('blogs/{id}', [AdminBlogController::class, 'destroy']);
    });

    // API GIỎ HÀNG
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/add', [CartController::class, 'add']);
    Route::put('/cart/items/{id}', [CartController::class, 'update']);
    Route::delete('/cart/items/{id}', [CartController::class, 'remove']);
    Route::delete('/cart', [CartController::class, 'clear']);
    Route::put('/cart/items/{id}/increase', [CartController::class, 'increase']);
    Route::put('/cart/items/{id}/decrease', [CartController::class, 'decrease']);

    // API WISHLIST
    Route::get('/wishlist', [WishlistController::class, 'show']);
    Route::post('/wishlist/{productId}', [WishlistController::class, 'add']);
    Route::delete('/wishlist/{productId}', [WishlistController::class, 'remove']);
    Route::post('/wishlist/move-to-cart/{productId}', [WishlistController::class, 'moveToCart']);

    // API ORDER
    Route::get('/order', [OrderController::class, 'index']);
    Route::get('/order/{id}', [OrderController::class, 'show']);
    Route::post('/order', [OrderController::class, 'store']);
    Route::put('/order/{id}/cancel', [OrderController::class, 'cancel']);
    Route::post('/order/{id}/reorder', [OrderController::class, 'reorder']);

    // API REVIEW
    Route::get('products/{id}/reviews', [ReviewController::class, 'index']);
    Route::post('products/{id}/reviews', [ReviewController::class, 'store']);
    Route::post('reviews/{id}/helpful', [ReviewController::class, 'markHelpful']);

    // ... Thêm các route cần xác thực khác (ví dụ: logout)
    Route::post('/logout', [AuthController::class, 'logout']);
});
