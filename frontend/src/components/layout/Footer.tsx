import { ArrowRight } from "lucide-react"
import { Button } from "../ui/button"

type FooterProps = { onSignUp: () => void; navigate: (path: string) => void }

const Footer = ({ onSignUp, navigate }: FooterProps) => {
  return <footer className="mt-16 bg-[#F0F0F0]">
    <div className="mx-auto max-w-frame px-4 pt-8 xl:px-0">
      <section className="flex flex-col gap-5 rounded-[20px] bg-black p-6 text-white sm:flex-row sm:items-center sm:justify-between sm:p-8">
        <div><p className="font-satoshi font-bold text-xl sm:text-2xl">Mua bán ngay trên H-Smart</p><p className="mt-2 max-w-lg text-sm text-white/65">Đăng ký bằng email, tìm món cần mua hoặc đăng bán món đồ bạn không còn dùng.</p></div>
        <Button variant="outline" className="shrink-0 border-white/30 bg-white text-black hover:bg-white/90" onClick={onSignUp}>Tạo tài khoản <ArrowRight size={16} className="ml-2" /></Button>
      </section>
      <div className="grid grid-cols-2 gap-8 py-10 md:grid-cols-4">
        <div className="col-span-2 md:col-span-1"><h2 className="mb-3 font-integralCF text-2xl uppercase">H-SMART</h2><p className="max-w-xs text-sm leading-relaxed text-black/60">Chợ đồ cũ dành cho sinh viên. Tài khoản cần xác nhận email trước khi mua bán.</p></div>
        <div><h3 className="mb-4 text-sm font-bold uppercase tracking-wider">Khám phá</h3><ul className="space-y-3 text-sm text-black/60"><li><button className="hover:text-black" onClick={() => navigate('/products')}>Sản phẩm đang bán</button></li><li><button className="hover:text-black" onClick={() => navigate('/sell')}>Đăng bán sản phẩm</button></li><li><button className="hover:text-black" onClick={() => navigate('/me/messages')}>Tin nhắn</button></li></ul></div>
        <div><h3 className="mb-4 text-sm font-bold uppercase tracking-wider">Tài khoản</h3><ul className="space-y-3 text-sm text-black/60"><li><button className="hover:text-black" onClick={onSignUp}>Đăng ký</button></li><li><button className="hover:text-black" onClick={() => navigate('/')}>Đăng nhập</button></li><li><button className="hover:text-black" onClick={() => navigate('/me/orders')}>Đơn hàng</button></li></ul></div>
        <div><h3 className="mb-4 text-sm font-bold uppercase tracking-wider">Hỗ trợ</h3><p className="text-sm leading-relaxed text-black/60">Mở mục Đơn hàng để xem thông tin giao dịch và tình trạng đơn.</p></div>
      </div>
      <div className="flex flex-col items-center justify-between gap-2 border-t border-black/10 py-5 text-center text-xs text-black/50 sm:flex-row sm:text-left"><p>H-Smart © {new Date().getFullYear()} — Chợ đồ cũ sinh viên Việt Nam.</p></div>
    </div>
  </footer>
}

export default Footer
