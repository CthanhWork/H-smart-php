# Hướng dẫn lấy API key Gemini và bật AI sau khi pull code H-Smart

Dành cho người mới tải hoặc cập nhật mã nguồn, kể cả người chưa dùng Google AI Studio. Cập nhật ngày 06/11/2026. Tên nút của Google có thể thay đổi; các liên kết chính thức bên dưới giúp kiểm tra giao diện mới.

## 1. Bạn cần chuẩn bị gì?

- Mã nguồn H-Smart trên máy, có `docker-start.ps1`, `compose.yaml` và `.env.example` ở thư mục gốc.
- Docker Desktop đã mở và Docker Engine đang chạy.
- Tài khoản Google có quyền sử dụng AI Studio và kết nối Internet.

**Google AI Studio** là trang của Google để thử Gemini và quản lý khóa kết nối. Bạn đăng nhập bằng tài khoản Google, không cần tạo một tài khoản H-Smart trên trang này. **API key** là khóa cho phép ứng dụng gọi Gemini; mỗi người chạy dự án nên tạo khóa riêng. **Project/dự án Google Cloud** là nơi Google quản lý khóa, quyền và mức sử dụng; khác với thư mục mã nguồn trên máy.

Gemini hỗ trợ chatbot diễn đạt câu trả lời và viết mô tả sản phẩm. YOLO nhận diện ảnh chạy trong Docker bằng model có sẵn, không cần khóa Gemini. Nếu chưa có khóa, chatbot có thể trả trích đoạn tài liệu nhưng chưa tổng hợp bằng model; tạo mô tả sẽ chưa hoạt động.

Nếu không mở được AI Studio, kiểm tra điều kiện tài khoản: Google yêu cầu từ 18 tuổi, khu vực được hỗ trợ và có thể cần xác minh tuổi. Xem [điều kiện truy cập chính thức](https://ai.google.dev/gemini-api/docs/available-regions).

## 2. Đăng nhập Google AI Studio

1. Mở [Google AI Studio](https://aistudio.google.com/).
2. Đăng nhập bằng tài khoản Google của bạn.
3. Đọc và chấp nhận điều khoản nếu Google yêu cầu.
4. Mở [trang API Keys](https://aistudio.google.com/api-keys), hoặc tìm mục **Get API key / API Keys** trong giao diện.

Người dùng mới có thể đã được Google tạo sẵn project và khóa. Nếu có khóa thuộc project của bạn, có thể dùng khóa đó; nếu cần khóa riêng cho H-Smart, chọn **Create API key**. Xem [hướng dẫn bắt đầu của Google](https://ai.google.dev/gemini-api/docs/get-started).

## 3. Tạo khóa cho H-Smart

1. Tại **API Keys**, nhấn **Create API key**.
2. Đặt tên dễ nhớ, chẳng hạn `H-Smart-local`, nếu hộp thoại có ô tên.
3. Chọn project của bạn; nếu chưa có, dùng lựa chọn tạo project mới mà giao diện cung cấp.
4. Hoàn tất tạo khóa, mở chi tiết khóa và dùng nút sao chép.

Nếu project cũ chưa xuất hiện, mở **Dashboard → Projects → Import projects**, chọn project của mình rồi quay lại **API Keys**. Nếu không có quyền tạo khóa, chọn project cá nhân có quyền phù hợp hoặc nhờ chủ project cấp quyền. Khóa mới có thể được hiển thị là **Auth key**; sao chép nguyên giá trị, không đổi nội dung. Xem [hướng dẫn quản lý khóa của Google](https://ai.google.dev/gemini-api/docs/generate-content/api-key).

**Lưu ý về chi phí:** tạo khóa không có nghĩa mọi model đều dùng miễn phí không giới hạn. Free Tier chỉ hỗ trợ một số model trong hạn mức; Paid Tier có tính phí. Kiểm tra gói và mức sử dụng của project trước khi bật thanh toán. Hướng dẫn này không yêu cầu bật Paid Tier; khả năng sử dụng tùy model và tài khoản. Xem [cơ chế thanh toán](https://ai.google.dev/gemini-api/docs/billing/) và [bảng giá Gemini](https://ai.google.dev/gemini-api/docs/pricing).

## 4. Khởi động dự án để tạo cấu hình cục bộ

Mở PowerShell ở thư mục gốc H-Smart. Ví dụ nếu mã nguồn nằm ở `D:\H-smart-php`:

```powershell
Set-Location D:\H-smart-php
.\docker-start.ps1
```

Thay đường dẫn nếu bạn lưu mã nguồn ở nơi khác. Lần đầu, script tạo `.env` từ `.env.example`, sinh mật khẩu cơ sở dữ liệu rồi build và khởi động ứng dụng. Bạn có thể thấy thông báo chưa có khóa Gemini; tiếp tục các bước dưới để thêm khóa.

Nếu đã chạy dự án và có `.env`, giữ tệp đó. Không chép đè toàn bộ bằng `.env.example`, vì có thể làm mất mật khẩu database và cấu hình đang dùng. Người chỉ `git pull` bản cập nhật cũng dùng lại `.env` hiện có.

Với chế độ tiết kiệm RAM, dùng lệnh này thay cho lệnh khởi động thông thường:

```powershell
.\docker-start.ps1 -LowMemory
```

## 5. Dán khóa vào đúng tệp `.env`

Mở **`.env` ở thư mục gốc**, cùng cấp với `compose.yaml`. Có thể dùng Notepad:

```powershell
notepad .env
```

Tìm các dòng `AI_` và sửa thành:

```dotenv
AI_BASE_URL=https://generativelanguage.googleapis.com/v1beta/openai
AI_API_KEY=DAN_KHOA_CUA_BAN_VAO_DAY
AI_CHAT_MODEL=gemini-2.5-flash
```

Thay riêng `DAN_KHOA_CUA_BAN_VAO_DAY` bằng toàn bộ khóa đã sao chép, rồi lưu tệp. Mỗi biến chỉ giữ một dòng. Không để khoảng trắng hoặc xuống dòng bên trong khóa.

| Dòng cấu hình | Ý nghĩa dễ hiểu |
| --- | --- |
| `AI_BASE_URL` | Địa chỉ kết nối Gemini mà dự án sử dụng; giữ nguyên như ví dụ |
| `AI_API_KEY` | Khóa riêng bạn vừa tạo |
| `AI_CHAT_MODEL` | Tên model dự án yêu cầu dùng; `gemini-2.5-flash` là cấu hình mẫu trong repo |

Với cách chạy Docker của repo, không dán khóa vào `.env.example`, `backend/.env`, mã React hoặc `.env` nằm ngoài thư mục dự án. Docker Compose đọc các biến trên từ `.env` gốc rồi chuyển chúng vào container AI thành `OPENAI_BASE_URL`, `OPENAI_API_KEY`, `OPENAI_CHAT_MODEL`. Tiền tố `OPENAI_` là tên biến của lớp kết nối tương thích; nhà cung cấp trong cấu hình này vẫn là Gemini.

Tên model trong ví dụ là mặc định của dự án, không bảo đảm tài khoản nào cũng được gọi model đó mãi mãi. Nếu Google báo model không tồn tại hoặc không có quyền truy cập, kiểm tra model được hỗ trợ cho project và khả năng tương thích trước khi đổi `AI_CHAT_MODEL`.

## 6. Áp dụng cấu hình vừa lưu

Với chế độ thông thường, chạy từ thư mục gốc:

```powershell
docker compose up -d --build ai backend
```

Lệnh này build phần AI mới nhất và tạo lại container khi cấu hình thay đổi. Chỉ `restart` container cũ có thể chưa nạp khóa mới. Không cần xóa database hoặc Docker volumes.

Nếu đang dùng chế độ tiết kiệm RAM, giữ đúng hai tệp Compose:

```powershell
docker compose -f compose.yaml -f compose.low-memory.yaml up -d --build ai backend
```

Nếu chưa khởi động đầy đủ các dịch vụ, dùng lại `docker-start.ps1` hoặc `docker-start.ps1 -LowMemory` theo chế độ đang chọn. Người chạy Docker qua WSL thay vì lệnh Docker trên Windows có thể chạy lại script này để script chọn cách gọi phù hợp.

## 7. Kiểm tra đã nạp khóa và thử trên giao diện

### Kiểm tra cấu hình mà không hiển thị khóa

```powershell
docker compose ps
docker compose exec ai python -c "import os; print('API key configured:', bool(os.getenv('OPENAI_API_KEY', '').strip())); print('Model:', os.getenv('OPENAI_CHAT_MODEL'))"
```

Kết quả mong đợi:

```text
API key configured: True
Model: gemini-2.5-flash
```

`True` chỉ xác nhận có giá trị khóa trong container, chưa xác nhận Google chấp nhận khóa hoặc còn hạn mức. Không chụp hoặc chia sẻ nội dung khóa để nhờ kiểm tra.

### Thử tạo mô tả sản phẩm

1. Mở [H-Smart trên máy](http://localhost:5174), đăng ký/đăng nhập. Tài khoản mới cần xác nhận email; hộp thư demo ở [Mailpit](http://localhost:8026).
2. Mở **Đăng bán** và nhập tên, chọn danh mục, tình trạng.
3. Trong ô mô tả, nhập dữ kiện mẫu: “Bàn học màu trắng, mặt bàn 100 x 50 cm. Đã dùng một năm. Mặt bàn có vết xước nhỏ ở góc phải. Không kèm ghế.”
4. Nhấn **Tạo mô tả bằng AI**. Có thể thử không kèm ảnh để kiểm tra riêng Gemini.
5. Đọc bản nháp: dữ kiện về vết xước và không kèm ghế phải được giữ, không tự thêm thương hiệu hoặc bảo hành. Khi đủ thông tin, prompt hướng tới 5–8 câu trong 2 đoạn ngắn; khi ít dữ kiện, nội dung có thể ngắn hơn.

Nội dung mới thay ô mô tả hiện tại. Đây vẫn là bản nháp để bạn sửa; nút tạo mô tả không tự đăng sản phẩm. Không cần gửi tin đăng nếu chỉ muốn thử AI.

### Thử chatbot và nhận diện ảnh

Mở `/assistant`, hỏi “Làm thế nào để đăng bán sản phẩm?”. Lưu ý chatbot vẫn có thể trả trích đoạn khi chưa có khóa, nên chỉ thấy câu trả lời chưa đủ kết luận Gemini đang chạy. Tạo mô tả thành công là phép thử riêng cho chức năng cần model.

Nút **Phân tích ảnh** dùng YOLO nội bộ; nhận diện ảnh thành công cũng không chứng minh khóa Gemini hoạt động. Tương tự, `/health` chỉ kiểm tra detector, không thử khóa với Google.

## 8. Lỗi thường gặp

| Hiện tượng | Việc cần làm |
| --- | --- |
| `API key configured: False` | Kiểm tra đúng `.env` gốc, khóa không rỗng, đã lưu tệp và chạy lại lệnh áp dụng ở mục 6 |
| Khóa đã nạp nhưng tạo mô tả lỗi | Kiểm tra khóa, quyền project, model và hạn mức trong AI Studio; H-Smart hiện gom lỗi model thành thông báo tạm thời không khả dụng |
| Không có quyền tạo key hoặc project không xuất hiện | Import project và kiểm tra quyền tài khoản theo mục 3 |
| Không truy cập được AI Studio | Kiểm tra tài khoản, điều kiện tuổi và khu vực theo mục 1 |
| Đăng nhập/đăng bán lỗi ngay cả khi chưa gọi AI | Kiểm tra đầy đủ các dịch vụ bằng `docker compose ps`; đây có thể là lỗi ứng dụng hoặc database |
| Phân tích ảnh được nhưng tạo mô tả không được | YOLO không cần khóa; kiểm tra riêng cấu hình Gemini và quyền gọi model |
| Mô tả vẫn ngắn | Nhập thêm dữ kiện thật: đặc điểm, kích thước, tình trạng, lỗi, phụ kiện. Prompt ưu tiên chính xác, không tự bịa để đủ số câu |
| H-Smart báo gửi quá nhiều yêu cầu | Chờ trước khi thử lại: tạo mô tả giới hạn 5 lần/phút/tài khoản; chatbot và nhận diện ảnh mỗi loại 10 lần/phút |

Nếu có kiểm tra trực tiếp API Google, các mã `401/403` thường liên quan xác thực/quyền, `404` có thể do model hoặc địa chỉ không đúng, `429` do hạn mức, `503` do dịch vụ tạm thời không đáp ứng. Mã `503` của H-Smart không nhất thiết là `503` từ Google, vì backend đang gom nhiều lỗi thành cùng một mã. Xem [bảng lỗi API chính thức](https://ai.google.dev/gemini-api/docs/api-errors).

## 9. Giữ khóa riêng và thay khóa khi cần

Khóa chỉ lưu ở `.env` cục bộ hoặc cấu hình bí mật của môi trường chạy. Không commit khóa, gửi khóa vào nhóm chat hoặc đưa vào ảnh chụp. `.env` đã được repo bỏ qua bằng `.gitignore`; `.env.example` chỉ giữ mẫu trống.

Nếu khóa đã lộ, tạo khóa mới trong AI Studio, cập nhật `.env`, áp dụng lại mục 6 và thu hồi khóa cũ. Sau mỗi lần đổi khóa, thử lại mục 7. Đừng dùng chung khóa của người khác chỉ vì đã pull cùng mã nguồn.

Xem thêm [mô tả các chức năng AI](MO_TA_CHUC_NANG_AI.md) để hiểu dữ liệu được gửi cho Gemini, cách YOLO hoạt động và giới hạn của kết quả AI.
