import { useCallback, useEffect, useState, type FormEvent } from 'react'
import {
  LogOut,
  Bell,
  Menu,
  MessageCircle,
  Search,
  ShoppingBag,
  Heart,
  Store,
  UserRound,
  UserRoundPlus,
  X,
} from 'lucide-react'
import { cn } from '../../lib/utils'
import { getUnreadNotificationCount } from '../../notificationApi'

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
  onAuth: (mode: 'login' | 'register') => void
  currentPath: string
  accessToken: string | null
}

const guestLinks = [
  { label: 'Khám phá sản phẩm', href: '/products' },
  { label: 'Cách hoạt động', href: '#how-it-works' },
]

const memberLinks = [
  { label: 'Mua sắm', href: '/products' },
  { label: 'Sản phẩm của tôi', href: '/me/products' },
  { label: 'Đề nghị', href: '/me/offers' },
  { label: 'Đơn mua', href: '/me/orders' },
  { label: 'Yêu thích', href: '/me/favorites' },
  { label: 'Hồ sơ và địa chỉ', href: '/me/profile' },
  { label: 'Tin nhắn', href: '/me/messages' },
  { label: 'Đăng bán', href: '/sell' },
]

const Navbar = ({ user, navigate, onLogout, onAuth, currentPath, accessToken }: NavbarProps) => {
  const [menuOpen, setMenuOpen] = useState(false)
  const [unreadCount, setUnreadCount] = useState(0)
  const [query, setQuery] = useState(() => new URLSearchParams(window.location.search).get('q') ?? '')
  const links = user ? memberLinks : guestLinks

  const refreshUnreadCount = useCallback(async () => {
    if (!accessToken) { setUnreadCount(0); return }
    try { setUnreadCount(await getUnreadNotificationCount(accessToken)) }
    catch { /* A page-level notification request will show the actionable error. */ }
  }, [accessToken])

  useEffect(() => {
    void refreshUnreadCount()
    const refreshWhenVisible = () => { if (document.visibilityState === 'visible') void refreshUnreadCount() }
    window.addEventListener('focus', refreshWhenVisible)
    document.addEventListener('visibilitychange', refreshWhenVisible)
    window.addEventListener('hsmart:notifications-updated', refreshWhenVisible)
    return () => {
      window.removeEventListener('focus', refreshWhenVisible)
      document.removeEventListener('visibilitychange', refreshWhenVisible)
      window.removeEventListener('hsmart:notifications-updated', refreshWhenVisible)
    }
  }, [refreshUnreadCount, currentPath])

  const go = (path: string) => {
    setMenuOpen(false)
    if (path.startsWith('#')) {
      document.querySelector(path)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
      return
    }
    navigate(path)
  }

  function search(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const value = query.trim()
    go(value ? `/products?q=${encodeURIComponent(value)}` : '/products')
  }

  return (
    <nav className="sticky top-0 z-30 border-b border-black/10 bg-white shadow-[0_3px_16px_rgba(25,25,25,0.05)]">
      <div className="bg-[#e65332] text-white">
        <div className="mx-auto flex h-8 max-w-frame items-center justify-between px-4 text-[11px] sm:text-xs xl:px-0">
          <p className="hidden sm:block">Chợ đồ cũ dành riêng cho cộng đồng sinh viên</p>
          <p className="sm:hidden">Mua bán đồ cũ sinh viên</p>
          <div className="flex items-center gap-2 sm:gap-3">
            {user ? <span className="max-w-[150px] truncate">Xin chào, {user.full_name || user.email}</span> : <>
              <button type="button" onClick={() => onAuth('register')} className="font-semibold hover:text-white/80">Đăng ký</button>
              <span className="text-white/50">|</span>
              <button type="button" onClick={() => onAuth('login')} className="font-semibold hover:text-white/80">Đăng nhập</button>
            </>}
            <span className="hidden text-white/50 sm:inline">|</span>
            <button type="button" onClick={() => go('/products')} className="hidden hover:text-white/80 sm:inline">Trợ giúp</button>
          </div>
        </div>
      </div>

      <div className="mx-auto grid max-w-frame grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 px-4 py-3 sm:gap-5 sm:py-4 xl:px-0">
        <button type="button" onClick={() => go('/')} className="flex shrink-0 items-center gap-2 text-[#242424]" aria-label="H-Smart, về trang chủ">
          <span className="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#e65332] text-white"><Store size={22} strokeWidth={2.1} /></span>
          <span className="font-integralCF text-lg tracking-tight sm:text-2xl">H-SMART</span>
        </button>

        <form onSubmit={search} role="search" className="flex h-11 min-w-0 items-center rounded-xl border border-black/10 bg-[#f6f7f8] px-3 transition focus-within:border-[#e65332]/60 focus-within:bg-white focus-within:ring-4 focus-within:ring-[#e65332]/10 sm:h-12 sm:px-4">
          <Search className="mr-2 shrink-0 text-black/40" size={19} />
          <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Tìm sách, đồ điện tử, đồ dùng…" aria-label="Tìm sản phẩm" className="w-full min-w-0 bg-transparent text-sm outline-none placeholder:text-black/40" />
          <button type="submit" aria-label="Tìm kiếm" className="ml-2 flex h-8 w-9 shrink-0 items-center justify-center rounded-lg bg-[#e65332] text-white transition hover:bg-[#cc4528] sm:w-11"><Search size={17} /></button>
        </form>

        <div className="flex shrink-0 items-center gap-1 sm:gap-2">
          {user ? <>
            <button type="button" onClick={() => go('/me/messages')} aria-label="Tin nhắn" title="Tin nhắn" className="relative flex h-10 w-10 items-center justify-center rounded-xl text-[#343434] transition hover:bg-[#fff0eb] hover:text-[#d94b2b]"><MessageCircle size={21} /></button>
            <button type="button" onClick={() => go('/me/notifications')} aria-label={`Thông báo${unreadCount ? `, ${unreadCount} chưa đọc` : ''}`} title="Thông báo" className={cn('relative flex h-10 w-10 items-center justify-center rounded-xl text-[#343434] transition hover:bg-[#fff0eb] hover:text-[#d94b2b]', currentPath === '/me/notifications' && 'bg-[#fff0eb] text-[#d94b2b]')}><Bell size={21} />{unreadCount > 0 && <span className="absolute -right-0.5 -top-0.5 min-w-4 rounded-full bg-[#e65332] px-1 text-center text-[10px] font-bold leading-4 text-white">{unreadCount > 99 ? '99+' : unreadCount}</span>}</button>
            <button type="button" onClick={() => go('/me/orders')} aria-label="Đơn mua" title="Đơn mua" className="flex h-10 w-10 items-center justify-center rounded-xl text-[#343434] transition hover:bg-[#fff0eb] hover:text-[#d94b2b]"><ShoppingBag size={21} /></button>
            <button type="button" onClick={() => go('/me/favorites')} aria-label="Yêu thích" title="Yêu thích" className="flex h-10 w-10 items-center justify-center rounded-xl text-[#343434] transition hover:bg-[#fff0eb] hover:text-[#d94b2b]"><Heart size={20} /></button>
            <button type="button" onClick={onLogout} aria-label="Đăng xuất" title="Đăng xuất" className="hidden h-10 w-10 items-center justify-center rounded-xl text-black/55 transition hover:bg-black/5 hover:text-black sm:flex"><LogOut size={19} /></button>
          </> : <>
            <button type="button" onClick={() => onAuth('login')} className="hidden items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-[#343434] hover:bg-black/5 sm:flex"><UserRound size={18} /> <span>Đăng nhập</span></button>
            <button type="button" onClick={() => onAuth('register')} className="hidden items-center gap-2 rounded-xl bg-[#e65332] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#cc4528] sm:flex"><UserRoundPlus size={18} /> <span>Tạo tài khoản</span></button>
          </>}
          <button type="button" aria-label={menuOpen ? 'Đóng menu' : 'Mở menu'} aria-expanded={menuOpen} onClick={() => setMenuOpen((open) => !open)} className="flex h-10 w-10 items-center justify-center rounded-xl text-black hover:bg-black/5 md:hidden">{menuOpen ? <X size={21} /> : <Menu size={21} />}</button>
        </div>
      </div>

      <div className="hidden border-t border-black/[0.05] md:block">
        <div className="mx-auto flex h-11 max-w-frame items-center gap-6 overflow-x-auto px-4 xl:px-0">
          {links.map((link) => <button key={link.href} type="button" onClick={() => go(link.href)} className={cn('h-full shrink-0 border-b-2 border-transparent text-[13px] font-medium text-black/60 transition hover:border-[#e65332] hover:text-[#d94b2b]', currentPath === link.href && 'border-[#e65332] font-semibold text-[#d94b2b]', user && link.href === '/sell' && 'ml-auto inline-flex h-8 items-center gap-2 rounded-lg border-0 bg-[#fff0eb] px-3 text-[#d94b2b] hover:bg-[#ffe3d8]')}>
            {user && link.href === '/sell' && <Store size={15} />}{link.label}
          </button>)}
          {user?.role === 'admin' && <button type="button" onClick={() => go('/admin')} className={cn('h-full shrink-0 border-b-2 border-transparent text-[13px] font-medium text-black/60 hover:border-[#e65332] hover:text-[#d94b2b]', currentPath === '/admin' && 'border-[#e65332] font-semibold text-[#d94b2b]')}>Quản trị</button>}
        </div>
      </div>

      {menuOpen && <div className="border-t border-black/10 bg-white px-4 py-3 md:hidden"><div className="mx-auto flex max-w-frame flex-col">
        {links.map((link) => <button key={link.href} type="button" onClick={() => go(link.href)} className={cn('rounded-xl px-3 py-3 text-left text-sm', currentPath === link.href ? 'bg-[#fff0eb] font-semibold text-[#d94b2b]' : 'text-black/70')}>{link.label}</button>)}
        {user?.role === 'admin' && <button type="button" onClick={() => go('/admin')} className="rounded-xl px-3 py-3 text-left text-sm text-black/70">Quản trị</button>}
        {user ? <button type="button" onClick={() => { setMenuOpen(false); onLogout() }} className="flex items-center gap-2 rounded-xl px-3 py-3 text-left text-sm text-black/70"><LogOut size={17} /> Đăng xuất</button> : <div className="mt-2 grid grid-cols-2 gap-2 border-t border-black/10 pt-3"><button type="button" onClick={() => { setMenuOpen(false); onAuth('login') }} className="rounded-xl border border-black/10 px-3 py-2.5 text-sm font-semibold">Đăng nhập</button><button type="button" onClick={() => { setMenuOpen(false); onAuth('register') }} className="rounded-xl bg-[#e65332] px-3 py-2.5 text-sm font-semibold text-white">Tạo tài khoản</button></div>}
      </div></div>}
    </nav>
  )
}

export default Navbar
