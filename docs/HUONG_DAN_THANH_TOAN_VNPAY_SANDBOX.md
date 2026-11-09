# Hướng dẫn chạy thanh toán VNPay Sandbox

Tài liệu này hướng dẫn tải H-Smart từ GitHub, cấu hình thông tin merchant Sandbox và chạy một giao dịch thử trên máy cá nhân. Sandbox chỉ dùng để kiểm thử; giao dịch không phải thanh toán tiền thật.

## 1. Lấy mã nguồn và khởi động H-Smart

Cài Docker Desktop, bật Docker Engine, rồi chạy PowerShell:

```powershell
git clone https://github.com/CthanhWork/H-smart-php.git
cd H-smart-php
git checkout develop
```

Nếu chức năng VNPay đã được gộp vào nhánh chính, có thể bỏ lệnh `git checkout`.

Khởi động ứng dụng. Lần đầu, script sẽ tạo file `.env` riêng trên máy này từ `.env.example`:

```powershell
.\docker-start.ps1
```

> Lần chạy đầu có thể mất vài phút để Docker tải image và build. Khi hoàn tất, mở giao diện tại <http://localhost:5174>. Hộp thư xác thực email dùng <http://localhost:8026>; backend API dùng <http://localhost:8001>.

Nếu ứng dụng đã chạy, sau khi sửa `.env` hãy áp dụng cấu hình mới cho backend:

```powershell
docker compose up -d --build backend
docker compose logs -f backend
```

## 2. Cấu hình thông tin Sandbox

Lấy `TMN_CODE` và `HASH_SECRET` từ tài khoản merchant Sandbox của người kiểm thử. Không dùng thông tin production. Không đưa mã merchant hoặc secret vào commit, ảnh chụp màn hình, báo cáo công khai hay tin nhắn gửi lên GitHub.

Sửa các dòng VNPay trong file `.env` ở thư mục gốc. Nếu vừa chạy lần đầu theo mục 1, file này đã được tạo tự động. Dùng mẫu sau:

```dotenv
VNPAY_TMN_CODE=TMN_CODE_SANDBOX_CUA_BAN
VNPAY_MODE=gateway
VNPAY_HASH_SECRET=HASH_SECRET_SANDBOX_CUA_BAN
VNPAY_PAY_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
VNPAY_RETURN_URL=http://localhost:8001/api/v1/payments/vnpay/return
VNPAY_IPN_URL=
VNPAY_RESULT_REDIRECT_URL=http://localhost:5174/payment-result
VNPAY_SANDBOX_RETURN_FALLBACK=true
```

Giữ nguyên URL Sandbox như trên. `VNPAY_SANDBOX_RETURN_FALLBACK=true` cho phép backend local xử lý callback Return có chữ ký hợp lệ trong môi trường Sandbox, vì máy chủ VNPay không thể gọi địa chỉ `localhost`. Mã nguồn chỉ bật cách này khi host cổng thanh toán là `sandbox.vnpayment.vn`; không dùng fallback cho production.

Mỗi máy clone cấu hình `.env` riêng. Chỉ commit `.env.example`; không commit `.env`, mã `TMN_CODE` hoặc `HASH_SECRET`. Có thể dùng chung merchant Sandbox trong nhóm nếu chủ tài khoản chia sẻ credentials qua password manager/kênh bảo mật. Chế độ mặc định trong repo là `VNPAY_MODE=mock`; đặt `gateway` sau khi điền thông tin Sandbox.

Địa chỉ Return phải trùng với địa chỉ đã đăng ký trong cấu hình merchant VNPay. Nếu VNPay không cho đăng ký `localhost`, dùng cách tạo HTTPS tunnel ở mục 3. Không điền `localhost` làm IPN URL trên VNPay rồi kỳ vọng máy chủ VNPay truy cập được máy cá nhân.

Nếu merchant chỉ chấp nhận hostname đã duyệt `hsmart.thatcherdev.id.vn`, chọn một máy làm host Sandbox dùng chung. Trỏ DNS về máy đó, chuyển tiếp cổng 80/443 tới máy chạy Docker, đặt Return/IPN thành URL HTTPS của hostname và bật proxy bằng `docker compose --profile public-https up -d --build`. Caddy profile này mặc định không chạy trên các máy clone khác. Một hostname/DNS record không thể đồng thời chuyển callback tới nhiều máy local khác nhau; muốn developer thử độc lập cần hostname public riêng được VNPay duyệt cho từng người, hoặc dùng chung host Sandbox.

## 3. (Khuyến nghị) Nhận IPN thật qua HTTPS tunnel

Để kiểm tra máy chủ VNPay gửi thông báo IPN về ứng dụng, backend cần có địa chỉ HTTPS công khai. Có thể dùng ngrok hoặc một tunnel HTTPS tương đương trong lúc phát triển. Ví dụ, sau khi cài và đăng nhập ngrok:

```powershell
ngrok http 8001
```

Ngrok sẽ hiển thị một URL HTTPS, ví dụ `https://abc123.ngrok-free.app`. Giữ cửa sổ tunnel chạy và dùng chính xác tên miền hiện tại trong các cấu hình sau:

```dotenv
VNPAY_RETURN_URL=https://abc123.ngrok-free.app/api/v1/payments/vnpay/return
VNPAY_IPN_URL=https://abc123.ngrok-free.app/api/v1/payments/vnpay/ipn
VNPAY_RESULT_REDIRECT_URL=http://localhost:5174/payment-result
VNPAY_SANDBOX_RETURN_FALLBACK=false
```

Cập nhật cả Return URL và IPN URL trong cổng quản lý merchant Sandbox nếu cổng đó yêu cầu khai báo callback. Sau đó khởi động lại backend để nhận `.env` mới. URL ngrok miễn phí thường đổi khi tạo tunnel mới; mỗi lần đổi cần cập nhật `.env`, cấu hình merchant và khởi động lại backend.

Với tunnel đang hoạt động, trình duyệt quay về backend qua URL HTTPS và máy chủ VNPay có thể gọi IPN. Nếu chỉ thử giao diện local bằng fallback, để `VNPAY_IPN_URL` trống và `VNPAY_SANDBOX_RETURN_FALLBACK=true` như mục 2.

## 4. Tạo đơn và thanh toán thử

Đăng ký hai tài khoản riêng và xác thực email trong Mailpit tại <http://localhost:8026>:

1. Tài khoản người bán đăng tin một sản phẩm.
2. Tài khoản người mua gửi đề nghị mua sản phẩm.
3. Người bán vào **Đề nghị**, chấp nhận đề nghị đó.
4. Người mua vào đề nghị đã được chấp nhận, chọn **Tạo đơn hàng**, nhập thông tin nhận hàng.
5. Trong **Đơn hàng**, chọn **Thanh toán qua VNPay**. H-Smart tạo URL đã ký và chuyển trình duyệt sang cổng Sandbox.
6. Dùng thông tin thẻ thử do VNPay cung cấp cho merchant Sandbox. Hoàn tất các bước tại cổng; sau khi quay lại H-Smart, kiểm tra trạng thái đơn trong **Đơn hàng**.

Đơn chờ thanh toán được giữ trong 30 phút. Nếu hết hạn, tạo lại đơn từ đề nghị đã chấp nhận.

## 5. Cách hiểu kết quả và xử lý lỗi thường gặp

- **“Thanh toán VNPay chưa được cấu hình”**: kiểm tra `VNPAY_TMN_CODE` và `VNPAY_HASH_SECRET` trong `.env`, sau đó chạy lại `docker compose up -d --build backend`.
- **VNPay báo mã 71 / “The terminal (website) not approved”**: merchant Sandbox chưa được VNPay duyệt hoặc cấu hình merchant chưa đúng. Việc đổi Return URL, bật fallback hay khởi động lại local không thể thay thế bước duyệt merchant; liên hệ VNPay hoặc quản trị tài khoản Sandbox.
- **Đã thanh toán ở Sandbox nhưng đơn chưa thành “Đã thanh toán” khi dùng tunnel**: kiểm tra tunnel còn chạy, `VNPAY_IPN_URL` là HTTPS công khai và đúng path `/api/v1/payments/vnpay/ipn`, URL callback trong merchant trùng cấu hình, rồi xem `docker compose logs -f backend`.
- **Đơn vẫn đang xử lý sau khi trình duyệt quay về**: với cấu hình IPN, trang Return chỉ báo kết quả trình duyệt; trạng thái đơn được xác nhận từ IPN. Tải lại trang đơn sau ít giây và kiểm tra log backend.
- **Trang kết quả báo thất bại hoặc không xác minh được giao dịch**: kiểm tra `TMN_CODE`, `HASH_SECRET`, URL Sandbox, thời hạn đơn và cấu hình callback. Không sửa trạng thái thanh toán trực tiếp trong database để làm cho bài thử có vẻ thành công.

Trong lần kiểm tra của dự án ngày 02/11/2026, 20 ca xử lý callback có chữ ký hợp lệ và dữ liệu được rollback đều đạt. Lượt gọi checkout thực tế tới Sandbox bị mã 71 vì merchant chưa được duyệt, nên chưa hoàn tất giao dịch đầu cuối qua cổng ngân hàng. Đây là giới hạn phê duyệt merchant, không phải kết quả cho thấy thanh toán tiền thật đã hoạt động.

## 6. Trước khi triển khai production

Không dùng URL Sandbox, thông tin Sandbox hoặc `VNPAY_SANDBOX_RETURN_FALLBACK=true` cho production. Môi trường production cần thông tin merchant production, tên miền HTTPS công khai, cấu hình Return/IPN đã đăng ký với VNPay và `VNPAY_SANDBOX_RETURN_FALLBACK=false`. Chỉ chuyển sang nhận thanh toán thật sau khi merchant production được VNPay cấp quyền và luồng IPN đã được cấu hình.

## 7. Chạy đầy đủ chức năng AI

Phần này dành cho người đã có thư mục mã nguồn H-Smart trên máy và muốn bật AI. Không cần cài Python, Laravel, Node.js hay MySQL riêng: Docker sẽ chạy các dịch vụ của H-Smart, gồm AI service. AI có ba chức năng: chatbot hỏi đáp theo tài liệu H-Smart, YOLO nhận diện ảnh sản phẩm và Gemini viết bản nháp mô tả. YOLO chạy bằng model đã có trong repo, không cần khóa; chatbot trả lời tổng hợp và tạo mô tả cần khóa Gemini.

### Bước 1: Chuẩn bị Docker và khóa Gemini

1. Cài Docker Desktop và mở ứng dụng. Chờ đến khi Docker báo đang chạy.
2. Tạo một khóa Gemini riêng tại Google AI Studio. Khóa này cho phép H-Smart gọi Gemini để trả lời chatbot và tạo mô tả; mỗi người chạy dự án nên tự dùng khóa của mình.
3. Mở PowerShell ngay tại thư mục dự án H-Smart. Đây là thư mục có file `docker-start.ps1`, `compose.yaml` và `.env.example`. Nếu chưa có `.env`, chạy script một lần để script tự tạo file cấu hình với mật khẩu database ngẫu nhiên:

   ```powershell
   .\docker-start.ps1
   ```

   Lần đầu Docker tải thư viện, build model AI và khởi động các container nên có thể mất vài phút. Nếu script báo dịch vụ đã khởi động, có thể để cửa sổ đó mở hoặc chạy các lệnh Docker tiếp theo ở một cửa sổ PowerShell khác cùng thư mục dự án.

### Bước 2: Điền khóa Gemini vào cấu hình

Mở file `.env` ở thư mục gốc dự án bằng Notepad hoặc VS Code. Tìm ba dòng bắt đầu bằng `AI_` và điền như sau (thay `DAN_KHOA_GEMINI_CUA_BAN` bằng khóa vừa tạo):

```dotenv
AI_BASE_URL=https://generativelanguage.googleapis.com/v1beta/openai
AI_API_KEY=DAN_KHOA_GEMINI_CUA_BAN
AI_CHAT_MODEL=gemini-2.5-flash
```

Chỉ thay giá trị bên phải dấu `=` ở dòng `AI_API_KEY`. Không xóa các cấu hình khác trong `.env`, đặc biệt `DB_PASSWORD`; không thêm dấu nháy quanh khóa và không gửi file `.env` cho người khác. Lưu file sau khi sửa. File `.env` là cấu hình riêng của máy và không được commit lên GitHub.

### Bước 3: Khởi động lại để nạp cấu hình

Trong PowerShell ở thư mục gốc dự án, chạy:

```powershell
docker compose up -d --build ai backend
```

Lệnh này build và khởi động lại AI service cùng Laravel backend để backend nhận cấu hình mới. Đợi khoảng một phút rồi kiểm tra:

```powershell
docker compose ps
```

Trong danh sách, `ai` và `backend` cần ở trạng thái `running` hoặc `healthy`. YOLO cần tải model vào bộ nhớ khi AI service khởi động; lần khởi động đầu có thể lâu hơn những lần sau.

### Bước 4: Thử trên giao diện

Mở <http://localhost:5174> và đăng nhập. Các chức năng AI yêu cầu tài khoản đã đăng nhập. Nếu chưa có tài khoản, đăng ký từ giao diện rồi mở <http://localhost:8026> để lấy email xác thực.

#### Ví dụ: đăng bán một ấm đun nước cũ

1. Mở <http://localhost:5174/sell> và chọn ảnh ấm đun nước từ thiết bị. Trang đăng bán chấp nhận JPEG, PNG hoặc WebP, tối đa 10 MB mỗi ảnh và tối đa 5 ảnh cho tin đăng.
2. Sau khi ảnh hiện trong khung xem trước, bấm **Phân tích ảnh**. Trong lúc xử lý nút hiện **Đang phân tích…**. Nếu model nhận ra ấm đun nước, kết quả sẽ hiện nhãn “Ấm đun nước” cùng phần trăm tin cậy. Nếu kết quả phù hợp, bấm **Dùng tên này** để điền tên; nếu không, tự nhập tên sản phẩm. Nếu không nhận ra vật thể, có thể tiếp tục điền biểu mẫu bình thường.
3. Điền tên, danh mục, tình trạng, giá và thông tin thật về sản phẩm, ví dụ: “Dung tích 1,7 lít, còn hoạt động tốt, thân có vết trầy nhỏ”. Bấm **Tạo mô tả bằng AI** ở mục mô tả.
4. AI điền bản nháp 1–3 câu vào ô mô tả, chỉ dựa trên thông tin bạn nhập và nhãn YOLO. Chẳng hạn, bản nháp có thể nêu dung tích, tình trạng hoạt động và vết trầy đã khai báo; AI không tự biết hay bịa thương hiệu, chất liệu hoặc phụ kiện. Đọc lại, sửa chi tiết chưa đúng hoặc bổ sung thông tin còn thiếu, rồi tự quyết định có lưu và đăng tin hay không. AI không tự lưu tin.

Hai thao tác ảnh và mô tả có thể dùng riêng: **Phân tích ảnh** chỉ gọi YOLO và không cần Gemini; **Tạo mô tả bằng AI** cần Gemini, có thể dùng kể cả khi không đính kèm ảnh. Nếu có ảnh, YOLO chạy trước; Gemini chỉ nhận dữ liệu người bán nhập và nhãn YOLO, không nhận ảnh gốc.

#### Ví dụ: hỏi trợ lý về cách sử dụng

1. Mở <http://localhost:5174/assistant>. Chọn một câu gợi ý có sẵn hoặc nhập câu hỏi, chẳng hạn: “Làm thế nào để đăng bán sản phẩm?”.
2. Bấm nút gửi. Trong lúc chờ, giao diện hiển thị **Đang tìm trong tài liệu H-Smart…**.
3. Backend Laravel xác thực tài khoản và giới hạn lượt gọi, rồi yêu cầu AI tìm nội dung phù hợp trong các tài liệu Markdown đã duyệt ở `docs/assistant/approved`. Khi có Gemini, model diễn đạt câu trả lời dựa trên những đoạn tìm thấy. Câu hỏi và câu trả lời xuất hiện trong cuộc trò chuyện và được lưu ở lịch sử của chính tài khoản.

Chatbot chỉ trả lời hướng dẫn chung có trong tài liệu; nó không xem đơn hàng, thông tin tài khoản, hoặc thay đổi dữ liệu. Nếu không có khóa Gemini, service vẫn có thể trả các đoạn tài liệu tìm được nhưng không tổng hợp câu trả lời bằng model. Nội dung nguồn không hiện thành danh sách tham khảo trên giao diện.

Các giới hạn hiện tại theo tài khoản là 10 câu chat/phút, 10 lần phân tích ảnh/phút và 5 lần tạo mô tả/phút. Nếu thấy thông báo vượt giới hạn, đợi một lúc rồi thử lại.

### Nếu AI chưa chạy

Kiểm tra Gemini đã được nạp vào container chưa mà không in khóa bí mật ra màn hình:

```powershell
docker compose exec ai python -c "import os; print('Gemini key configured:', bool(os.getenv('OPENAI_API_KEY'))); print('Gemini model:', os.getenv('OPENAI_CHAT_MODEL'))"
```

Kết quả cần có `Gemini key configured: True` và model `gemini-2.5-flash`. Nếu hiện `False`, kiểm tra lại dòng `AI_API_KEY` trong `.env`, lưu file rồi chạy lại `docker compose up -d --build ai backend`.

Kiểm tra YOLO đã nạp model:

```powershell
docker compose exec ai python -c "import urllib.request; print(urllib.request.urlopen('http://127.0.0.1:8000/health').read().decode())"
```

Kết quả bình thường có `status` là `healthy`, `model_loaded` là `true`, và `demo_mode` là `false`. YOLO hoạt động khi không có Gemini key; nếu chỉ YOLO chạy mà chatbot không tổng hợp được hoặc nút tạo mô tả báo lỗi, kiểm tra khóa Gemini còn hiệu lực và đã được nạp bằng lệnh phía trên.

Để xem lỗi chi tiết trong lúc gọi AI:

```powershell
docker compose logs --tail 100 ai
docker compose logs --tail 100 backend
```

Nếu YOLO không khởi động được, xác nhận file `ai-service/models/household_yolo26n_best.pt` có trong thư mục mã nguồn và Docker Desktop còn đủ bộ nhớ. Nếu sửa các tài liệu đã duyệt trong `docs/assistant/approved`, nạp lại chúng bằng lệnh:

```powershell
docker compose up -d --build ai
```
