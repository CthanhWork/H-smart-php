import { useEffect, useRef, useState, type FormEvent } from 'react'
import './App.css'
import SellPage from './SellPage'
import ProductPages from './ProductPages'

const demoMailboxUrl = import.meta.env.VITE_DEMO_MAILBOX_URL as string | undefined

type User = { id: number; email: string; full_name: string | null; status: string }
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
    throw new Error('Không thể kết nối máy chủ. Hãy thử lại sau.')
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
  const isVerifying = path === '/verify-email'
  const isResetting = path === '/reset-password'
  const isSelling = path === '/sell'
  const editMatch = path.match(/^\/products\/(\d+)\/edit$/)
  const isProducts = path === '/products' || path === '/me/products' || /^\/products\/\d+$/.test(path)
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
    const onPopState = () => setPath(window.location.pathname)
    window.addEventListener('popstate', onPopState)
    return () => window.removeEventListener('popstate', onPopState)
  }, [])

  function navigate(nextPath: string) {
    window.history.pushState({}, '', nextPath)
    setPath(nextPath)
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
        const result = await api<null>('register', { method: 'POST', body: JSON.stringify({ email, fullName, password, password_confirmation: confirmation }) })
        setNotice(result.message); setPassword(''); setConfirmation('')
      } else {
        const result = await api<Tokens>('login', { method: 'POST', body: JSON.stringify({ email, password }) })
        setTokens(result.data); setPassword('')
      }
    } catch (cause) { setError((cause as Error).message) }
    finally { setLoading(false) }
  }

  async function submitReset(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setError(''); setNotice('')
    if (password !== confirmation) { setError('Mật khẩu nhập lại không khớp.'); return }
    if (!resetToken) { setError('Liên kết đặt lại mật khẩu thiếu token.'); return }
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

  return <main className="page">
    <header className="site-header"><div className="brand"><span className="brand-mark">H</span><span>H-Smart</span></div><span className="header-tag">Chợ đồ cũ dành cho sinh viên</span></header>
    {isSelling || editMatch ? <SellPage key={path} accessToken={tokens?.accessToken ?? null} editId={editMatch ? Number(editMatch[1]) : undefined} onSaved={(id) => navigate(`/products/${id}`)} onBack={() => navigate(editMatch ? `/products/${editMatch[1]}` : '/')} /> : isProducts ? <ProductPages key={path} path={path} accessToken={tokens?.accessToken ?? null} userId={tokens?.user.id ?? null} navigate={navigate} /> : <section className="auth-layout">
      <div className="intro">
        <span className="eyebrow">MUA BÁN THÔNG MINH HƠN</span>
        <h1>Đồ cũ hữu ích.<br /><em>Khởi đầu mới.</em></h1>
        <p>Tham gia cộng đồng sinh viên, tìm món đồ phù hợp và trao đổi an tâm với tài khoản đã xác thực email.</p>
        <div className="intro-stats"><span><strong>01</strong> Tạo tài khoản</span><span><strong>02</strong> Xác thực email</span><span><strong>03</strong> Bắt đầu khám phá</span></div>
      </div>
      <div className="card">
        {tokens ? <div className="success-state">
          <div className="success-icon">✓</div><span className="eyebrow">ĐĂNG NHẬP THÀNH CÔNG</span>
          <h2>Xin chào, {tokens.user.full_name || tokens.user.email}!</h2>
          <p>Email <strong>{tokens.user.email}</strong> đã được xác thực. Bạn có thể đăng bán sản phẩm.</p>
          {error && <div className="alert error" role="alert">{error}</div>}
          <button className="primary-button" type="button" onClick={() => navigate('/sell')}>Đăng bán sản phẩm</button>
          <button className="primary-button" type="button" onClick={() => navigate('/products')}>Xem sản phẩm đang bán</button>
          <button className="text-button" type="button" onClick={() => navigate('/me/products')}>Sản phẩm của tôi</button>
          <form className="change-form" onSubmit={submitChange}>
            <h3>Đổi mật khẩu</h3>
            <label>Mật khẩu hiện tại<input type="password" autoComplete="current-password" required value={currentPassword} onChange={(event) => setCurrentPassword(event.target.value)} /></label>
            <label>Mật khẩu mới<input type="password" autoComplete="new-password" minLength={8} maxLength={128} required value={password} onChange={(event) => setPassword(event.target.value)} /></label>
            <label>Nhập lại mật khẩu mới<input type="password" autoComplete="new-password" required value={confirmation} onChange={(event) => setConfirmation(event.target.value)} /></label>
            <button className="primary-button" type="submit" disabled={loading}>Đổi mật khẩu</button>
          </form>
          <button className="primary-button" type="button" onClick={logout} disabled={loading}>Đăng xuất</button>
          <button className="text-button" type="button" onClick={logoutAll} disabled={loading}>Đăng xuất khỏi mọi thiết bị</button>
        </div> : isVerifying ? <div className="success-state">
          <div className="success-icon">✉</div><span className="eyebrow">XÁC THỰC EMAIL</span><h2>Kiểm tra liên kết</h2>
          {loading && <p>Đang xác thực tài khoản…</p>}
          {notice && <div className="alert success" role="status">{notice}</div>}
          {error && <div className="alert error" role="alert">{error}</div>}
          {!verificationToken && !notice && !error && <div className="alert error" role="alert">Liên kết xác thực thiếu token.</div>}
          <a className="primary-button button-link" href="/">Về trang đăng nhập</a>
        </div> : isResetting ? <div>
          <span className="eyebrow">KHÔI PHỤC TÀI KHOẢN</span>
          <h2>Đặt lại mật khẩu</h2>
          <p className="card-subtitle">Chọn mật khẩu mới có ít nhất 8 ký tự.</p>
          {notice && <div className="alert success" role="status">{notice}</div>}
          {error && <div className="alert error" role="alert">{error}</div>}
          {!resetToken && !notice && <div className="alert error" role="alert">Liên kết đặt lại mật khẩu thiếu token.</div>}
          {resetToken && <form onSubmit={submitReset}>
            <label>Mật khẩu mới<input type="password" autoComplete="new-password" minLength={8} maxLength={128} required value={password} onChange={(event) => setPassword(event.target.value)} /></label>
            <label>Nhập lại mật khẩu<input type="password" autoComplete="new-password" required value={confirmation} onChange={(event) => setConfirmation(event.target.value)} /></label>
            <button className="primary-button" type="submit" disabled={loading}>{loading ? 'Đang xử lý…' : 'Đặt lại mật khẩu'}</button>
          </form>}
          <a className="primary-button button-link" href="/">Về trang đăng nhập</a>
        </div> : <>
          <div className="tabs" role="tablist" aria-label="Tài khoản">
            <button type="button" role="tab" aria-selected={mode === 'login'} className={mode === 'login' ? 'active' : ''} onClick={() => switchMode('login')}>Đăng nhập</button>
            <button type="button" role="tab" aria-selected={mode === 'register'} className={mode === 'register' ? 'active' : ''} onClick={() => switchMode('register')}>Đăng ký</button>
          </div>
          <h2>{mode === 'login' ? 'Chào mừng trở lại' : mode === 'register' ? 'Tạo tài khoản mới' : 'Quên mật khẩu?'}</h2>
          <p className="card-subtitle">{mode === 'login' ? 'Đăng nhập bằng email đã xác thực của bạn.' : mode === 'register' ? demoMailboxUrl ? 'Tạo tài khoản rồi xác thực email trong hộp thư thử nghiệm.' : 'Dùng email thật để nhận liên kết xác thực.' : 'Nhập email để nhận liên kết đặt lại mật khẩu.'}</p>
          {notice && <div className="alert success" role="status">{notice}</div>}
          {demoMailboxUrl && mode === 'register' && <p className="hint">Bản chạy thử: mở <a href={demoMailboxUrl} target="_blank" rel="noreferrer">hộp thư thử nghiệm</a>, bấm liên kết xác thực rồi quay lại đăng nhập.</p>}
          {error && <div className="alert error" role="alert">{error}</div>}
          <form onSubmit={submit}>
            {mode === 'register' && <label>Họ và tên <span>(không bắt buộc)</span><input type="text" autoComplete="name" maxLength={150} value={fullName} onChange={(event) => setFullName(event.target.value)} /></label>}
            <label>Email <input type="email" autoComplete="email" required maxLength={150} placeholder="ban@example.com" value={email} onChange={(event) => setEmail(event.target.value)} /></label>
            {mode !== 'forgot' && <label>Mật khẩu <input type="password" autoComplete={mode === 'login' ? 'current-password' : 'new-password'} required minLength={mode === 'register' ? 8 : undefined} maxLength={mode === 'register' ? 128 : undefined} value={password} onChange={(event) => setPassword(event.target.value)} /></label>}
            {mode === 'register' && <><p className="hint">Tối thiểu 8 ký tự. Tránh mật khẩu phổ biến và thông tin trong email.</p><label>Nhập lại mật khẩu <input type="password" autoComplete="new-password" required value={confirmation} onChange={(event) => setConfirmation(event.target.value)} /></label></>}
            <button className="primary-button" disabled={loading} type="submit">{loading ? 'Đang xử lý…' : mode === 'login' ? 'Đăng nhập' : mode === 'register' ? 'Tạo tài khoản' : 'Gửi liên kết đặt lại'}</button>
          </form>
          {mode === 'login' && <div className="resend">Chưa nhận được email xác thực? <button type="button" onClick={resend} disabled={loading || !email}>Gửi lại</button><br /><button type="button" onClick={() => switchMode('forgot')}>Quên mật khẩu?</button></div>}
          {mode === 'forgot' && <div className="resend"><button type="button" onClick={() => switchMode('login')}>Quay lại đăng nhập</button></div>}
        </>}
      </div>
    </section>}<footer>© 2026 H-Smart · Đồ án sinh viên</footer>
  </main>
}

export default App
