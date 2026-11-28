# Chính sách mua bán H-Smart

## Hoa hồng đơn hàng

Đơn mới mặc định áp dụng hoa hồng 2% do người bán chịu. Cơ sở tính gồm giá sản phẩm sau giảm giá và phí vận chuyển người mua phải trả. Hoa hồng được chụp vào đơn lúc tạo: `floor((tiền sản phẩm + phí vận chuyển) × tỷ lệ / 100)`. Ví dụ sản phẩm 1.000.000đ, vận chuyển 25.000đ, phí là 20.500đ. Đơn cũ giữ nguyên phí đã ghi trên đơn.

## Mua theo giá niêm yết

Sản phẩm trong giỏ chưa được giữ. Khi đặt mua, máy chủ kiểm tra lại trạng thái và giá, sau đó tạo một đơn riêng cho một sản phẩm và giữ sản phẩm trong 30 phút. Nếu giá thay đổi, người mua phải kiểm tra lại trước khi đặt. Giỏ có thể có nhiều người bán; phiên bản hiện tại thanh toán từng đơn.

## Thanh toán VNPay Sandbox

Nạp ví và thanh toán đơn dùng VNPay Sandbox. Callback chỉ ghi nhận khi máy chủ xác minh chữ ký, số tiền và tham chiếu; redirect trình duyệt không tự xác nhận thanh toán. Không gửi thông tin ngân hàng hoặc mã xác thực qua chatbot.

## COD có đặt cọc

Đơn COD mới yêu cầu đặt cọc trước khi người bán xác nhận và gửi hàng. Tỷ lệ mặc định là 25% tiền sản phẩm; quản trị viên có thể đổi trong phạm vi 20–30%. Tỷ lệ và số tiền được chụp trên đơn. Hoa hồng toàn đơn chỉ tính một lần theo giá sản phẩm cộng phí giao hàng và được trừ từ khoản cọc. Ví dụ sản phẩm 1.000.000đ, giao hàng 25.000đ, cọc 25% là 250.000đ; hoa hồng 2% là 20.500đ; phần còn lại người mua trả khi nhận là 750.000đ tiền hàng và 25.000đ phí giao. Phần cọc ròng 229.500đ được giữ chờ khiếu nại; 750.000đ COD còn lại không được ghi có vào ví nội bộ. Nếu người bán thu trực tiếp, đơn được ghi riêng là thu trực tiếp; nếu đơn vị vận chuyển thu, cần đối soát riêng. Nếu giao thất bại và hàng hoàn về, khoản cọc không tự động bị tịch thu. Hoàn cọc vào Ví của tôi chỉ sau khi người mua đồng ý rõ ràng và quản trị viên xác minh. Khoản hoàn chỉ được ghi nhận vào sổ ví sau khi admin xác minh và người mua đồng ý.

## VietQR

Không nhận yêu cầu VietQR mới. Bản ghi VietQR lịch sử chỉ được giữ để tra cứu; endpoint ghi nhận/đối soát giao dịch mới đã vô hiệu hóa. Đơn lịch sử còn chờ giữ nguyên trạng thái, không tự mở khóa và không được ghi có trong phạm vi Sandbox-only này.

## Dịch vụ chưa được bật

Hạn mức 5 tin miễn phí/tháng và phí 5.000đ/tin từ tin thứ 6 được áp dụng; phí được ghi nợ từ số dư khả dụng trong sổ ví. Gói đẩy tin mặc định 10.000đ/3 ngày được ưu tiên luân phiên trong danh mục; VIP mặc định 15.000đ/7 ngày được ưu tiên luân phiên ở trang chủ và danh mục. Theo sắp xếp mặc định, tối đa ba tin ưu tiên đứng trước danh sách thường; VIP xếp trước tin đẩy và các tin cùng gói luân phiên theo lát 5 phút. Bộ lọc vẫn áp dụng, gói không đảm bảo vị trí cố định, và ưu tiên dừng khi tin hết hạn, bị ẩn hoặc bán xong. Quản trị viên có thể thay biểu phí và giới hạn cho giao dịch mới trong trang quản trị; đơn cũ giữ các giá trị đã chụp. Sổ ví ghi nhận khoản thu, khoản giữ và khoản hoàn. Sau thời hạn khiếu nại 48 giờ, đơn đủ điều kiện chuyển sang chờ admin xác nhận giải ngân; scheduler không tự ghi có. Yêu cầu chi trả cho người bán được admin xử lý thủ công; VNPay Sandbox không cung cấp luồng chuyển tiền ra ngân hàng.

## Hỗ trợ

Thông tin pháp nhân, địa chỉ liên hệ, hotline và email hỗ trợ cần được đơn vị vận hành bổ sung trước khi sử dụng cho giao dịch thật.
