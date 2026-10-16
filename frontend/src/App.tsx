import { useEffect, useLayoutEffect, useRef, useState, type FormEvent } from 'react'
import { Button } from './components/ui/button'
import { Alert } from './components/ui/alert'
import Navbar from './components/layout/Navbar'
import Footer from './components/layout/Footer'
import SellPage from './SellPage'
import { ProductListPage, ProductDetailPage } from './ProductPages'
import OfferPages from './OfferPages'
import OrderPages from './OrderPages'
import AdminPage from './AdminPage'
import MessagePages from './MessagePages'
import HomePage from './HomePage'
import ProfilePage from './ProfilePage'

const demoMailboxUrl = import.meta.env.VITE_DEMO_MAILBOX_URL as string | undefined

type User = { id: number; email: string; full_name: string | null; status: string; role: 'member' | 'admin' }
type Tokens = { accessToken: string; refreshToken: string; tokenType: 'Bearer'; user: User }
type ApiResult<T> = { status: 'success' | 'error'; message: string; data: T; errors?: Record<string, string[]> }

async function api<T>(path: string, options: RequestInit = {}): Promise<ApiResult<T>> {
  let response: Response
  try {
    response = await fetch(`/api/v1/auth/${path}`, {
      ...options,
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...options.headers },
    })
  } catch {
    throw new Error('Không kết nối được với máy chủ. Thử lại sau nhé.')
  }
  const result = await response.json() as ApiResult<T>
  if (!response.ok) {
    const detail = result.errors && Object.values(result.errors)[0]?.[0]
    throw new Error(detail || result.message || 'Yêu cầu không thành công.')
  }
  return result
}

function App() {
  const [path, setPath] = useState(window.location.pathname)
  const [navigationVersion, setNavigationVersion] = useState(0)
  const isVerifying = path === '/verify-email'
  const isResetting = path === '/reset-password'
  const isSelling = path === '/sell'
  const isAdmin = path === '/admin'
  const editMatch = path.match(/^\/products\/(\d+)\/edit$/)
  const isProducts = path === '/products' || path === '/me/products' || path === '/me/favorites' || /^\/products\/\d+$/.test(path)
  const isOffers = path === '/me/offers'
  const isOrders = path === '/me/orders'
  const isMessages = path === '/me/messages'
  const isProfile = path === '/me/profile'
  const isOrderCreation = path === '/orders/create'
  const verificationToken = isVerifying ? new URLSearchParams(window.location.search).get('token') : null
  const [resetToken, setResetToken] = useState(() => isResetting ? new URLSearchParams(window.location.search).get('token') : null)
  const verificationAttempted = useRef(false)
  const [mode, setMode] = useState<'login' | 'register' | 'forgot'>('login')
  const [email, setEmail] = useState('')
  const [fullName, setFullName] = useState('')
  const [currentPassword, setCurrentPassword] = useState('')
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [tokens, setTokens] = useState<Tokens | null>(null)
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(Boolean(verificationToken))

  useEffect(() => {
    if (path === '/login') setMode('login')
    if (path === '/register') setMode('register')
    if (path === '/forgot-password') setMode('forgot')
  }, [path])

  useEffect(() => {
    const onPopState = () => { setPath(window.location.pathname); setNavigationVersion((version) => version + 1) }
    window.addEventListener('popstate', onPopState)
    return () => window.removeEventListener('popstate', onPopState)
  }, [])

  useLayoutEffect(() => {
    window.scrollTo(0, 0)
  }, [navigationVersion])

  function navigate(nextPath: string) {
    window.history.pushState({}, '', nextPath)
    setPath(window.location.pathname)
    setNavigationVersion((version) => version + 1)
    setError('')
    setNotice('')
  }

  useEffect(() => {
    if (!verificationToken || verificationAttempted.current) return
    verificationAttempted.current = true
    api<null>(`verify-email?token=${encodeURIComponent(verificationToken)}`)
      .then((result) => setNotice(result.message))
      .catch((cause: Error) => setError(cause.message))
      .finally(() => { setLoading(false); window.history.replaceState({}, '', '/verify-email') })
  }, [verificationToken])

  useEffect(() => {
    if (isResetting) window.history.replaceState({}, '', '/reset-password')
  }, [isResetting])

  function switchMode(next: 'login' | 'register' | 'forgot') {
    setMode(next); setError(''); setNotice(''); setPassword(''); setConfirmation('')
    navigate(next === 'forgot' ? '/forgot-password' : `/${next}`)
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setError(''); setNotice('')
    if (mode === 'register' && password !== confirmation) { setError('Mật khẩu nhập lại không khớp.'); return }
    setLoading(true)
    try {
      if (mode === 'forgot') {
        const result = await api<null>('forgot-password', { method: 'POST', body: JSON.stringify({ email }) })
        setNotice(result.message)
      } else if (mode === 'register') {
        const result = await api<null>('register', { method: 'POST', body: JSON.stringify({ email, full_name: fullName, password, password_confirmation: confirmation }) })
        setNotice(result.message); setPassword(''); setConfirmation('')
      } else {
        const result = await api<Tokens>('login', { method: 'POST', body: JSON.stringify({ email, password }) })
        setTokens(result.data); setPassword(''); navigate('/')
      }
    } catch (cause) { setError((cause as Error).message) }
    finally { setLoading(false) }
  }

  async function submitReset(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setError(''); setNotice('')
    if (password !== confirmation) { setError('Mật khẩu nhập lại không khớp.'); return }
    if (!resetToken) { setError('Liên kết đặt lại mật khẩu không hợp lệ. Mở lại email để thử lần nữa.'); return }
    setLoading(true)
    try {
      const result = await api<null>('reset-password', {
        method: 'POST', body: JSON.stringify({ token: resetToken, password, password_confirmation: confirmation }),
      })
      setResetToken(null)
      setPassword(''); setConfirmation(''); setNotice(result.message)
    } catch (cause) { setError((cause as Error).message) }
    finally { setLoading(false) }
  }

  async function submitChange(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setError(''); setNotice('')
    if (!tokens) return
    if (password !== confirmation) { setError('Mật khẩu nhập lại không khớp.'); return }
    setLoading(true)
    try {
      const result = await api<null>('change-password', {
        method: 'POST', headers: { Authorization: `Bearer ${tokens.accessToken}` },
        body: JSON.stringify({ currentPassword, password, password_confirmation: confirmation }),
      })
      setTokens(null); setCurrentPassword(''); setPassword(''); setConfirmation(''); setNotice(result.message)
    } catch (cause) { setError((cause as Error).message) }
    finally { setLoading(false) }
  }

  async function logoutAll() {
    if (!tokens) return
    setError(''); setLoading(true)
    try {
      const result = await api<null>('logout-all', { method: 'POST', headers: { Authorization: `Bearer ${tokens.accessToken}` } })
      setTokens(null); setNotice(result.message)
    } catch (cause) { setError((cause as Error).message) }
    finally { setLoading(false) }
  }

  async function resend() {
    setError(''); setNotice(''); setLoading(true)
    try {
      const result = await api<null>('resend-verification', { method: 'POST', body: JSON.stringify({ email }) })
      setNotice(result.message)
    } catch (cause) { setError((cause as Error).message) }
    finally { setLoading(false) }
  }

  async function logout() {
    if (!tokens) return
    setLoading(true)
    try {
      await api<null>('logout', { method: 'POST', headers: { Authorization: `Bearer ${tokens.accessToken}` }, body: JSON.stringify({ refreshToken: tokens.refreshToken }) })
    } catch { /* The local session must still be cleared. */ }
    finally { setTokens(null); setLoading(false) }
  }

  const isPrivateProductList = path === '/me/products' || path === '/me/favorites'
  const pageContent = isSelling || editMatch ? <SellPage key={path} accessToken={tokens?.accessToken ?? null} editId={editMatch ? Number(editMatch[1]) : undefined} onSaved={(id) => navigate(`/products/${id}`)} onBack={() => navigate(editMatch ? `/products/${editMatch[1]}` : '/')} /> : isAdmin && tokens ? <AdminPage accessToken={tokens.accessToken} onBack={() => navigate('/')} /> : isProfile && tokens ? <ProfilePage accessToken={tokens.accessToken} navigate={navigate} onSaved={(fullName) => setTokens((current) => current ? { ...current, user: { ...current.user, full_name: fullName } } : current)} /> : isProducts && (!isPrivateProductList || tokens) ? path === '/products' || isPrivateProductList ? <ProductListPage key={`${path}${window.location.search}`} accessToken={tokens?.accessToken ?? ''} navigate={navigate} mine={path === '/me/products'} favorites={path === '/me/favorites'} /> : <ProductDetailPage key={path} accessToken={tokens?.accessToken ?? ''} userId={tokens?.user.id ?? null} navigate={navigate} /> : isOffers && tokens ? <OfferPages key={path} accessToken={tokens.accessToken} userId={tokens.user.id} navigate={navigate} /> : (isOrders || isOrderCreation) && tokens ? <OrderPages key={`${path}${window.location.search}`} accessToken={tokens.accessToken} userId={tokens.user.id} navigate={navigate} mode={isOrderCreation ? 'create' : 'list'} /> : isMessages && tokens ? <MessagePages key={`${path}${window.location.search}${navigationVersion}`} accessToken={tokens.accessToken} userId={tokens.user.id} navigate={navigate} /> : null
  const authContent = <section className="font-vietnamese mx-auto grid max-w-frame items-center gap-10 px-4 py-12 md:grid-cols-[1fr_0.85fr] md:py-20 xl:px-0">
      <div><span className="text-xs font-medium uppercase tracking-[0.18em] text-black/50">Chợ đồ cũ dành cho sinh viên</span><h1 className="mt-4 font-bold tracking-tight text-4xl uppercase leading-tight sm:text-5xl lg:text-6xl">Mua bán đồ cũ.<br /><span className="text-black/45">Dễ dàng, an tâm.</span></h1><p className="mt-5 max-w-lg text-base leading-relaxed text-black/60">Tìm sách, thiết bị học tập và đồ dùng đã qua sử dụng từ sinh viên khác. Đăng nhập bằng email đã xác nhận để mua bán.</p><div className="mt-8 grid max-w-lg grid-cols-3 gap-3 border-t border-black/10 pt-5 text-xs text-black/55 sm:text-sm"><span><strong className="block text-lg text-black">01</strong>Tạo tài khoản</span><span><strong className="block text-lg text-black">02</strong>Xác thực email</span><span><strong className="block text-lg text-black">03</strong>Khám phá</span></div></div>
      <div className="rounded-[24px] border border-black/10 bg-white p-5 shadow-sm sm:p-8">
        {tokens ? <div className="space-y-4">
          <div className="mb-5"><span className="text-xs font-medium uppercase tracking-wider text-[#2f7049]">Đăng nhập thành công</span><h2 className="mt-2 text-2xl font-bold">Xin chào, {tokens.user.full_name || tokens.user.email}!</h2><p className="mt-2 text-sm text-black/55">Email <strong className="text-black">{tokens.user.email}</strong> đã được xác nhận.</p></div>
          {error && <Alert variant="error">{error}</Alert>}
          <div className="grid grid-cols-2 gap-2"><Button onClick={() => navigate('/sell')}>Đăng bán</Button><Button variant="outline" onClick={() => navigate('/products')}>Xem sản phẩm</Button><Button variant="outline" onClick={() => navigate('/me/products')}>Sản phẩm của tôi</Button><Button variant="outline" onClick={() => navigate('/me/offers')}>Đề nghị mua bán</Button><Button variant="outline" onClick={() => navigate('/me/orders')}>Đơn hàng</Button><Button variant="outline" onClick={() => navigate('/me/messages')}>Tin nhắn</Button><Button variant="outline" onClick={() => navigate('/me/profile')}>Hồ sơ và địa chỉ</Button>{tokens.user.role === 'admin' && <Button variant="outline" onClick={() => navigate('/admin')}>Quản trị viên</Button>}</div>
          <form className="space-y-3 border-t border-black/10 pt-5" onSubmit={submitChange}><h3 className="font-bold">Đổi mật khẩu</h3><label className="block text-sm">Mật khẩu hiện tại<input className="mt-1 w-full rounded-full border border-black/15 px-4 py-3 outline-none focus:border-black" type="password" autoComplete="current-password" required value={currentPassword} onChange={(event) => setCurrentPassword(event.target.value)} /></label><label className="block text-sm">Mật khẩu mới<input className="mt-1 w-full rounded-full border border-black/15 px-4 py-3 outline-none focus:border-black" type="password" autoComplete="new-password" minLength={8} maxLength={128} required value={password} onChange={(event) => setPassword(event.target.value)} /></label><label className="block text-sm">Nhập lại mật khẩu mới<input className="mt-1 w-full rounded-full border border-black/15 px-4 py-3 outline-none focus:border-black" type="password" autoComplete="new-password" required value={confirmation} onChange={(event) => setConfirmation(event.target.value)} /></label><Button className="w-full" type="submit" disabled={loading}>Đổi mật khẩu</Button></form>
          <div className="flex flex-wrap gap-2"><Button variant="outline" onClick={logout} disabled={loading}>Đăng xuất</Button><Button variant="ghost" onClick={logoutAll} disabled={loading}>Đăng xuất khỏi mọi thiết bị</Button></div>
        </div> : isVerifying ? <div className="space-y-4"><span className="text-xs font-medium uppercase tracking-wider text-black/50">Xác nhận email</span><h2 className="text-2xl font-bold">Xác nhận email</h2>{loading && <p>Đang xác nhận email…</p>}{notice && <Alert variant="success" role="status">{notice}</Alert>}{error && <Alert variant="error">{error}</Alert>}{!verificationToken && !notice && !error && <Alert variant="error">Liên kết xác nhận không hợp lệ. Mở lại email để thử lần nữa.</Alert>}<Button onClick={() => navigate('/')}>Về trang đăng nhập</Button></div> : isResetting ? <div><span className="text-xs uppercase tracking-wider text-black/50">Khôi phục tài khoản</span><h2 className="mt-2 text-2xl font-bold">Đặt lại mật khẩu</h2><p className="mt-2 text-sm text-black/55">Mật khẩu mới cần có ít nhất 8 ký tự.</p>{notice && <Alert variant="success" className="mt-4" role="status">{notice}</Alert>}{error && <Alert variant="error" className="mt-4">{error}</Alert>}{!resetToken && !notice && <Alert variant="error" className="mt-4">Liên kết đặt lại mật khẩu không hợp lệ. Mở lại email để thử lần nữa.</Alert>}{resetToken && <form className="mt-5 space-y-4" onSubmit={submitReset}><label className="block text-sm">Mật khẩu mới<input className="mt-1 w-full rounded-full border border-black/15 px-4 py-3 outline-none focus:border-black" type="password" autoComplete="new-password" minLength={8} maxLength={128} required value={password} onChange={(event) => setPassword(event.target.value)} /></label><label className="block text-sm">Nhập lại mật khẩu<input className="mt-1 w-full rounded-full border border-black/15 px-4 py-3 outline-none focus:border-black" type="password" autoComplete="new-password" required value={confirmation} onChange={(event) => setConfirmation(event.target.value)} /></label><Button className="w-full" type="submit" disabled={loading}>{loading ? 'Đang xử lý…' : 'Đặt lại mật khẩu'}</Button></form>}<Button className="mt-4 w-full" variant="outline" onClick={() => navigate('/')}>Về trang đăng nhập</Button></div> : <>
          <div className="mb-6 flex rounded-full bg-[#F0F0F0] p-1" role="tablist" aria-label="Tài khoản"><button type="button" role="tab" aria-selected={mode === 'login'} className={`flex-1 rounded-full py-2.5 text-sm font-medium ${mode === 'login' ? 'bg-black text-white' : 'text-black/55'}`} onClick={() => switchMode('login')}>Đăng nhập</button><button type="button" role="tab" aria-selected={mode === 'register'} className={`flex-1 rounded-full py-2.5 text-sm font-medium ${mode === 'register' ? 'bg-black text-white' : 'text-black/55'}`} onClick={() => switchMode('register')}>Đăng ký</button></div>
          <h2 className="text-2xl font-bold">{mode === 'login' ? 'Chào mừng trở lại' : mode === 'register' ? 'Tạo tài khoản mới' : 'Quên mật khẩu?'}</h2><p className="mt-2 text-sm text-black/55">{mode === 'login' ? 'Nhập email và mật khẩu để đăng nhập.' : mode === 'register' ? demoMailboxUrl ? 'Đăng ký xong, xác nhận email để hoàn tất tạo tài khoản.' : 'Sau khi đăng ký, mở email và nhấn vào liên kết để xác nhận tài khoản.' : 'Nhập email đã đăng ký để nhận liên kết đặt lại mật khẩu.'}</p>
          {notice && <Alert variant="success" className="mt-4" role="status">{notice}</Alert>}{demoMailboxUrl && mode === 'register' && <p className="mt-3 rounded-xl bg-[#F0F0F0] p-3 text-xs text-black/60">Email xác nhận sẽ được gửi vào <a className="underline" href={demoMailboxUrl} target="_blank" rel="noreferrer">hộp thư thử nghiệm</a>.</p>}{error && <Alert variant="error" className="mt-4">{error}</Alert>}
          <form className="mt-5 space-y-4" onSubmit={submit}>
            {mode === 'register' && <label className="block text-sm font-medium">Họ và tên <span className="font-normal text-black/45">(không bắt buộc)</span><input className="mt-1.5 w-full rounded-full border border-black/15 px-4 py-3 text-sm outline-none focus:border-black" type="text" autoComplete="name" maxLength={150} value={fullName} onChange={(event) => setFullName(event.target.value)} /></label>}
            <label className="block text-sm font-medium">Email<input className="mt-1.5 w-full rounded-full border border-black/15 px-4 py-3 text-sm outline-none focus:border-black" type="email" autoComplete="email" required maxLength={150} placeholder="ban@example.com" value={email} onChange={(event) => setEmail(event.target.value)} /></label>
            {mode !== 'forgot' && <label className="block text-sm font-medium">Mật khẩu<input className="mt-1.5 w-full rounded-full border border-black/15 px-4 py-3 text-sm outline-none focus:border-black" type="password" autoComplete={mode === 'login' ? 'current-password' : 'new-password'} required minLength={mode === 'register' ? 8 : undefined} maxLength={mode === 'register' ? 128 : undefined} value={password} onChange={(event) => setPassword(event.target.value)} /></label>}
            {mode === 'register' && <><p className="-mt-2 text-xs text-black/50">Mật khẩu cần có ít nhất 8 ký tự. Đừng dùng mật khẩu dễ đoán.</p><label className="block text-sm font-medium">Nhập lại mật khẩu<input className="mt-1.5 w-full rounded-full border border-black/15 px-4 py-3 text-sm outline-none focus:border-black" type="password" autoComplete="new-password" required value={confirmation} onChange={(event) => setConfirmation(event.target.value)} /></label></>}
            <Button className="w-full" disabled={loading} type="submit">{loading ? 'Đang xử lý…' : mode === 'login' ? 'Đăng nhập' : mode === 'register' ? 'Tạo tài khoản' : 'Gửi liên kết đặt lại'}</Button>
          </form>
          {mode === 'login' && <div className="mt-5 space-y-2 text-center text-sm text-black/55">Chưa nhận được email xác nhận? <button className="font-medium text-black underline" type="button" onClick={resend} disabled={loading || !email}>Gửi lại email</button><div><button className="underline" type="button" onClick={() => switchMode('forgot')}>Quên mật khẩu?</button></div></div>}{mode === 'forgot' && <div className="mt-5 text-center"><button className="text-sm underline" type="button" onClick={() => switchMode('login')}>Quay lại đăng nhập</button></div>}
        </>}
      </div>
    </section>
  const content = pageContent ?? (path === '/' ? <HomePage isAuthenticated={Boolean(tokens)} accessToken={tokens?.accessToken ?? ''} navigate={navigate} /> : authContent)

  return <div className="font-vietnamese min-h-screen bg-white text-black"><Navbar user={tokens?.user ?? null} navigate={navigate} onLogout={logout} onAuth={switchMode} currentPath={path} /><div>{content}</div><Footer onSignUp={() => switchMode('register')} navigate={navigate} /></div>
}

export default App
