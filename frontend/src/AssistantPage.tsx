import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Bot, MessageSquareText, Plus, Send, Trash2 } from 'lucide-react'
import { productRequest } from './productApi'
import { Button } from './components/ui/button'
import { Alert } from './components/ui/alert'
import type { Product } from './productApi'

type Citation = { source_id: string; title: string; section: string }
type StructuredData = { intent?: string; products?: Product[]; applied_filters?: Record<string, string | number>; actions?: Array<{ type: string; label: string; url: string }> }
type ChatMessage = { id: number; role: 'user' | 'assistant'; content: string; created_at: string; citations?: Citation[]; structured_data?: StructuredData }
type Conversation = { id: number; title: string; updated_at: string; messages_count: number }
type Props = { accessToken: string; navigate: (path: string) => void }
const suggestions = ['Tìm nồi cơm điện dưới 500 nghìn', 'Có bàn học nào từ 200 đến 700 nghìn không?', 'Phí bán hàng bao nhiêu?']

function AssistantProductCard({ product: snapshot, accessToken, navigate, onAdd }: { product: Product; accessToken: string; navigate: (path: string) => void; onAdd: (product: Product) => void }) {
  const [product, setProduct] = useState(snapshot)
  const [unavailable, setUnavailable] = useState(false)
  const [priceChanged, setPriceChanged] = useState(false)
  useEffect(() => {
    let cancelled = false
    productRequest<Product>(`products/${snapshot.id}`, accessToken).then(({ data }) => {
      if (cancelled) return
      setPriceChanged(data.price !== snapshot.price)
      setProduct(data)
    }).catch(() => { if (!cancelled) setUnavailable(true) })
    return () => { cancelled = true }
  }, [snapshot.id, accessToken])
  return <article className="overflow-hidden rounded-xl border border-black/10 bg-white text-black sm:flex">
    {product.images?.[0]?.url ? <img src={product.images[0].url} alt={product.title} className="h-36 w-full object-cover sm:w-36" loading="lazy"/> : <div className="grid h-36 w-full place-items-center bg-[#f5f5f4] text-xs text-black/40 sm:w-36">Không có ảnh</div>}
    <div className="flex min-w-0 flex-1 flex-col p-3"><p className="line-clamp-2 text-sm font-semibold">{product.title}</p><p className="mt-1 text-sm font-bold text-[#e65332]">{new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(product.price)}</p><p className="mt-1 text-xs text-black/50">{product.condition === 'good' ? 'Còn tốt' : product.condition === 'like_new' ? 'Như mới' : product.condition === 'new' ? 'Mới' : 'Đã qua sử dụng'} · {product.seller?.name ?? 'Người bán H-Smart'}{unavailable ? ' · Không còn khả dụng' : ''}</p>
      {priceChanged && <p className="mt-1 text-xs font-medium text-amber-800">Giá hiện tại đã thay đổi so với lúc trợ lý trả lời.</p>}
      {unavailable && <p className="mt-1 text-xs text-amber-800">Tin đã bán, bị ẩn hoặc không còn hiển thị.</p>}
      <div className="mt-auto flex flex-wrap gap-2 pt-3"><button className="rounded-lg border border-black/15 px-3 py-1.5 text-xs font-semibold" onClick={() => navigate(`/products/${product.id}`)}>Xem sản phẩm hiện tại</button><button disabled={!accessToken || unavailable} className="rounded-lg bg-[#e65332] px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50" onClick={() => onAdd(product)}>{accessToken ? 'Thêm vào giỏ' : 'Đăng nhập để thêm'}</button><button className="rounded-lg px-2 py-1.5 text-xs text-[#b53a20] underline" onClick={() => navigate('/me/cart')}>Xem giỏ</button></div>
    </div>
  </article>
}

export default function AssistantPage({ accessToken, navigate }: Props) {
  const [conversations, setConversations] = useState<Conversation[]>([])
  const [conversationId, setConversationId] = useState<number | null>(null)
  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [draft, setDraft] = useState('')
  const [loading, setLoading] = useState(false)
  const [historyLoading, setHistoryLoading] = useState(true)
  const [error, setError] = useState('')
  const [guestSearchContext, setGuestSearchContext] = useState<{ filters: Record<string, string | number>; keyword: string; product_ids: number[] }>({ filters: {}, keyword: '', product_ids: [] })
  const scrollRef = useRef<HTMLDivElement>(null)

  async function loadConversations() {
    setHistoryLoading(true)
    if (!accessToken) { setConversations([]); setConversationId(null); setMessages([]); setGuestSearchContext({ filters: {}, keyword: '', product_ids: [] }); setHistoryLoading(false); return }
    try {
      const result = await productRequest<Conversation[]>('assistant/conversations', accessToken)
      setConversations(result.data)
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Không tải được lịch sử trò chuyện.')
    } finally { setHistoryLoading(false) }
  }

  useEffect(() => { setConversationId(null); setMessages([]); setError(''); void loadConversations() }, [accessToken])
  useEffect(() => { scrollRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' }) }, [messages, loading])

  async function openConversation(id: number) {
    setError('')
    try {
      const result = await productRequest<{ id: number; title: string; messages: ChatMessage[] }>(`assistant/conversations/${id}`, accessToken)
      setConversationId(result.data.id)
      setMessages(result.data.messages)
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Không mở được cuộc trò chuyện.') }
  }

  function newConversation() { setConversationId(null); setMessages([]); setGuestSearchContext({ filters: {}, keyword: '', product_ids: [] }); setError('') }

  async function deleteConversation(id: number) {
    try {
      await productRequest<null>(`assistant/conversations/${id}`, accessToken, { method: 'DELETE' })
      setConversations((current) => current.filter((conversation) => conversation.id !== id))
      if (conversationId === id) newConversation()
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Không xóa được cuộc trò chuyện.') }
  }

  async function sendMessage(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const message = draft.trim()
    if (message.length < 2 || loading) return
    setError('')
    setDraft('')
    const optimisticId = Date.now()
    setMessages((current) => [...current, { id: optimisticId, role: 'user', content: message, created_at: new Date().toISOString() }])
    setLoading(true)
    try {
      const result = await productRequest<{ conversation_id: number | null; user_message: ChatMessage; assistant_message: ChatMessage; products?: Product[]; content?: string; intent?: string; applied_filters?: StructuredData['applied_filters']; actions?: StructuredData['actions'] }>(
        'assistant/chat', accessToken, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ conversation_id: conversationId, message, ...(!accessToken ? { search_context: guestSearchContext } : {}) }) },
      )
      setConversationId(result.data.conversation_id)
      const assistantMessage = { ...result.data.assistant_message, structured_data: result.data.assistant_message.structured_data ?? (result.data.products ? { products: result.data.products, intent: result.data.intent, applied_filters: result.data.applied_filters, actions: result.data.actions } : undefined) }
      setMessages((current) => [...current.filter((item) => item.id !== optimisticId), result.data.user_message, assistantMessage])
      if (!accessToken && result.data.products) setGuestSearchContext({ filters: result.data.applied_filters ?? {}, keyword: String(result.data.applied_filters?.keyword ?? ''), product_ids: result.data.products.map((product) => product.id) })
      if (accessToken) await loadConversations()
    } catch (cause) {
      setMessages((current) => current.filter((item) => item.id !== optimisticId))
      setDraft(message)
      setError(cause instanceof Error ? cause.message : 'Trợ lý đang tạm thời không khả dụng.')
    } finally { setLoading(false) }
  }

  async function addToCart(product: Product) {
    if (!accessToken) { navigate('/login'); return }
    try {
      await productRequest('me/cart', accessToken, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ product_id: product.id }) })
      setNotice('Đã thêm sản phẩm vào giỏ hàng.')
    } catch (cause) { setError((cause as Error).message) }
  }

  const [notice, setNotice] = useState('')

  return <main className="mx-auto max-w-frame px-4 py-8 sm:py-12">
    <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div><h1 className="mt-2 text-3xl font-bold tracking-tight">Trợ lý H-Smart</h1><p className="mt-2 max-w-2xl text-sm text-black/55">Tìm sản phẩm bằng tiếng Việt tự nhiên, hỏi về chính sách hoặc tra cứu đơn hàng của bạn.</p></div>
      <Button variant="outline" onClick={newConversation} disabled={loading}><Plus size={17} /> Cuộc trò chuyện mới</Button>
    </div>
    {error && <Alert variant="error" className="mb-4" role="alert">{error}</Alert>}{notice && <Alert variant="success" className="mb-4" role="status">{notice}<button className="ml-3 underline" onClick={() => navigate('/me/cart')}>Mở giỏ hàng</button></Alert>}
    <div className="grid min-h-[560px] overflow-hidden rounded-2xl border border-black/10 bg-white shadow-sm lg:grid-cols-[260px_minmax(0,1fr)]">
      <aside className="border-b border-black/10 bg-[#faf9f7] p-4 lg:border-b-0 lg:border-r">
        <h2 className="mb-3 text-xs font-semibold uppercase tracking-wider text-black/45">Lịch sử gần đây</h2>
        {!accessToken ? <p className="text-sm leading-6 text-black/45">Đăng nhập để lưu lịch sử và tra cứu đơn hàng.</p> : historyLoading ? <p className="text-sm text-black/45">Đang tải…</p> : conversations.length === 0 ? <p className="text-sm leading-6 text-black/45">Các câu hỏi của bạn sẽ xuất hiện ở đây.</p> : <ul className="space-y-1">
          {conversations.map((conversation) => <li key={conversation.id} className={`group flex items-center gap-1 rounded-xl ${conversationId === conversation.id ? 'bg-[#fff0eb]' : 'hover:bg-black/[0.04]'}`}>
            <button type="button" onClick={() => void openConversation(conversation.id)} disabled={loading} className="flex min-w-0 flex-1 items-center gap-2 px-3 py-2.5 text-left text-sm disabled:opacity-50"><MessageSquareText size={16} className="shrink-0 text-black/40" /><span className="truncate">{conversation.title}</span></button>
            <button type="button" onClick={() => void deleteConversation(conversation.id)} disabled={loading} aria-label={`Xóa ${conversation.title}`} className="mr-2 rounded-lg p-1.5 text-black/35 opacity-100 hover:bg-white hover:text-red-600 disabled:opacity-30 sm:opacity-0 sm:group-hover:opacity-100"><Trash2 size={15} /></button>
          </li>)}
        </ul>}
        <div className="mt-6 rounded-xl bg-white p-3 text-xs leading-5 text-black/55 ring-1 ring-black/[0.06]">Có thể tìm sản phẩm công khai. Đăng nhập để lưu hội thoại, thêm vào giỏ và tra cứu đơn hàng.</div>
      </aside>
      <section className="flex min-h-[560px] flex-col">
        <div className="flex-1 space-y-5 overflow-y-auto p-4 sm:p-6">
          {messages.length === 0 && <div className="mx-auto flex max-w-xl flex-col items-center py-12 text-center">
            <span className="grid h-14 w-14 place-items-center rounded-2xl bg-[#fff0eb] text-[#d94b2b]"><Bot size={27} /></span>
            <h2 className="mt-4 text-xl font-semibold">Bạn cần hỗ trợ gì?</h2><p className="mt-2 text-sm text-black/50">Mình có thể hướng dẫn các chức năng mua bán và chính sách của H-Smart.</p>
            <div className="mt-6 flex flex-wrap justify-center gap-2">{suggestions.map((suggestion) => <button key={suggestion} type="button" onClick={() => setDraft(suggestion)} className="rounded-full border border-black/10 px-3.5 py-2 text-xs text-black/65 transition hover:border-[#e65332]/40 hover:bg-[#fff8f5]">{suggestion}</button>)}</div>
          </div>}
          {messages.map((message) => <article key={message.id} className={`flex ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}>
            <div className={`max-w-[90%] rounded-2xl px-4 py-3 sm:max-w-[80%] ${message.role === 'user' ? 'rounded-br-md bg-[#e65332] text-white' : 'rounded-bl-md bg-[#f5f5f4] text-black'}`}>
              <p className="whitespace-pre-wrap text-sm leading-6">{message.content}</p>
              {message.role === 'assistant' && message.structured_data?.products?.length ? <div className="mt-4 grid gap-3">{message.structured_data.products.map((product) => <AssistantProductCard key={`${message.id}-${product.id}`} product={product} accessToken={accessToken} navigate={navigate} onAdd={(current) => void addToCart(current)} />)}</div> : null}
              {message.role === 'assistant' && message.structured_data?.actions?.length ? <div className="mt-3 flex flex-wrap gap-2">{message.structured_data.actions.map((action) => <button key={`${message.id}-${action.type}`} type="button" onClick={() => navigate(action.url)} className="rounded-lg border border-black/15 bg-white px-3 py-1.5 text-xs font-semibold text-[#b53a20]">{action.label}</button>)}</div> : null}
              {message.role === 'assistant' && message.citations?.length ? <ul className="mt-3 border-t border-black/10 pt-2 text-xs text-black/55">{message.citations.map((citation, index) => <li key={`${citation.title}-${citation.section}-${index}`}>{citation.source_id === 'order' ? <a className="font-medium text-[#b53a20] underline" href={citation.section}>{citation.title}</a> : <>{citation.title} · {citation.section}</>}</li>)}</ul> : null}
            </div>
          </article>)}
          {loading && <div className="flex"><div className="rounded-2xl rounded-bl-md bg-[#f5f5f4] px-4 py-3 text-sm text-black/55" role="status">Đang tìm trong tài liệu H-Smart…</div></div>}
          <div ref={scrollRef} />
        </div>
        <form onSubmit={sendMessage} className="border-t border-black/10 p-3 sm:p-4">
          <div className="flex items-end gap-2 rounded-2xl border border-black/10 bg-white p-2 focus-within:border-[#e65332]/50 focus-within:ring-4 focus-within:ring-[#e65332]/10">
            <textarea value={draft} onChange={(event) => setDraft(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); event.currentTarget.form?.requestSubmit() } }} maxLength={1200} rows={1} placeholder="Nhập câu hỏi của bạn…" aria-label="Câu hỏi cho trợ lý" className="max-h-32 min-h-10 flex-1 resize-y bg-transparent px-2 py-2 text-sm outline-none placeholder:text-black/40" />
            <Button type="submit" aria-label="Gửi câu hỏi" disabled={loading || draft.trim().length < 2} className="h-10 w-10 shrink-0 rounded-xl p-0"><Send size={17} /></Button>
          </div>
          <p className="mt-2 text-center text-[11px] text-black/40">Trợ lý có thể tìm sản phẩm và hướng dẫn chính sách. Không gửi thông tin cá nhân nhạy cảm.</p>
        </form>
      </section>
    </div>
  </main>
}
