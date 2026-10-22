import { ArrowRight } from "lucide-react"
import { Button } from "../ui/button"

type FooterProps = { isAuthenticated: boolean; isAdmin?: boolean; onSignUp: () => void; onLogin: () => void; navigate: (path: string) => void }

const Footer = ({ isAuthenticated, isAdmin = false, onSignUp, onLogin, navigate }: FooterProps) => {
  return <footer className="mt-16 bg-[#F0F0F0]">
    <div className="mx-auto max-w-frame px-4 pt-8 xl:px-0">
      <section className="flex flex-col gap-5 rounded-[20px] bg-black p-6 text-white sm:flex-row sm:items-center sm:justify-between sm:p-8">
        <div><p className="font-satoshi font-bold text-xl sm:text-2xl">{isAuthenticated ? 'Tiếp tục mua bán trên H-Smart' : 'Mua bán ngay trên H-Smart'}</p><p className="mt-2 max-w-lg text-sm text-white/65">{isAuthenticated ? 'Khám phá sản phẩm mới hoặc quản lý tin đăng và đơn hàng của bạn.' : 'Đăng ký bằng email, tìm món cần mua hoặc đăng bán món đồ bạn không còn dùng.'}</p></div>
        <Button variant="outline" className="shrink-0 border-white/30 bg-white text-black hover:bg-white/90" onClick={() => navigate(isAuthenticated ? '/products' : '/register')}>{isAuthenticated ? 'Khám phá sản phẩm' : 'Tạo tài khoản'} <ArrowRight size={16} className="ml-2" /></Button>
      </section>
      <div className="grid grid-cols-2 gap-8 py-10 md:grid-cols-4">
        <div className="col-span-2 md:col-span-1"><h2 className="mb-3 font-integralCF text-2xl uppercase">H-SMART</h2><p className="max-w-xs text-sm leading-relaxed text-black/60">{isAuthenticated ? 'Cộng đồng sinh viên chia sẻ và tìm mua những món đồ đã qua sử dụng.' : 'Chợ đồ cũ dành cho sinh viên. Tài khoản cần xác nhận email trước khi mua bán.'}</p></div>
        <div><h3 className="mb-4 text-sm font-bold uppercase tracking-wider">Khám phá</h3><ul className="space-y-3 text-sm text-black/60"><li><button className="hover:text-black" onClick={() => navigate('/products')}>Sản phẩm đang bán</button></li><li><button className="hover:text-black" onClick={() => navigate(isAuthenticated ? '/sell' : '/register')}>Đăng bán sản phẩm</button></li>{isAuthenticated && <li><button className="hover:text-black" onClick={() => navigate(isAdmin ? '/admin/messages' : '/me/messages')}>{isAdmin ? 'Tin nhắn hỗ trợ' : 'Tin nhắn'}</button></li>}</ul></div>
        <div><h3 className="mb-4 text-sm font-bold uppercase tracking-wider">{isAuthenticated ? 'Tài khoản của bạn' : 'Tài khoản'}</h3><ul className="space-y-3 text-sm text-black/60">{isAuthenticated ? <><li><button className="hover:text-black" onClick={() => navigate('/me/products')}>Sản phẩm của tôi</button></li><li><button className="hover:text-black" onClick={() => navigate('/me/orders')}>Đơn hàng</button></li><li><button className="hover:text-black" onClick={() => navigate('/me/profile')}>Hồ sơ và địa chỉ</button></li></> : <><li><button className="hover:text-black" onClick={onSignUp}>Đăng ký</button></li><li><button className="hover:text-black" onClick={onLogin}>Đăng nhập</button></li></>}</ul></div>
        <div><h3 className="mb-4 text-sm font-bold uppercase tracking-wider">Hỗ trợ</h3><p className="text-sm leading-relaxed text-black/60">Mở mục Đơn hàng để xem thông tin giao dịch và tình trạng đơn.</p></div>
      </div>
      <div className="flex flex-col items-center justify-between gap-2 border-t border-black/10 py-5 text-center text-xs text-black/50 sm:flex-row sm:text-left"><p>H-Smart © {new Date().getFullYear()} — Chợ đồ cũ sinh viên Việt Nam.</p></div>
    </div>
  </footer>
}

export default Footer
