import { useState } from "react"
import { cn } from "../../lib/utils"
import { LogOut, Menu, User, X } from "lucide-react"

type NavUser = {
  id: number
  email: string
  full_name: string | null
  status: string
  role: 'member' | 'admin'
}

type NavbarProps = {
  user: NavUser | null
  navigate: (path: string) => void
  onLogout: () => void
  currentPath: string
}

const navLinks = [
  { label: "Đang bán", href: "/products" },
  { label: "Sản phẩm của tôi", href: "/me/products" },
  { label: "Đề nghị", href: "/me/offers" },
  { label: "Đơn hàng", href: "/me/orders" },
  { label: "Đăng bán", href: "/sell" },
]

const Navbar = ({ user, navigate, onLogout, currentPath }: NavbarProps) => {
  const [menuOpen, setMenuOpen] = useState(false)
  const go = (path: string) => { setMenuOpen(false); navigate(path) }
  return (
    <nav className="sticky top-0 bg-white z-20 border-b border-black/10">
      <div className="flex items-center max-w-frame mx-auto px-4 xl:px-0 py-4 justify-between gap-4">
        {/* Wordmark */}
        <button
          onClick={() => go("/")}
          className="font-integralCF text-xl lg:text-[28px] text-black uppercase tracking-tight shrink-0"
        >
          H-SMART
        </button>

        {/* Desktop nav links */}
        <div className="hidden md:flex items-center gap-4 lg:gap-6 flex-1 justify-center">
          {navLinks.map((link) => (
            <button
              key={link.href}
              onClick={() => go(link.href)}
              className={cn(
                "text-sm font-medium transition-colors",
                currentPath === link.href
                  ? "text-black font-semibold"
                  : "text-black/60 hover:text-black"
              )}
            >
              {link.label}
            </button>
          ))}
        </div>

        {/* Right actions */}
        <div className="flex items-center gap-1 shrink-0">
          {user?.role === 'admin' && (
            <button
              onClick={() => go("/admin")}
              className="text-sm font-medium text-black/60 hover:text-black px-3"
            >
              Admin
            </button>
          )}
          {user ? (
            <>
              <span className="hidden sm:inline text-sm text-black/60 mr-2">
                {user.full_name || user.email}
              </span>
              <button
                onClick={onLogout}
                className="p-2 text-black/60 hover:text-black transition-colors"
                title="Đăng xuất"
              >
                <LogOut size={20} />
              </button>
            </>
          ) : (
            <button
              onClick={() => go("/")}
              className="p-2 text-black/60 hover:text-black transition-colors"
              title="Đăng nhập"
            >
              <User size={20} />
            </button>
          )}
          <button type="button" aria-label={menuOpen ? "Đóng menu" : "Mở menu"} aria-expanded={menuOpen} onClick={() => setMenuOpen((open) => !open)} className="ml-1 rounded-full p-2 text-black md:hidden">{menuOpen ? <X size={20} /> : <Menu size={20} />}</button>
        </div>
      </div>
      {menuOpen && <div className="border-t border-black/10 bg-white px-4 py-3 md:hidden"><div className="mx-auto flex max-w-frame flex-col">{navLinks.map((link) => <button key={link.href} onClick={() => go(link.href)} className={`rounded-xl px-3 py-3 text-left text-sm ${currentPath === link.href ? "bg-[#F0F0F0] font-semibold text-black" : "text-black/65"}`}>{link.label}</button>)}{user?.role === 'admin' && <button onClick={() => go('/admin')} className="rounded-xl px-3 py-3 text-left text-sm">Quản trị</button>}</div></div>}
    </nav>
  )
}

export default Navbar
