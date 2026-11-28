# Hướng dẫn sử dụng H-Smart

Tài liệu hỗ trợ người dùng H-Smart. Nội dung này là nguồn được duyệt cho trợ lý hỏi đáp. Trợ lý chỉ trả lời dựa trên tài liệu này; nếu thiếu thông tin, hãy liên hệ quản trị viên qua mục Tin nhắn.

## Tài khoản và bảo mật

Đăng ký bằng email và mật khẩu, sau đó mở email xác nhận để kích hoạt tài khoản. Nếu không thấy email, có thể yêu cầu gửi lại email xác nhận. Dùng chức năng Quên mật khẩu để nhận liên kết đặt lại. Không chia sẻ mật khẩu, mã xác thực hoặc token đăng nhập với bất kỳ ai.

## Tìm và xem sản phẩm

Mở mục Mua sắm để tìm theo từ khóa, lọc theo danh mục, tình trạng và khoảng giá, hoặc sắp xếp theo giá hay thời gian đăng. Trang chi tiết hiển thị mô tả, giá, tình trạng, thông tin người bán và đánh giá. Có thể lưu sản phẩm vào mục Yêu thích.

## Đăng bán sản phẩm

Chọn Đăng bán, nhập tên, mô tả, giá, danh mục, tình trạng và tải ảnh sản phẩm lên. Tin mới có thể cần được quản trị viên duyệt trước khi xuất hiện trong danh sách đang bán. Người bán quản lý tin trong mục Sản phẩm của tôi.

## Đề nghị giá và đặt hàng

Người mua có thể gửi đề nghị giá nếu tin cho phép thương lượng. Người bán xem đề nghị và chấp nhận hoặc từ chối; người mua có thể hủy đề nghị còn hiệu lực. Sau khi đề nghị được chấp nhận, người mua có thể tạo đơn và nhập địa chỉ nhận hàng. Hãy kiểm tra thông tin đơn trước khi xác nhận.

## Thanh toán và vận chuyển

Đơn hàng có thể thanh toán qua VNPay Sandbox hoặc chọn thanh toán khi nhận hàng (COD). Với COD có đặt cọc, phần cọc được thanh toán online; phần còn lại được thu khi giao và phải được ghi nhận/đối soát theo phương thức thu thực tế. Người mua bấm “Đã nhận được hàng” để xác nhận giao hàng, thao tác này không tự xác nhận COD đã được thu đủ. Quản trị viên xác nhận giao hàng, đối soát COD và duyệt giải ngân vào Ví của tôi. Nạp ví và thanh toán đơn VNPay chỉ chạy trong Sandbox; không tích hợp ngân hàng production.

## Ví của tôi và chi trả

Mở **Ví của tôi** để xem số dư khả dụng, tiền bán hàng đang chờ quản trị viên giải ngân, tiền đang giữ cho yêu cầu rút và lịch sử giao dịch. Nạp ví dùng VNPay Sandbox; ví chỉ được ghi có sau khi callback hợp lệ đã qua kiểm tra chữ ký, số tiền, mã tham chiếu và xử lý lặp.

Người bán có thể lưu tên ngân hàng, số tài khoản và tên chủ tài khoản trong mục **Tài khoản ngân hàng**. Chỉ nhập dữ liệu thử nghiệm; không nhập OTP, mật khẩu ngân hàng, PIN, CVV hoặc thông tin đăng nhập. Số tài khoản hiển thị được che một phần. Khi tạo yêu cầu rút, hệ thống giữ khoản tiền và chụp thông tin tài khoản làm snapshot để người bán kiểm tra; đổi hồ sơ sau đó không sửa yêu cầu cũ. Muốn đổi đích nhận khi yêu cầu còn chờ duyệt, hãy hủy yêu cầu rồi tạo lại.

Chi trả cho người bán do quản trị viên xử lý thủ công trong phạm vi demo/Sandbox. VNPay Sandbox của dự án không cung cấp luồng chuyển tiền ra ngân hàng; trạng thái do quản trị viên ghi nhận không phải bằng chứng chuyển khoản ngân hàng thật. Nếu kết quả chưa rõ, khoản tiền tiếp tục được giữ để đối soát. Hoàn tiền cho người mua là nghiệp vụ riêng theo hủy đơn/khiếu nại và chỉ dùng khoản hệ thống thực sự đang giữ.

Sau khi giao hàng được xác nhận hợp lệ, thời hạn xem xét khiếu nại là 48 giờ. Hết thời hạn, đơn chỉ chuyển sang **Chờ quản trị viên giải ngân** nếu đã thu/đối soát đủ tiền và không có khiếu nại hoặc lệnh giữ. Người mua xác nhận nhận hàng không rút ngắn thời hạn; tiền chỉ vào số dư khả dụng sau khi quản trị viên xác nhận giải ngân.

## Phí sàn

Đăng tin không mất phí. Khi tạo đơn, hệ thống chụp mức hoa hồng đang cấu hình trên đơn; bản demo mặc định là 5% giá sản phẩm đã thỏa thuận. Người mua trả tổng giá sản phẩm và phí giao hàng. Phí sàn được khấu trừ vào phần tiền người bán nhận sau khi đơn COD được giao và tiền đã đối soát.

## Tin nhắn, thông báo và đánh giá

Mục Tin nhắn dùng để trao đổi với người mua hoặc người bán liên quan đến tin đăng; mục Tin nhắn hỗ trợ dùng để liên hệ quản trị viên. Thông báo ghi nhận tin nhắn, đề nghị giá và mốc đơn hàng. Sau khi giao dịch hoàn tất, người mua đủ điều kiện có thể gửi đánh giá cho sản phẩm.

## Báo cáo nội dung

Tại trang chi tiết sản phẩm, người dùng có thể báo cáo sản phẩm hoặc người bán và gửi lý do kèm thông tin bổ sung. Quản trị viên xem xét báo cáo và có thể xử lý tin đăng hoặc tài khoản theo quy định.

## Trợ giúp và dữ liệu riêng tư

Trợ lý trả lời câu hỏi chung về cách sử dụng H-Smart dựa trên tài liệu đã duyệt và có thể tra cứu trạng thái đơn hàng mà người hỏi tham gia. Khi tra cứu đơn, Laravel xác thực quyền truy cập và gửi thông tin giao dịch trực tiếp vào câu trả lời; dữ liệu đơn không gửi sang dịch vụ AI. Trợ lý không tra cứu thông tin tài khoản ngân hàng hoặc tin nhắn riêng. Không nhập mật khẩu, mã xác thực hay thông tin thanh toán vào câu hỏi. Nếu cần hỗ trợ giao dịch, hãy liên hệ quản trị viên qua Tin nhắn hỗ trợ.
