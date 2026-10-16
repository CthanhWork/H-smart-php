<?php

namespace Database\Seeders;

use App\Modules\Product\Models\Category;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductImage;
use App\Modules\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoProductSeeder extends Seeder
{
    /**
     * Add realistic sample listings and Unsplash photos for local demos.
     * Safe to run repeatedly: each seller/title pair is updated in place.
     */
    public function run(): void
    {
        $seller = User::firstOrCreate(
            ['email' => 'demo-seller@hsmart.local'],
            [
                'username' => 'demo-seller',
                'password_hash' => Hash::make(Str::random(64)),
                'role' => 'member',
                'status' => 'active',
                'full_name' => 'Người bán mẫu',
            ],
        );

        $categories = [
            'dien-tu' => Category::firstOrCreate(['slug' => 'dien-tu'], ['name' => 'Điện tử'])->id,
            'sach-va-giao-trinh' => Category::firstOrCreate(['slug' => 'sach-va-giao-trinh'], ['name' => 'Sách và giáo trình'])->id,
            'do-dung-hoc-tap' => Category::firstOrCreate(['slug' => 'do-dung-hoc-tap'], ['name' => 'Đồ dùng học tập'])->id,
            'thoi-trang' => Category::firstOrCreate(['slug' => 'thoi-trang'], ['name' => 'Thời trang'])->id,
            'do-gia-dung' => Category::firstOrCreate(['slug' => 'do-gia-dung'], ['name' => 'Đồ gia dụng'])->id,
            'khac' => Category::firstOrCreate(['slug' => 'khac'], ['name' => 'Khác'])->id,
        ];

        $listings = [
            ['MacBook Air M1 2020 8GB/256GB', 'Laptop còn hoạt động tốt, pin dùng ổn cho học tập và làm việc văn phòng. Kèm sạc theo máy.', 13500000, 'dien-tu', 'good', true, '1496181133206-80ce9b88a853'],
            ['MacBook Pro 13 inch Intel 2020', 'Máy phù hợp học lập trình và thiết kế cơ bản. Ngoại hình còn đẹp, có sạc.', 14800000, 'dien-tu', 'good', true, '1517336714731-489689fd1ca8'],
            ['Laptop Dell XPS 13 9310', 'Laptop mỏng nhẹ, màn hình 13 inch, phù hợp mang đi học mỗi ngày. Máy đã qua sử dụng, hoạt động bình thường.', 11900000, 'dien-tu', 'good', true, '1588872657578-7efd1f1555ed'],
            ['iPad Gen 9 Wi-Fi 64GB', 'Máy tính bảng dùng đọc tài liệu, ghi chú và học trực tuyến. Màn hình không nứt, cảm ứng hoạt động tốt.', 5900000, 'dien-tu', 'like_new', true, '1544244015-0df4b3ffc6b0'],
            ['iPhone 12 128GB', 'Điện thoại đã qua sử dụng, camera và Face ID hoạt động bình thường. Có thể kiểm tra trực tiếp khi nhận.', 7800000, 'dien-tu', 'good', true, '1690220928060-a32811e39f54'],
            ['Samsung Galaxy A54 5G 128GB', 'Điện thoại màn hình Super AMOLED, pin dùng tốt. Phù hợp nhu cầu học tập và giải trí.', 4900000, 'dien-tu', 'good', true, '1505468726633-0069fc52f4b9'],
            ['Tai nghe Sony WH-1000XM4', 'Tai nghe chống ồn, đệm tai còn tốt, kết nối Bluetooth ổn định. Có cáp sạc USB-C.', 3900000, 'dien-tu', 'good', true, '1540821924489-7690c70c4eac'],
            ['Tai nghe chụp tai Logitech G Pro X', 'Tai nghe có dây, micro hoạt động tốt, phù hợp học online và chơi game.', 1450000, 'dien-tu', 'like_new', false, '1590658268037-6bf12165a8df'],
            ['Bàn phím cơ Keychron K2', 'Bàn phím cơ layout gọn, kết nối Bluetooth và USB-C. Phím hoạt động ổn, đã vệ sinh trước khi đăng.', 1250000, 'dien-tu', 'good', true, '1587829741301-dc798b83add3'],
            ['Máy ảnh Canon EOS M50 kèm lens kit', 'Máy ảnh mirrorless dành cho người mới tập chụp. Bao gồm thân máy và ống kính kit.', 8900000, 'dien-tu', 'good', true, '1499760824500-675daed0dafd'],
            ['Máy ảnh Fujifilm X-T30', 'Máy ảnh nhỏ gọn, phù hợp chụp ảnh du lịch và làm nội dung. Ngoại hình có trầy nhẹ ở đáy.', 14500000, 'dien-tu', 'fair', true, '1701382334837-d3a98fff1770'],
            ['Giáo trình Cơ sở dữ liệu', 'Sách phục vụ môn Cơ sở dữ liệu, nội dung còn đầy đủ. Có ghi chú bút chì ở một số trang.', 85000, 'sach-va-giao-trinh', 'good', false, '1544947950-fa07a98d237f'],
            ['English Grammar in Use – Fifth Edition', 'Sách ngữ pháp tiếng Anh bản in, bìa và ruột còn tốt. Phù hợp tự học và ôn tập.', 220000, 'sach-va-giao-trinh', 'like_new', true, '1507842217343-583bb7270b66'],
            ['Sách Toán cao cấp tập 1', 'Giáo trình dùng cho các học phần toán đại cương. Có một ít ghi chú ở lề trang.', 65000, 'sach-va-giao-trinh', 'fair', false, '1544947950-fa07a98d237f'],
            ['Balo laptop 15.6 inch chống thấm', 'Balo nhiều ngăn, có ngăn đệm đựng laptop. Khóa kéo và quai đeo còn chắc chắn.', 320000, 'do-dung-hoc-tap', 'good', true, '1553062407-98eeb64c6a62'],
            ['Đèn bàn LED kẹp bàn', 'Đèn học có thể điều chỉnh góc chiếu, ánh sáng dịu, dùng nguồn USB.', 180000, 'do-dung-hoc-tap', 'like_new', false, '1507473885765-e6ed057f782c'],
            ['Máy tính cầm tay Casio FX-580VN X', 'Máy tính khoa học dùng tốt, màn hình rõ, đầy đủ nắp và nắp trượt.', 430000, 'do-dung-hoc-tap', 'good', false, '1518455027359-f3f8164ba6bd'],
            ['Giày sneaker trắng xanh size 41', 'Giày đã sử dụng vài lần, đế còn tốt, phù hợp đi học và đi chơi.', 420000, 'thoi-trang', 'good', true, '1687444334081-8ca04ed2a1a3'],
            ['Áo thun cotton form rộng size L', 'Áo cotton màu trung tính, không rách hay xù lông. Giặt sạch trước khi giao.', 120000, 'thoi-trang', 'good', false, '1521572163474-6864f9cf17ab'],
            ['Ghế làm việc lưng lưới', 'Ghế xoay dùng ở bàn học, bánh xe và cần nâng hạ hoạt động bình thường.', 650000, 'do-gia-dung', 'good', true, '1506439773649-6e0eb8cfb237'],
            ['Microsoft Surface Laptop Go 2 8GB/128GB', 'Laptop nhỏ gọn, màn hình cảm ứng, phù hợp ghi chép và làm việc văn phòng. Máy đã qua sử dụng, kèm bộ sạc.', 8900000, 'dien-tu', 'good', true, '1550009158-9ebf69173e03'],
            ['Lenovo ThinkPad T14 Gen 2', 'Laptop dòng ThinkPad, bàn phím và các cổng kết nối hoạt động bình thường. Có vài vết xước nhẹ ở vỏ.', 10500000, 'dien-tu', 'good', true, '1588872657578-7efd1f1555ed'],
            ['Samsung Galaxy Tab S7 FE 64GB', 'Máy tính bảng màn hình lớn, loa và cảm ứng hoạt động tốt. Có bao da, không kèm bút S Pen.', 6500000, 'dien-tu', 'good', true, '1544244015-0df4b3ffc6b0'],
            ['Xiaomi Redmi Note 12 Pro 5G', 'Điện thoại đã qua sử dụng, camera và sạc hoạt động bình thường. Người mua có thể kiểm tra máy khi nhận.', 4200000, 'dien-tu', 'good', true, '1690220928060-a32811e39f54'],
            ['Apple AirPods Pro thế hệ 2', 'Tai nghe và hộp sạc hoạt động tốt, đã vệ sinh trước khi đăng. Có thể kết nối thử trực tiếp.', 3600000, 'dien-tu', 'good', true, '1505740420928-5e560c06d30e'],
            ['Chuột Logitech MX Master 3S', 'Chuột không dây dùng Bluetooth hoặc đầu thu USB, các nút và con lăn hoạt động ổn định.', 1750000, 'dien-tu', 'like_new', true, '1527814050087-3793815479db'],
            ['Ổ cứng SSD di động Samsung T7 500GB', 'Ổ cứng di động USB-C, đã kiểm tra đọc ghi và nhận máy tính bình thường. Kèm cáp kết nối.', 1250000, 'dien-tu', 'good', false, '1550009158-9ebf69173e03'],
            ['Loa Bluetooth JBL Flip 6', 'Loa Bluetooth nhỏ gọn, âm thanh và pin hoạt động tốt. Có cáp sạc, ngoại hình còn sạch.', 1650000, 'dien-tu', 'good', true, '1505740420928-5e560c06d30e'],
            ['Ống kính Canon EF 50mm f/1.8 STM', 'Ống kính lấy nét tự động bình thường, kính không mốc. Có nắp trước và nắp sau.', 1900000, 'dien-tu', 'good', true, '1526170375885-4d8ecf77b99f'],
            ['Balo Herschel Classic', 'Balo đi học màu đen, ngăn chính rộng, khóa kéo và quai đeo còn tốt. Có dấu hiệu sử dụng nhẹ.', 480000, 'do-dung-hoc-tap', 'good', true, '1584917865442-de89df76afd3'],
            ['Dế Mèn Phiêu Lưu Ký – Tô Hoài', 'Sách tiếng Việt, ruột đầy đủ và không rách trang. Bìa có một vài dấu trầy nhỏ.', 45000, 'sach-va-giao-trinh', 'good', false, '1519682337058-a94d519337bc'],
            ['Clean Code – Robert C. Martin', 'Sách tiếng Anh về viết mã dễ bảo trì, bản in đã qua sử dụng. Không thiếu trang, có ghi chú nhỏ ở một số đoạn.', 260000, 'sach-va-giao-trinh', 'good', true, '1543002588-bfa74002ed7e'],
            ['Sổ tay ghi chép bìa cứng A5', 'Sổ còn nhiều trang trắng, giấy không bị ố. Phù hợp ghi chép bài học hoặc kế hoạch cá nhân.', 35000, 'do-dung-hoc-tap', 'like_new', false, '1544816155-12df9643f363'],
            ['Bộ bút highlight 6 màu', 'Bộ bút đánh dấu màu, mực còn sử dụng tốt và đầu bút chưa bị tòe.', 55000, 'do-dung-hoc-tap', 'good', false, '1513364776144-60967b0f800f'],
            ['Giá đỡ laptop nhôm có thể gấp gọn', 'Giá đỡ điều chỉnh được độ cao, chân đế chắc chắn. Phù hợp laptop kích thước phổ biến.', 180000, 'do-dung-hoc-tap', 'like_new', false, '1550009158-9ebf69173e03'],
            ['Đồng hồ Casio MTP-V001D', 'Đồng hồ kim dây thép, mặt kính còn tốt và chạy bình thường. Dây có vết xước nhỏ do sử dụng.', 420000, 'thoi-trang', 'good', true, '1523275335684-37898b6baf30'],
            ['Kính mát gọng đen unisex', 'Kính đã qua sử dụng, gọng và bản lề còn chắc. Tròng không nứt, có hộp đựng.', 150000, 'thoi-trang', 'good', false, '1572635196237-14b3f281503f'],
            ['Áo khoác denim xanh size M', 'Áo khoác jean còn tốt, không rách, khóa cúc đầy đủ. Đã giặt sạch trước khi đăng bán.', 280000, 'thoi-trang', 'good', true, '1591047139829-d91aecb6caea'],
            ['Bình giữ nhiệt inox 500ml', 'Bình giữ nhiệt có nắp vặn kín, không móp và không rò nước. Đã vệ sinh sạch sẽ.', 95000, 'do-gia-dung', 'good', false, '1560343090-f0409e92791a'],
            ['Màn hình LG 24 inch Full HD', 'Màn hình máy tính đã qua sử dụng, hiển thị ổn định, không sọc. Có chân đế và cáp nguồn.', 1750000, 'dien-tu', 'good', true, '1550745165-9bc0b252726f'],
        ];

        foreach ($listings as [$title, $description, $price, $categorySlug, $condition, $negotiable, $photoId]) {
            $product = Product::updateOrCreate(
                ['seller_id' => $seller->id, 'title' => $title],
                [
                    'category_id' => $categories[$categorySlug],
                    'description' => $description,
                    'price' => $price,
                    'condition' => $condition,
                    'negotiable' => $negotiable,
                    'min_price' => $negotiable ? (int) floor($price * 0.9) : null,
                    'status' => Product::STATUS_ACTIVE,
                    'province' => 'TP. Hồ Chí Minh',
                    'district' => 'Quận 10',
                ],
            );

            ProductImage::updateOrCreate(
                ['product_id' => $product->id, 'sort_order' => 0],
                ['url' => "https://images.unsplash.com/photo-{$photoId}?auto=format&fit=crop&w=900&q=80"],
            );
        }
    }
}
