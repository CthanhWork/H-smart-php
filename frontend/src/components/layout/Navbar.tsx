import { useCallback, useEffect, useState, type FormEvent, type MouseEvent } from 'react'
import {
  LogOut,
  Bell,
  Menu,
  Search,
  Store,
  UserRound,
  X,
} from 'lucide-react'
import { cn } from '../../lib/utils'
import { getUnreadNotificationCount } from '../../notificationApi'
import { productRequest } from '../../productApi'

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
  { label: 'Danh mục sản phẩm', href: '/#categories' },
]

const memberLinks = [
  { label: 'Mua sắm', href: '/products' },
  { label: 'Tin nhắn', href: '/me/messages' },
  { label: 'Đăng bán', href: '/sell' },
]
const accountLinks = [
  { label: 'Sản phẩm của tôi', href: '/me/products' },
  { label: 'Đề nghị mua bán', href: '/me/offers' },
  { label: 'Đơn mua', href: '/me/orders' },
  { label: 'Sản phẩm yêu thích', href: '/me/favorites' },
  { label: 'Hồ sơ và địa chỉ', href: '/me/profile' },
  { label: 'Trợ lý H-Smart', href: '/assistant' },
]

const Navbar = ({ user, navigate, onLogout, onAuth, currentPath, accessToken }: NavbarProps) => {
  const [menuOpen, setMenuOpen] = useState(false)
  const [accountOpen, setAccountOpen] = useState(false)
  const [unreadCount, setUnreadCount] = useState(0)
  const [unreadMessageCount, setUnreadMessageCount] = useState(0)
  const searchRoute = `${window.location.pathname}${window.location.search}`
  const routeQuery = new URLSearchParams(window.location.search).get('q') ?? ''
  const [searchInput, setSearchInput] = useState(() => ({ route: searchRoute, value: routeQuery }))
  const query = searchInput.route === searchRoute ? searchInput.value : routeQuery
  const isProductListing = ['/products', '/me/products', '/me/favorites'].includes(currentPath)
  const links = user
    ? memberLinks.map((link) => user.role === 'admin' && link.href === '/me/messages' ? { label: 'Tin nhắn hỗ trợ', href: '/admin/messages' } : link)
    : guestLinks
  const messagePath = user?.role === 'admin' ? '/admin/messages' : '/me/messages'

  const refreshUnreadCount = useCallback(async () => {
    if (!accessToken) { setUnreadCount(0); return }
    try { setUnreadCount(await getUnreadNotificationCount(accessToken)) }
    catch { /* A page-level notification request will show the actionable error. */ }
  }, [accessToken])

  const refreshUnreadMessageCount = useCallback(async () => {
    if (!accessToken) { setUnreadMessageCount(0); return }
    try {
      const { data } = await productRequest<Array<{ unread_count?: number }>>('messages', accessToken)
      setUnreadMessageCount(data.reduce((total, thread) => total + Math.max(0, thread.unread_count ?? 0), 0))
    } catch { /* The inbox shows the actionable error if messages cannot load. */ }
  }, [accessToken])

  useEffect(() => {
    void refreshUnreadCount()
    void refreshUnreadMessageCount()
    const refreshWhenVisible = () => {
      if (document.visibilityState === 'visible') {
        void refreshUnreadCount()
        void refreshUnreadMessageCount()
      }
    }
    window.addEventListener('focus', refreshWhenVisible)
    document.addEventListener('visibilitychange', refreshWhenVisible)
    window.addEventListener('hsmart:notifications-updated', refreshWhenVisible)
    window.addEventListener('hsmart:messages-updated', refreshWhenVisible)
    const refreshTimer = window.setInterval(refreshWhenVisible, 30_000)
    return () => {
      window.clearInterval(refreshTimer)
      window.removeEventListener('focus', refreshWhenVisible)
      document.removeEventListener('visibilitychange', refreshWhenVisible)
      window.removeEventListener('hsmart:notifications-updated', refreshWhenVisible)
      window.removeEventListener('hsmart:messages-updated', refreshWhenVisible)
    }
  }, [refreshUnreadCount, refreshUnreadMessageCount, currentPath])

  const go = (path: string) => {
    setMenuOpen(false)
    setAccountOpen(false)
    if (path === '/#categories' || path === '#categories') {
      const target = '#categories'
      if (window.location.pathname === '/' && document.querySelector(target)) {
        document.querySelector(target)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
      } else {
        navigate('/#categories')
        window.setTimeout(() => document.querySelector(target)?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 80)
      }
      return
    }
    navigate(path)
  }

  function followLink(event: MouseEvent<HTMLAnchorElement>, path: string) {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return
    event.preventDefault()
    go(path)
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
            {user && <span className="max-w-[150px] truncate">Xin chào, {user.full_name || user.email}</span>}
            <span className="hidden text-white/50 sm:inline">|</span>
            <a href="/" onClick={(event) => followLink(event, '/')} className="hidden hover:text-white/80 sm:inline">Trang chủ</a>
          </div>
        </div>
      </div>

      <div className={cn('mx-auto grid max-w-frame grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 px-4 py-3 sm:gap-5 sm:py-4 xl:px-0', isProductListing && 'grid-cols-[auto_auto] justify-between')}>
        <a href="/" onClick={(event) => followLink(event, '/')} className="flex shrink-0 items-center gap-2 text-[#242424]" aria-label="H-Smart, về trang chủ">
          <span className="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#e65332] text-white"><Store size={22} strokeWidth={2.1} /></span>
          <span className="font-integralCF text-lg tracking-tight sm:text-2xl">H-SMART</span>
        </a>

        {!isProductListing && <form onSubmit={search} role="search" className="flex h-11 min-w-0 items-center rounded-xl border border-black/10 bg-[#f6f7f8] px-3 transition focus-within:border-[#e65332]/60 focus-within:bg-white focus-within:ring-4 focus-within:ring-[#e65332]/10 sm:h-12 sm:px-4">
          <Search className="mr-2 shrink-0 text-black/40" size={19} />
          <input value={query} onChange={(event) => setSearchInput({ route: searchRoute, value: event.target.value })} maxLength={200} placeholder="Tìm theo tên, mô tả, danh mục…" aria-label="Tìm sản phẩm" className="w-full min-w-0 bg-transparent text-sm outline-none placeholder:text-black/40" />
          <button type="submit" aria-label="Tìm kiếm" className="ml-2 flex h-8 w-9 shrink-0 items-center justify-center rounded-lg bg-[#e65332] text-white transition hover:bg-[#cc4528] sm:w-11"><Search size={17} /></button>
        </form>}

        <div className="flex shrink-0 items-center gap-1 sm:gap-2">
          {user ? <>
            <a href="/me/notifications" onClick={(event) => followLink(event, '/me/notifications')} aria-label={`Thông báo${unreadCount ? `, ${unreadCount} chưa đọc` : ''}`} aria-current={currentPath === '/me/notifications' ? 'page' : undefined} title="Thông báo" className={cn('relative flex h-10 w-10 items-center justify-center rounded-xl text-[#343434] transition hover:bg-[#fff0eb] hover:text-[#d94b2b]', currentPath === '/me/notifications' && 'bg-[#fff0eb] text-[#d94b2b]')}><Bell size={21} />{unreadCount > 0 && <><span className="notification-ping absolute -right-0.5 -top-0.5 h-3 w-3 rounded-full bg-[#e65332]" aria-hidden="true" /><span className="absolute -right-1 -top-1 min-w-[18px] rounded-full bg-[#d93025] px-1 text-center text-[10px] font-bold leading-[18px] text-white" aria-hidden="true">{unreadCount > 99 ? '99+' : unreadCount}</span></>}</a>
            <div className="relative hidden sm:block">
              <button type="button" onClick={() => setAccountOpen((open) => !open)} aria-expanded={accountOpen} className={cn('flex h-10 items-center gap-2 rounded-xl px-3 text-sm font-semibold text-[#343434] transition hover:bg-[#fff0eb]', accountOpen && 'bg-[#fff0eb]')}><UserRound size={18} /><span className="max-w-28 truncate">Tài khoản</span></button>
              {accountOpen && <div id="desktop-account-menu" className="absolute right-0 top-full z-40 mt-2 w-56 rounded-2xl border border-black/10 bg-white p-2 shadow-xl">
                {accountLinks.map((link) => <a key={link.href} href={link.href} onClick={(event) => followLink(event, link.href)} className="flex w-full items-center rounded-xl px-3 py-2.5 text-left text-sm text-black/70 hover:bg-[#fff0eb] hover:text-[#d94b2b]">{link.label}</a>)}
                {user.role === 'admin' && <a href="/admin" onClick={(event) => followLink(event, '/admin')} className="flex w-full items-center rounded-xl px-3 py-2.5 text-left text-sm text-black/70 hover:bg-[#fff0eb] hover:text-[#d94b2b]">Quản trị</a>}
                <button type="button" onClick={() => { setAccountOpen(false); onLogout() }} className="mt-1 flex w-full items-center gap-2 rounded-xl border-t border-black/10 px-3 py-3 text-left text-sm text-black/60 hover:bg-black/[0.03]"><LogOut size={16} />Đăng xuất</button>
              </div>}
            </div>
          </> : <>
            <button type="button" onClick={() => onAuth('login')} className="hidden rounded-xl px-3 py-2 text-sm font-semibold text-[#343434] hover:bg-black/5 sm:flex">Đăng nhập</button>
            <button type="button" onClick={() => onAuth('register')} className="hidden rounded-xl bg-[#e65332] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#cc4528] sm:flex">Tạo tài khoản</button>
          </>}
          <button type="button" aria-label={menuOpen ? 'Đóng menu' : 'Mở menu'} aria-expanded={menuOpen} onClick={() => setMenuOpen((open) => !open)} className="flex h-10 w-10 items-center justify-center rounded-xl text-black hover:bg-black/5 lg:hidden">{menuOpen ? <X size={21} /> : <Menu size={21} />}</button>
        </div>
      </div>

      <div className="hidden border-t border-black/[0.05] lg:block">
        <div className="mx-auto flex h-11 max-w-frame items-center gap-6 overflow-x-auto px-4 xl:px-0">
          {links.map((link) => <a key={link.href} href={link.href} onClick={(event) => followLink(event, link.href)} aria-current={currentPath === link.href ? 'page' : undefined} className={cn('inline-flex h-full shrink-0 items-center gap-1.5 border-b-2 border-transparent text-[13px] font-medium text-black/60 transition hover:border-[#e65332] hover:text-[#d94b2b]', currentPath === link.href && 'border-[#e65332] font-semibold text-[#d94b2b]', user && link.href === '/sell' && 'ml-auto inline-flex h-8 items-center gap-2 rounded-lg border-0 bg-[#fff0eb] px-3 text-[#d94b2b] hover:bg-[#ffe3d8]')}>
            {user && link.href === '/sell' && <Store size={15} />}{link.label}{link.href === messagePath && unreadMessageCount > 0 && <span className="relative rounded-full bg-[#d93025] px-1.5 text-[10px] font-bold leading-[18px] text-white">{unreadMessageCount > 99 ? '99+' : unreadMessageCount}<span className="notification-ping absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-[#d93025]" aria-hidden="true" /></span>}
          </a>)}
          {user?.role === 'admin' && <a href="/admin" onClick={(event) => followLink(event, '/admin')} aria-current={currentPath === '/admin' ? 'page' : undefined} className={cn('inline-flex h-full shrink-0 items-center border-b-2 border-transparent text-[13px] font-medium text-black/60 hover:border-[#e65332] hover:text-[#d94b2b]', currentPath === '/admin' && 'border-[#e65332] font-semibold text-[#d94b2b]')}>Quản trị</a>}
        </div>
      </div>

      {menuOpen && <div className="border-t border-black/10 bg-white px-4 py-3 lg:hidden"><div className="mx-auto flex max-w-frame flex-col">
        {links.map((link) => <a key={link.href} href={link.href} onClick={(event) => followLink(event, link.href)} aria-current={currentPath === link.href ? 'page' : undefined} className={cn('flex items-center justify-between rounded-xl px-3 py-3 text-left text-sm', currentPath === link.href ? 'bg-[#fff0eb] font-semibold text-[#d94b2b]' : 'text-black/70')}><span>{link.label}</span>{link.href === messagePath && unreadMessageCount > 0 && <span className="relative rounded-full bg-[#d93025] px-2 py-0.5 text-[10px] font-bold text-white">{unreadMessageCount > 99 ? '99+' : unreadMessageCount} chưa đọc<span className="notification-ping absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-[#d93025]" aria-hidden="true" /></span>}</a>)}
        {user ? <>
          <button type="button" onClick={() => setAccountOpen((open) => !open)} aria-expanded={accountOpen} className="mt-2 flex w-full items-center justify-between rounded-xl border-t border-black/10 px-3 py-3 text-left text-sm font-semibold text-black/75"><span className="flex items-center gap-2"><UserRound size={17} />Tài khoản</span><span aria-hidden="true">{accountOpen ? '−' : '+'}</span></button>
          {accountOpen && <div className="ml-3 border-l border-black/10 pl-2">{accountLinks.map((link) => <a key={link.href} href={link.href} onClick={(event) => followLink(event, link.href)} className="block w-full rounded-xl px-3 py-2.5 text-left text-sm text-black/65 hover:bg-[#fff0eb]">{link.label}</a>)}{user.role === 'admin' && <a href="/admin" onClick={(event) => followLink(event, '/admin')} className="block w-full rounded-xl px-3 py-2.5 text-left text-sm text-black/65 hover:bg-[#fff0eb]">Quản trị</a>}<button type="button" onClick={() => { setMenuOpen(false); onLogout() }} className="flex items-center gap-2 rounded-xl px-3 py-2.5 text-left text-sm text-black/65"><LogOut size={16} />Đăng xuất</button></div>}
        </> : <div className="mt-2 grid grid-cols-2 gap-2 border-t border-black/10 pt-3"><button type="button" onClick={() => { setMenuOpen(false); onAuth('login') }} className="rounded-xl border border-black/10 px-3 py-2.5 text-sm font-semibold">Đăng nhập</button><button type="button" onClick={() => { setMenuOpen(false); onAuth('register') }} className="rounded-xl bg-[#e65332] px-3 py-2.5 text-sm font-semibold text-white">Tạo tài khoản</button></div>}
      </div></div>}
      <div className="fixed inset-x-0 bottom-0 z-40 grid grid-cols-4 border-t border-black/10 bg-white/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_16px_rgba(0,0,0,.08)] backdrop-blur lg:hidden" aria-label="Điều hướng nhanh">
        <a href="/products" onClick={(event) => followLink(event, '/products')} className="flex min-h-14 flex-col items-center justify-center gap-1 text-[10px] font-medium text-black/65"><Search size={18}/>Khám phá</a>
        <a href={user ? '/sell' : '/register'} onClick={(event) => followLink(event, user ? '/sell' : '/register')} className="flex min-h-14 flex-col items-center justify-center gap-1 text-[10px] font-semibold text-[#d94322]"><Store size={18}/>Đăng bán</a>
        <a href={user ? messagePath : '/login'} onClick={(event) => followLink(event, user ? messagePath : '/login')} className="flex min-h-14 flex-col items-center justify-center gap-1 text-[10px] font-medium text-black/65"><Menu size={18}/>Tin nhắn</a>
        <a href={user ? '/me/profile' : '/login'} onClick={(event) => followLink(event, user ? '/me/profile' : '/login')} className="flex min-h-14 flex-col items-center justify-center gap-1 text-[10px] font-medium text-black/65"><UserRound size={18}/>Tài khoản</a>
      </div>
    </nav>
  )
}

export default Navbar
