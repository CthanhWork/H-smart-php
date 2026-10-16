import { useEffect, useState, type FormEvent } from 'react'
import { productRequest } from './productApi'
import { ArrowLeft, MessageCircle, Send } from 'lucide-react'
import { Button } from './components/ui/button'
import { Alert } from './components/ui/alert'

type Thread = { product_id: number; product_title: string; user_id: number; user_name: string; last_message: string; updated_at: string; unread_count: number }
type ChatMessage = { id: number; sender_id: number; recipient_id: number; body: string; sender_name: string; created_at: string }
type Props = { accessToken: string; userId: number; navigate: (path: string) => void }

export default function MessagePages({ accessToken, userId, navigate }: Props) {
  const params = new URLSearchParams(window.location.search)
  const productId = params.get('productId')
  const otherUserId = params.get('userId')
  const [threads, setThreads] = useState<Thread[]>([])
  const [productTitle, setProductTitle] = useState('')
  const [otherName, setOtherName] = useState('')
  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [body, setBody] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [version, setVersion] = useState(0)

  useEffect(() => {
    let cancelled = false
    if (productId && otherUserId) {
      productRequest<{ product: { title: string }; user: { full_name: string }; messages: ChatMessage[] }>(`messages/${productId}/${otherUserId}`, accessToken)
        .then(({ data }) => { if (!cancelled) { setProductTitle(data.product.title); setOtherName(data.user.full_name); setMessages(data.messages) } })
        .catch((cause: Error) => { if (!cancelled) setError(cause.message) })
        .finally(() => { if (!cancelled) setLoading(false) })
    } else {
      productRequest<Thread[]>('messages', accessToken)
        .then(({ data }) => { if (!cancelled) setThreads(data) })
        .catch((cause: Error) => { if (!cancelled) setError(cause.message) })
        .finally(() => { if (!cancelled) setLoading(false) })
    }
    return () => { cancelled = true }
  }, [accessToken, productId, otherUserId, version])

  async function send(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!productId || !otherUserId || !body.trim()) return
    setBusy(true); setError('')
    try {
      await productRequest<ChatMessage>(`products/${productId}/messages`, accessToken, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ recipient_id: Number(otherUserId), body: body.trim() }),
      })
      setBody(''); setLoading(true); setVersion((current) => current + 1)
    } catch (cause) { setError((cause as Error).message) }
    finally { setBusy(false) }
  }

  const fieldClass = 'w-full rounded-2xl border border-black/15 bg-white px-4 py-3 text-sm outline-none focus:border-black'
  return <main className="mx-auto min-h-[55vh] w-full max-w-frame px-4 py-10 xl:px-0">
    <button className="mb-6 inline-flex items-center gap-2 text-sm text-black/55 hover:text-black" onClick={() => navigate(productId ? '/me/messages' : '/')}><ArrowLeft size={16} />{productId ? 'Hộp thư' : 'Về tài khoản'}</button>
    <div className="mb-6 flex flex-wrap items-end justify-between gap-3"><div><p className="text-xs uppercase tracking-[0.16em] text-black/50">Trao đổi</p><h1 className="mt-2 font-satoshi font-bold tracking-tight text-2xl uppercase sm:text-4xl">{productId ? productTitle || 'Cuộc trò chuyện' : 'Tin nhắn'}</h1></div>{productId && <span className="text-sm text-black/55">{otherName}</span>}</div>
    {error && <Alert variant="error" className="mb-4">{error}</Alert>}{loading && <p className="py-8 text-sm text-black/55">Đang tải tin nhắn…</p>}
    {!productId && !loading && <div className="space-y-3">{threads.length ? threads.map((thread) => <button className="flex w-full items-center gap-4 rounded-[20px] border border-black/10 bg-white p-4 text-left shadow-sm transition hover:border-black/30 sm:p-5" key={`${thread.product_id}-${thread.user_id}`} onClick={() => navigate(`/me/messages?productId=${thread.product_id}&userId=${thread.user_id}`)}><span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#F0F0F0]"><MessageCircle size={20} /></span><span className="min-w-0 flex-1"><strong className="block truncate">{thread.user_name} · {thread.product_title}</strong><span className="mt-1 block truncate text-sm text-black/60">{thread.last_message}</span><small className="mt-1 block text-xs text-black/40">{new Date(thread.updated_at).toLocaleString('vi-VN')}</small></span>{thread.unread_count > 0 && <span className="rounded-full bg-black px-3 py-1 text-xs text-white">{thread.unread_count} tin mới</span>}</button>) : <div className="rounded-[20px] border border-dashed border-black/20 p-10 text-center text-sm text-black/55"><MessageCircle className="mx-auto mb-3" size={28} />Chưa có tin nhắn. Mở một sản phẩm để hỏi người bán nếu bạn cần thêm thông tin.</div>}</div>}
    {productId && !loading && <div className="overflow-hidden rounded-[20px] border border-black/10 bg-white shadow-sm"><div className="max-h-[55vh] min-h-56 space-y-3 overflow-y-auto bg-[#F7F7F7] p-4 sm:p-6">{messages.length ? messages.map((message) => <article className={`max-w-[85%] rounded-2xl p-4 text-sm sm:max-w-[70%] ${message.sender_id === userId ? 'ml-auto bg-black text-white' : 'bg-white text-black shadow-sm'}`} key={message.id}><strong className="text-xs opacity-70">{message.sender_id === userId ? 'Bạn' : message.sender_name}</strong><p className="mt-1 whitespace-pre-wrap leading-relaxed">{message.body}</p><small className="mt-2 block text-[11px] opacity-55">{new Date(message.created_at).toLocaleString('vi-VN')}</small></article>) : <div className="flex h-48 items-center justify-center text-sm text-black/45">Bắt đầu cuộc trò chuyện bằng tin nhắn đầu tiên.</div>}</div>
      <form className="flex flex-col gap-3 border-t border-black/10 p-4 sm:flex-row sm:items-end" onSubmit={send}><label className="flex-1 text-sm font-medium">Tin nhắn<textarea className={`${fieldClass} mt-1.5 resize-y`} value={body} onChange={(event) => setBody(event.target.value)} maxLength={2000} rows={2} required placeholder="Viết tin nhắn (tối đa 2.000 ký tự)" /></label><Button className="shrink-0" disabled={busy || !body.trim()}><Send size={15} className="mr-2" />{busy ? 'Đang gửi…' : 'Gửi tin nhắn'}</Button></form>
    </div>}
  </main>
}
