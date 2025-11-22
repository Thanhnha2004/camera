    <?php

    use App\Http\Controllers\Admin\AdminProductController;
    use App\Http\Controllers\Api\AddressController;
    use App\Http\Controllers\Api\AuthController;
    use App\Http\Controllers\Api\CartController;
    use App\Http\Controllers\Api\ProductController;
    use App\Http\Controllers\Api\UserProfileController;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Route;
    use App\Http\Controllers\Api\BrandController;
    use App\Http\Controllers\Api\CategoryController;
    use App\Http\Controllers\Api\CouponController;
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

    // CÁC ROUTE CẦN XÁC THỰC (YÊU CẦU SANCTUM TOKEN)
    Route::middleware('auth:sanctum')->group(function () {

        // 1. Lấy thông tin người dùng hiện tại (Current User Info)
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
        

        // 2. API HỒ SƠ NGƯỜI DÙNG 
        Route::get('/profile', [UserProfileController::class, 'show']);
        Route::put('/profile', [UserProfileController::class, 'update']);
        Route::post('/profile/avatar', [UserProfileController::class, 'uploadAvatar']);
        Route::put('/profile/password', [UserProfileController::class, 'changePassword']);

        // 3. API ĐỊA CHỈ NGƯỜI DÙNG
        Route::get('/addresses', [AddressController::class, 'show']);
        Route::put('/addresses/{id}', [AddressController::class, 'update']);
        Route::post('/addresses', [AddressController::class, 'create']);
        Route::delete('/addresses/{id}', [AddressController::class, 'delete']);
        Route::put('/addresses/{id}/set-default', [AddressController::class, 'setDefault']);

        // 4. API LẤY SẢN PHẨM THEO TRANG
        Route::get('/products/price-range', [ProductController::class, 'searchPriceRange']);
        Route::get('/products/autocomplete', [ProductController::class, 'autocomplete']);
        Route::get('/products/search', [ProductController::class, 'search']);

        Route::get('/products', [ProductController::class, 'show']);
        Route::get('/products/{slug}', [ProductController::class, 'showDetail']);//tìm theo slug và id
     

        // 4. API SẢN PHẨM CHO ADMIN
        // Áp dụng middleware 'admin' cho nhóm route này
        Route::middleware(['admin'])->prefix('admin')->group(function () {
            Route::get('/products', [AdminProductController::class, 'show']); 
            Route::post('/products', [AdminProductController::class, 'create']);

            
            // Cập nhật sản phẩm
            Route::put('/products/{id}', [AdminProductController::class, 'update']);
            // Xóa sản phẩm
            Route::delete('/products/{id}', [AdminProductController::class, 'delete']);

        });

        // 5. API GIỎ HÀNG
        Route::get('/cart', [CartController::class, 'show']);
        Route::post('/cart/add', [CartController::class, 'add']);
        Route::put('/cart/items/{id}', [CartController::class, 'update']);
        Route::delete('/cart/items/{id}', [CartController::class, 'remove']);
        Route::delete('/cart', [CartController::class, 'clear']);

        // ... Thêm các route cần xác thực khác (ví dụ: logout)
        Route::post('/logout', [AuthController::class, 'logout']);

        //6.Category 
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/categories/{slug}', [CategoryController::class, 'showBySlug']);
        Route::get('/categories/{id}/products', [CategoryController::class, 'products']);

        //7. brand
        Route::get('/brands', [BrandController::class, 'index']);
        Route::get('/brands/{slug}', [BrandController::class, 'showBySlug']);
        Route::get('/brands/{id}/products', [BrandController::class, 'products']);

        //8.lọc và sắp xếp sản phẩm,gợi ý tìm sản phẩm
        Route::get('/products/filter', [ProductController::class, 'filter']);
        Route::get('/products/sort', [ProductController::class, 'sort']);


        //9. validate coupon
        Route::get('/coupons/validate/{code}', [CouponController::class, 'validateCoupon']);// Validate Coupon
        Route::post('/cart/apply-coupon', [CouponController::class, 'applyCoupon']);// Apply Coupon vào giỏ hàng
        Route::delete('/cart/remove-coupon', [CouponController::class, 'removeCoupon']);// Remove Coupon khỏi giỏ hàng

    });
