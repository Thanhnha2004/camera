<?php

// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite; 

class AuthController extends Controller
{

    /**
     * POST /api/register
     */
    public function register(Request $request)
    {
        // 1. Validation rules
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed', // 'confirmed' yêu cầu trường password_confirmation
        ]);

        // 2. Tạo User mới
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // 3. Tạo Sanctum Token
        // 'auth_token' là tên token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * POST /api/login
     */
    public function login(Request $request)
    {
        // 1. Validation rules
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // 2. Xác thực (Attempt Authentication)
        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Thông tin đăng nhập không hợp lệ.'],
            ]);
        }

        $user = Auth::user();

        // Xóa các token cũ (tùy chọn, để đảm bảo mỗi phiên chỉ có 1 token)
        $user->tokens()->delete();

        // 3. Tạo Sanctum Token mới
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * POST /api/google/callback
     * * Xử lý mã ủy quyền (code) nhận được từ Google OAuth.
     */
    public function googleAuthCallback()
    {
        try{
            $googleUser = Socialite::driver('google')->user();
            $user = User::where('google_id', $googleUser->getId())->first();

            if(!$user){
                $new_user = User::create([
                    'name'=> $googleUser->getName(),
                    'email'=> $googleUser->getEmail(),
                    'google_id'=> $googleUser->getId(),
                ]);
            } else {
                return 0;
            }
        }catch(Exception $e){
            dd("something wrong");
        }
    }

    /**
     * Helper: Tạo Sanctum Token và trả về Response chung
     */
    protected function issueTokenResponse(User $user)
    {
        // Xóa token cũ
        $user->tokens()->delete();

        // Tạo token mới
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * POST /api/logout
     */
    public function logout(Request $request)
    {
        // Thu hồi token hiện tại đang được sử dụng
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Đăng xuất thành công.'
        ], 200);
    }
}