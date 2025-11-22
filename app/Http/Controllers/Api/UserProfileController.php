<?php

namespace App\Http\Controllers\Api;

// Api/UserProfileController.php (Phương thức changePassword)
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserProfileController extends Controller
{
    // Api/UserProfileController.php (Phương thức show)
    public function show(Request $request)
    {
        // Lấy thông tin user hiện tại
        return response()->json([
            'user' => $request->user()->load('addresses'), // Giả sử bạn muốn tải cả địa chỉ
        ]);
    }

    // Api/UserProfileController.php (Phương thức update)
    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            // Kiểm tra email duy nhất, loại trừ email hiện tại của user
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'required|string',
        ]);

        $user->update($request->only('name', 'email', 'phone'));

        return response()->json([
            'message' => 'Hồ sơ đã được cập nhật thành công.',
            'user' => $user
        ]);
    }

    // Api/UserProfileController.php (Phương thức uploadAvatar)
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // Validation file
        ]);

        $user = $request->user();

        // Lưu file vào thư mục 'avatars' trong storage/app/public
        $path = $request->file('avatar')->store('avatars', 'public');

        // Cập nhật đường dẫn avatar vào database
        $user->avatar = Storage::url($path);
        $user->save();

        return response()->json(data: [
            'message' => 'Avatar đã được tải lên thành công.',
            'avatar' => $user->avatar
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'User chưa đăng nhập'], 401);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Mật khẩu hiện tại không chính xác'], 400);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json(['message' => 'Đổi mật khẩu thành công']);
    }

}
