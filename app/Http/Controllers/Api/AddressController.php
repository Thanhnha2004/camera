<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    // Lấy danh sách địa chỉ của user
    public function show(Request $request): mixed
    {
        return $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->get();
    }

    // Tạo mới địa chỉ
    public function create(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address_line' => 'required|string|max:500',
            'ward' => 'required|string|max:100',
            'district' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'is_default' => 'boolean',
        ]);

        // Nếu thêm địa chỉ mặc định mới, bỏ mặc định ở các địa chỉ khác
        if (!empty($data['is_default']) && $data['is_default']) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $data['user_id'] = $request->user()->id;

        $address = Address::create(attributes: $data);

        return response()->json([
            'message' => 'Thêm địa chỉ mới thành công.',
            'address' => $address,
        ]);
    }

    // Cập nhật địa chỉ
    public function update(Request $request, $id)
    {
        $address = $request->user()->addresses()->findOrFail($id);

        $data = $request->validate([
            'full_name' => 'string|max:255',
            'phone' => 'string|max:20',
            'address_line' => 'string|max:500',
            'ward' => 'string|max:100',
            'district' => 'string|max:100',
            'city' => 'string|max:100',
            'is_default' => 'boolean',
        ]);

        // Nếu cập nhật thành mặc định
        if (isset($data['is_default']) && $data['is_default']) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address->update($data);

        return response()->json([
            'message' => 'Địa chỉ đã được cập nhật.',
            'address' => $address,
        ]);
    }

    // Đặt địa chỉ làm mặc định
    public function setDefault(Request $request, $id)
    {
        $request->user()->addresses()->update(['is_default' => false]);
        $address = $request->user()->addresses()->findOrFail($id);
        $address->update(['is_default' => true]);

        return response()->json(['message' => 'Địa chỉ đã được đặt làm mặc định.']);
    }

    // Xóa địa chỉ
    public function delete(Request $request, $id)
    {
        $address = $request->user()->addresses()->findOrFail($id);
        $address->delete();

        return response()->json([
            'message' => 'Địa chỉ đã được xóa.',
        ]);
    }
}
