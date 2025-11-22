<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product; // Giả định có model Product
use App\Models\LowStockNotification; // Giả định có model LowStockNotification
use Illuminate\Support\Facades\Log;

/**
 * Lệnh Console để kiểm tra tất cả sản phẩm có tồn kho thấp hay không
 * và tạo ra các bản ghi LowStockNotification tương ứng.
 */
class CheckLowStock extends Command
{
    /**
     * Tên và chữ ký của lệnh console.
     * Tên lệnh: check:low-stock
     * @var string
     */
    protected $signature = 'check:low-stock';

    /**
     * Mô tả lệnh console.
     * @var string
     */
    protected $description = 'Checks all products and generates low stock notifications if quantity falls below the threshold.';

    // Ngưỡng tồn kho thấp mặc định
    protected $lowStockThreshold = 10;

    /**
     * Thực thi lệnh console.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Bắt đầu kiểm tra tồn kho thấp...');
        $notificationCount = 0;

        // 1. Xác định ngưỡng tồn kho thấp (có thể lấy từ cấu hình hệ thống)
        // Hiện tại dùng giá trị mặc định là 10.

        // 2. Truy vấn các sản phẩm có số lượng tồn kho (stock_quantity) nhỏ hơn hoặc bằng ngưỡng
        $lowStockProducts = Product::where('stock_quantity', '<=', $this->lowStockThreshold)
            ->where('is_active', true) // Chỉ kiểm tra sản phẩm đang hoạt động
            ->get();

        if ($lowStockProducts->isEmpty()) {
            $this->info('Không tìm thấy sản phẩm nào có tồn kho thấp.');
            return Command::SUCCESS;
        }

        $this->info(sprintf('Tìm thấy %d sản phẩm có tồn kho thấp. Bắt đầu tạo thông báo...', $lowStockProducts->count()));

        // 3. Lặp qua các sản phẩm và tạo/cập nhật thông báo
        foreach ($lowStockProducts as $product) {
            // Kiểm tra xem thông báo đã tồn tại và chưa được đánh dấu là đã đọc/đã xử lý hay chưa
            $existingNotification = LowStockNotification::where('product_id', $product->id)
                ->where('is_read', 0)
                ->first();

            // Chỉ tạo thông báo mới nếu chưa có thông báo chưa đọc nào tồn tại cho sản phẩm này
            if (!$existingNotification) {
                LowStockNotification::create([
                    'product_id' => $product->id,
                    'current_stock' => $product->stock_quantity,
                    'threshold' => $this->lowStockThreshold,
                    'message' => "Sản phẩm '{$product->name}' (SKU: {$product->sku}) chỉ còn {$product->stock_quantity} đơn vị.",
                    'is_read' => 0, // Mặc định là chưa đọc
                ]);
                $notificationCount++;
                $this->comment("Đã tạo thông báo cho sản phẩm: {$product->name}");
            }
        }

        $this->info(sprintf('Hoàn thành. Đã tạo thêm %d thông báo mới.', $notificationCount));

        // Ghi log chi tiết
        Log::info(sprintf('Low Stock Check: Đã kiểm tra %d sản phẩm, tạo %d thông báo mới.', $lowStockProducts->count(), $notificationCount));

        return Command::SUCCESS;
    }
}