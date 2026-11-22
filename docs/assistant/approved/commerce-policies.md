# Chính sách mua bán H-Smart (bản demo)

## Hoa hồng đơn hàng

Đơn mới mặc định áp dụng hoa hồng 2% do người bán chịu. Cơ sở tính gồm giá sản phẩm sau giảm giá và phí vận chuyển người mua phải trả. Hoa hồng được chụp vào đơn lúc tạo: `floor((tiền sản phẩm + phí vận chuyển) × tỷ lệ / 100)`. Ví dụ sản phẩm 1.000.000đ, vận chuyển 25.000đ, phí là 20.500đ. Đơn cũ giữ nguyên phí đã ghi trên đơn.

## Mua theo giá niêm yết

Sản phẩm trong giỏ chưa được giữ. Khi đặt mua, máy chủ kiểm tra lại trạng thái và giá, sau đó tạo một đơn riêng cho một sản phẩm và giữ sản phẩm trong 30 phút. Nếu giá thay đổi, người mua phải kiểm tra lại trước khi đặt. Giỏ có thể có nhiều người bán; phiên bản hiện tại thanh toán từng đơn.

## Thanh toán thử

VNPay Sandbox và thanh toán mô phỏng chỉ dùng kiểm thử, không chứng minh tiền thật đã được chuyển. Redirect trình duyệt không phải xác nhận thanh toán. Không gửi thông tin ngân hàng hoặc mã xác thực qua chatbot.

## COD có đặt cọc

Đơn COD mới yêu cầu đặt cọc trước khi người bán xác nhận và gửi hàng. Tỷ lệ mặc định là 25% tiền sản phẩm; quản trị viên có thể đổi trong phạm vi 20–30%. Tỷ lệ và số tiền được chụp trên đơn. Hoa hồng toàn đơn chỉ tính một lần theo giá sản phẩm cộng phí giao hàng và được trừ từ khoản cọc. Ví dụ sản phẩm 1.000.000đ, giao hàng 25.000đ, cọc 25% là 250.000đ; hoa hồng 2% là 20.500đ; phần còn lại người mua trả khi nhận là 750.000đ tiền hàng và 25.000đ phí giao. Phần cọc ròng 229.500đ được giữ chờ khiếu nại; 750.000đ COD còn lại không được ghi có vào ví nội bộ. Nếu người bán thu trực tiếp, đơn được ghi riêng là thu trực tiếp; nếu đơn vị vận chuyển thu, cần đối soát riêng. Nếu giao thất bại và hàng hoàn về, khoản cọc không tự động bị tịch thu. Hoàn cọc vào số dư mô phỏng chỉ sau khi người mua đồng ý rõ ràng và quản trị viên xác minh. Đây là luồng mô phỏng, không phải xác nhận giữ tiền thật.

## VietQR

VietQR tạo hướng dẫn chuyển khoản và mã QR từ `VIETQR_BANK_ID`, `VIETQR_ACCOUNT_NUMBER` và `VIETQR_ACCOUNT_NAME`. Tạo ảnh QR không xác minh tiền vào và không đổi đơn sang đã thanh toán. Quản trị viên có thể ghi nhận đối soát thủ công sau khi kiểm tra sao kê; khoản khớp chính xác và còn trong hạn mới đổi đơn sang đã thanh toán. Thiếu, dư, đến muộn hoặc không khớp được lưu để xử lý thủ công. Trước giao dịch thật cần tích hợp nguồn sao kê hoặc webhook đã xác minh và quy trình hoàn tiền phù hợp. Hiện merchant chưa được cấu hình.

## Dịch vụ chưa được bật

Hạn mức 5 tin miễn phí/tháng và phí 5.000đ/tin từ tin thứ 6 được áp dụng trong bản demo; phí chỉ được trừ từ số dư mô phỏng trong `local/testing`. Gói đẩy tin mặc định 10.000đ/3 ngày được ưu tiên luân phiên trong danh mục; VIP mặc định 15.000đ/7 ngày được ưu tiên luân phiên ở trang chủ và danh mục. Theo sắp xếp mặc định, tối đa ba tin ưu tiên đứng trước danh sách thường; VIP xếp trước tin đẩy và các tin cùng gói luân phiên theo lát 5 phút. Bộ lọc vẫn áp dụng, gói không đảm bảo vị trí cố định, và ưu tiên dừng khi tin hết hạn, bị ẩn hoặc bán xong. Quản trị viên có thể thay biểu phí và giới hạn cho giao dịch mới trong trang quản trị; đơn cũ giữ các giá trị đã chụp. Bản demo có sổ ví nội bộ, giải ngân sau thời hạn khiếu nại 48 giờ và giữ khoản chưa giải ngân khi có khiếu nại. Nạp, rút và hoàn tiền vào ví chỉ là bút toán mô phỏng; không có tích hợp ngân hàng, ví điện tử, cổng nạp/rút hay chuyển khoản thật. Không cam kết khoản tiền nào đã được giữ hoặc chi trả ngoài sổ mô phỏng.

## Hỗ trợ

Thông tin pháp nhân, địa chỉ liên hệ, hotline và email hỗ trợ cần được đơn vị vận hành bổ sung trước khi sử dụng cho giao dịch thật.
