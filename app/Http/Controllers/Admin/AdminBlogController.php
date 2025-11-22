<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminBlogController extends Controller
{
    /**
     * GET /api/admin/blogs
     * Lấy danh sách Blog posts (có phân trang và lọc).
     */
    public function index(Request $request): JsonResponse
    {
        // Định nghĩa các tham số lọc và phân trang
        $limit = $request->input('limit', 15);
        $status = $request->input('status'); // Lọc theo trạng thái
        $search = $request->input('search'); // Tìm kiếm theo tiêu đề

        $blogs = Blog::query()
            // Eager load mối quan hệ tác giả (author) để tránh N+1 Query
            ->with('author:id,name');

        // Lọc theo trạng thái nếu được cung cấp và hợp lệ
        if ($status && in_array($status, ['draft', 'published'])) {
            $blogs->where('status', $status);
        }

        // Tìm kiếm theo tiêu đề (Không phân biệt chữ hoa/thường)
        if ($search) {
            $blogs->where('title', 'LIKE', '%' . $search . '%');
        }

        // Sắp xếp theo thời gian tạo mới nhất và phân trang
        $blogs = $blogs->latest()->paginate($limit);

        return response()->json($blogs, 200);
    }

    /**
     * Xử lý việc tạo mới một bài viết Blog.
     * POST /api/admin/blogs
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Định nghĩa và thực hiện Validation
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'excerpt' => ['nullable', 'string'],
            'featured_image' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:draft,published'],
        ];

        // Thực hiện validation. Nếu thất bại, Laravel sẽ tự động throw exception và trả về lỗi 422.
        $data = $request->validate($rules);
        
        // --- Bắt đầu Logic Xử Lý Dữ Liệu ---

        // 2. Thêm author_id (lấy từ người dùng hiện tại)
        $data['author_id'] = $request->user()->id;

        // 3. Tạo slug từ tiêu đề và đảm bảo unique
        $baseSlug = Str::slug($data['title']);
        $slug = $baseSlug;
        $count = 0;

        // Kiểm tra và tạo unique slug
        while (Blog::where('slug', $slug)->exists()) {
            $count++;
            $slug = $baseSlug . '-' . $count;
        }
        $data['slug'] = $slug;
        
        try {
            // 4. Tạo Blog mới
            $blog = Blog::create($data);

            return response()->json([
                'message' => 'Bài viết Blog đã được tạo thành công.',
                'blog' => $blog
            ], 201); // HTTP 201 Created

        } catch (\Exception $e) {
            Log::error('Lỗi khi tạo Blog:', ['error' => $e->getMessage(), 'data' => $data]);
            
            return response()->json([
                'message' => 'Đã xảy ra lỗi trong quá trình tạo bài viết.',
                'error' => $e->getMessage()
            ], 500); // HTTP 500 Internal Server Error
        }
    }

    /**
     * GET /api/admin/blogs/{id}
     * Hiển thị chi tiết một Blog post.
     */
    public function show($id): JsonResponse
    {
        try {
            // Lấy blog post, bao gồm thông tin tác giả
            $blog = Blog::with('author:id,name')->findOrFail($id);
            
            return response()->json($blog, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Bài viết Blog không tồn tại.'], 404);
        }
    }

    /**
     * PUT /api/admin/blogs/{id}
     * Cập nhật thông tin của một Blog post.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $blog = Blog::findOrFail($id);
            
            // 1. Định nghĩa và thực hiện Validation cho cập nhật
            $rules = [
                // Kiểm tra title duy nhất, bỏ qua chính blog post hiện tại
                'title' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('blogs')->ignore($blog->id)],
                'content' => ['sometimes', 'required', 'string'],
                'excerpt' => ['nullable', 'string'],
                'featured_image' => ['nullable', 'string', 'max:255'],
                'status' => ['sometimes', 'required', 'string', 'in:draft,published'],
            ];

            // Chỉ xác thực những trường có trong request
            $data = $request->validate($rules);

            // --- Logic Xử Lý Dữ Liệu ---

            // 2. Tái tạo Slug nếu tiêu đề (title) đã được thay đổi
            if (isset($data['title']) && $data['title'] !== $blog->title) {
                $baseSlug = Str::slug($data['title']);
                $slug = $baseSlug;
                $count = 0;

                // Kiểm tra và tạo unique slug, bỏ qua chính blog post hiện tại
                while (Blog::where('slug', $slug)->where('id', '!=', $blog->id)->exists()) {
                    $count++;
                    $slug = $baseSlug . '-' . $count;
                }
                $data['slug'] = $slug;
            }

            // 3. Cập nhật Blog
            $blog->update($data);

            return response()->json([
                'message' => 'Bài viết Blog đã được cập nhật thành công.',
                'blog' => $blog
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Bài viết Blog không tồn tại.'], 404);
        } catch (\Exception $e) {
            Log::error('Lỗi khi cập nhật Blog:', ['error' => $e->getMessage(), 'id' => $id, 'data' => $request->all()]);
            
            return response()->json([
                'message' => 'Đã xảy ra lỗi trong quá trình cập nhật bài viết.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/admin/blogs/{id}
     * Xóa một Blog post.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $blog = Blog::findOrFail($id);
            $blog->delete(); // Giả định là soft-delete

            return response()->json([
                'message' => 'Bài viết Blog đã được xóa thành công.',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Bài viết Blog không tồn tại.'], 404);
        }
    }

    /**
     * PUT /api/admin/blogs/{id}/publish
     * Hành động tùy chỉnh: Thiết lập trạng thái của Blog post thành 'published'.
     */
    public function publish($id): JsonResponse
    {
        try {
            $blog = Blog::findOrFail($id);

            // Kiểm tra nếu bài viết đã được xuất bản
            if ($blog->status === 'published') {
                return response()->json(['message' => 'Bài viết Blog đã được xuất bản rồi.'], 409); // Conflict
            }

            // Cập nhật trạng thái
            $blog->update(['status' => 'published']);

            return response()->json([
                'message' => 'Bài viết Blog đã được xuất bản thành công.',
                'blog' => $blog
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Bài viết Blog không tồn tại.'], 404);
        } catch (\Exception $e) {
             Log::error('Lỗi khi xuất bản Blog:', ['error' => $e->getMessage(), 'id' => $id]);
             
             return response()->json([
                'message' => 'Đã xảy ra lỗi trong quá trình xuất bản bài viết.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}