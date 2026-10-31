import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import { ArrowLeft, Inbox, MessageCircle, Search, Send, ShoppingBag, SquarePen, X } from 'lucide-react'
import { productRequest } from './productApi'
import { Button } from './components/ui/button'
import { Alert } from './components/ui/alert'

type Thread = {
  product_id: number | null
  product_title: string
  user_id: number
  user_name: string
  user_role: 'member' | 'admin'
  last_message: string
  updated_at: string
  unread_count: number
}

type ChatMessage = {
  id: number
  sender_id: number
  recipient_id: number
  body: string
  sender_name: string
  created_at: string
}

type Contact = { id: number; full_name: string | null; role: 'admin' }

type Props = { accessToken: string; userId: number; navigate: (path: string) => void; isAdmin?: boolean }

function formatMessageDate(value: string) {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  const today = new Date()
  const sameDay = date.getFullYear() === today.getFullYear()
    && date.getMonth() === today.getMonth()
    && date.getDate() === today.getDate()
  return sameDay
    ? new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit' }).format(date)
    : new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).format(date)
}

function getInitials(name: string) {
  return name.trim().split(/\s+/).slice(-2).map((part) => part[0]?.toLocaleUpperCase('vi-VN') ?? '').join('') || '?'
}

export default function MessagePages({ accessToken, userId, navigate, isAdmin = false }: Props) {
  const params = new URLSearchParams(window.location.search)
  const productId = params.get('productId')
  const otherUserId = params.get('userId')
  const hasConversation = Boolean(otherUserId)
  const isProductConversation = Boolean(productId && otherUserId)
  const [threads, setThreads] = useState<Thread[]>([])
  const [productTitle, setProductTitle] = useState('')
  const [otherName, setOtherName] = useState('')
  const [otherRole, setOtherRole] = useState<'member' | 'admin' | null>(null)
  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [body, setBody] = useState('')
  const [query, setQuery] = useState('')
  const [newMessageOpen, setNewMessageOpen] = useState(false)
  const [contactQuery, setContactQuery] = useState('')
  const [contacts, setContacts] = useState<Contact[]>([])
  const [contactsLoading, setContactsLoading] = useState(false)
  const [contactError, setContactError] = useState('')
  const [error, setError] = useState('')
  const [threadsLoading, setThreadsLoading] = useState(true)
  const [conversationLoading, setConversationLoading] = useState(hasConversation)
  const [busy, setBusy] = useState(false)
  const [version, setVersion] = useState(0)
  const messagesEndRef = useRef<HTMLDivElement>(null)
  const conversationKeyRef = useRef('')

  useEffect(() => {
    let cancelled = false
    productRequest<Thread[]>('messages', accessToken)
      .then(({ data }) => { if (!cancelled) { setThreads(data); window.dispatchEvent(new Event('hsmart:messages-updated')) } })
      .catch((cause: Error) => { if (!cancelled) setError(cause.message) })
      .finally(() => { if (!cancelled) setThreadsLoading(false) })

    const requestedConversationKey = hasConversation && otherUserId ? `${productId ?? 'direct'}:${otherUserId}` : ''
    const isNewConversation = conversationKeyRef.current !== requestedConversationKey
    if (isNewConversation) conversationKeyRef.current = requestedConversationKey
    if (hasConversation && otherUserId) {
      if (isNewConversation) {
        setConversationLoading(true)
        setError('')
        setMessages([])
        setOtherName('')
        setProductTitle('')
        setOtherRole(null)
      }
      const path = productId ? `messages/${productId}/${otherUserId}` : `messages/${otherUserId}`
      productRequest<{ product: { title: string } | null; user: { full_name: string; role: 'member' | 'admin' }; messages: ChatMessage[] }>(path, accessToken)
        .then(({ data }) => {
          if (cancelled) return
          setError('')
          setProductTitle(data.product?.title ?? '')
          setOtherName(data.user.full_name)
          setOtherRole(data.user.role)
          setMessages((current) => current.length === data.messages.length && current.at(-1)?.id === data.messages.at(-1)?.id ? current : data.messages)
          setThreads((current) => current.map((thread) => thread.product_id === (productId ? Number(productId) : null) && thread.user_id === Number(otherUserId) ? { ...thread, unread_count: 0 } : thread))
          window.dispatchEvent(new Event('hsmart:messages-updated'))
        })
        .catch((cause: Error) => { if (!cancelled) setError(cause.message) })
        .finally(() => { if (!cancelled) setConversationLoading(false) })
    } else {
      setConversationLoading(false)
      setMessages([])
      setOtherName('')
      setOtherRole(null)
    }
    return () => { cancelled = true }
  }, [accessToken, hasConversation, productId, otherUserId, version])

  useEffect(() => {
    const refresh = () => {
      if (document.visibilityState === 'visible') setVersion((current) => current + 1)
    }
    const timer = window.setInterval(refresh, 10000)
    document.addEventListener('visibilitychange', refresh)
    return () => {
      window.clearInterval(timer)
      document.removeEventListener('visibilitychange', refresh)
    }
  }, [])

  useEffect(() => {
    if (!newMessageOpen || isAdmin) return
    let cancelled = false
    setContactsLoading(true)
    setContactError('')
    productRequest<Contact[]>('messages/contacts', accessToken)
      .then(({ data }) => { if (!cancelled) setContacts(data) })
      .catch((cause: Error) => { if (!cancelled) setContactError(cause.message) })
      .finally(() => { if (!cancelled) setContactsLoading(false) })
    return () => { cancelled = true }
  }, [accessToken, isAdmin, newMessageOpen])

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: version ? 'smooth' : 'auto', block: 'end' })
  }, [messages, conversationLoading])

  const filteredThreads = useMemo(() => {
    const normalizedQuery = query.trim().toLocaleLowerCase('vi-VN')
    if (!normalizedQuery) return threads
    return threads.filter((thread) => `${thread.user_name} ${thread.product_title} ${thread.last_message}`.toLocaleLowerCase('vi-VN').includes(normalizedQuery))
  }, [query, threads])

  async function send(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const messageBody = body.trim()
    if (!otherUserId || !messageBody || busy) return
    setBusy(true)
    setError('')
    try {
      const path = productId ? `products/${productId}/messages` : 'messages'
      await productRequest<ChatMessage>(path, accessToken, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ recipient_id: Number(otherUserId), body: messageBody }),
      })
      setBody('')
      setThreadsLoading(true)
      setConversationLoading(true)
      setVersion((current) => current + 1)
    } catch (cause) {
      setError((cause as Error).message)
    } finally {
      setBusy(false)
    }
  }

  function closeNewMessage() {
    setNewMessageOpen(false)
    setContactQuery('')
  }

  const selectedThread = threads.find((thread) => thread.product_id === (productId ? Number(productId) : null) && thread.user_id === Number(otherUserId))
  const conversationName = otherName || selectedThread?.user_name || 'Cuộc trò chuyện'
  const visibleContacts = contacts.filter((contact) => (contact.full_name ?? 'Quản trị viên').toLocaleLowerCase('vi-VN').includes(contactQuery.trim().toLocaleLowerCase('vi-VN')))
  const fieldClass = 'w-full rounded-2xl border border-black/10 bg-[#f4f5f7] px-4 py-3 text-sm outline-none transition focus:border-[#1684ff] focus:bg-white focus:ring-4 focus:ring-[#1684ff]/10'

  return <main className="mx-auto w-full max-w-[1440px] px-3 py-4 sm:px-5 sm:py-6 xl:px-8">
    <section aria-label="Tin nhắn" className="overflow-hidden rounded-2xl border border-black/10 bg-white shadow-[0_8px_30px_rgba(20,20,20,0.07)]">
      <div className="grid min-h-[min(76vh,780px)] grid-cols-1 lg:h-[min(76vh,780px)] lg:min-h-[560px] lg:grid-cols-[340px_minmax(0,1fr)]">
        <aside className={`${hasConversation ? 'hidden lg:flex' : 'flex'} min-h-[65vh] flex-col border-r border-black/10 lg:min-h-0`} aria-label="Danh sách cuộc trò chuyện">
          <div className="border-b border-black/[0.07] px-5 pb-4 pt-5">
            <div className="flex items-center justify-between">
              <div><p className="text-xs font-semibold uppercase tracking-[0.15em] text-[#e65332]">Hộp thư</p><h1 className="mt-1 text-2xl font-bold tracking-tight">Tin nhắn</h1></div>
              {!isAdmin && <button type="button" onClick={() => setNewMessageOpen(true)} className="inline-flex items-center gap-2 rounded-full bg-[#eaf3ff] px-3 py-2 text-xs font-semibold text-[#0866ff] transition hover:bg-[#dcecff] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#1684ff]" aria-label="Liên hệ quản trị viên"><SquarePen size={16} /><span className="hidden sm:inline">Liên hệ admin</span></button>}
            </div>
            <label className="mt-4 flex items-center gap-2 rounded-full bg-[#f1f2f4] px-3.5 py-2.5 focus-within:ring-2 focus-within:ring-[#1684ff]/30">
              <Search size={17} className="shrink-0 text-black/45" />
              <span className="sr-only">Tìm cuộc trò chuyện</span>
              <input value={query} onChange={(event) => setQuery(event.target.value)} className="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-black/45" placeholder="Tìm trong tin nhắn" />
            </label>
          </div>

          <div className="min-h-0 flex-1 overflow-y-auto p-2" aria-live="polite">
            {threadsLoading && <p className="px-4 py-6 text-sm text-black/50" role="status">Đang tải cuộc trò chuyện…</p>}
            {!threadsLoading && filteredThreads.length > 0 && filteredThreads.map((thread) => {
              const active = hasConversation && thread.product_id === (productId ? Number(productId) : null) && thread.user_id === Number(otherUserId)
              return <button key={`${thread.product_id ?? 'direct'}-${thread.user_id}`} type="button" onClick={() => navigate(thread.product_id ? `/me/messages?productId=${thread.product_id}&userId=${thread.user_id}` : isAdmin ? `/admin/messages?userId=${thread.user_id}` : `/me/messages?userId=${thread.user_id}`)} aria-current={active ? 'page' : undefined} className={`flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#1684ff] ${active ? 'bg-[#eaf3ff]' : 'hover:bg-[#f4f5f7]'}`}>
                <span className={`relative grid h-12 w-12 shrink-0 place-items-center rounded-full text-sm font-semibold ${active ? 'bg-[#d5e8ff] text-[#0866ff]' : 'bg-[#f0e6e2] text-[#8c4c3b]'}`} aria-hidden="true">{getInitials(thread.user_name)}{thread.unread_count > 0 && <span className="absolute bottom-0 right-0 h-3.5 w-3.5 rounded-full border-2 border-white bg-[#1684ff]" />}</span>
                <span className="min-w-0 flex-1"><span className="flex items-center justify-between gap-2"><strong className={`truncate text-sm ${thread.unread_count ? 'font-bold text-black' : 'font-semibold text-[#30343b]'}`}>{thread.user_name}</strong><time className="shrink-0 text-[11px] text-black/45" dateTime={thread.updated_at}>{formatMessageDate(thread.updated_at)}</time></span><span className="mt-1 block truncate text-xs text-black/50">{thread.product_title}</span><span className={`mt-0.5 block truncate text-sm ${thread.unread_count ? 'font-semibold text-black/75' : 'text-black/55'}`}>{thread.last_message}</span></span>
                {thread.unread_count > 0 && <span className="sr-only">{thread.unread_count} tin chưa đọc</span>}
              </button>
            })}
            {!threadsLoading && filteredThreads.length === 0 && <div className="px-5 py-10 text-center"><span className="mx-auto grid h-11 w-11 place-items-center rounded-full bg-[#f4f5f7] text-black/40"><Inbox size={20} /></span><p className="mt-3 text-sm font-semibold">{query.trim() ? 'Không tìm thấy cuộc trò chuyện' : 'Chưa có cuộc trò chuyện'}</p><p className="mt-1 text-xs leading-relaxed text-black/50">{query.trim() ? 'Thử tìm bằng tên người dùng hoặc tên sản phẩm.' : isAdmin ? 'Tin nhắn hỗ trợ từ người dùng sẽ xuất hiện ở đây.' : 'Tin nhắn với người bán và admin sẽ xuất hiện ở đây.'}</p></div>}
          </div>
        </aside>

        <section className={`${hasConversation ? 'flex' : 'hidden lg:flex'} min-h-[70vh] min-w-0 flex-col bg-white lg:min-h-0`} aria-label={hasConversation ? `Trò chuyện với ${conversationName}` : 'Khung tin nhắn'}>
          {hasConversation ? <>
            <header className="flex min-h-[76px] items-center gap-3 border-b border-black/[0.07] px-4 py-3 sm:px-6">
              <button type="button" onClick={() => navigate(isAdmin ? '/admin/messages' : '/me/messages')} aria-label="Quay lại danh sách tin nhắn" className="grid h-10 w-10 shrink-0 place-items-center rounded-full text-black/65 transition hover:bg-black/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#1684ff] lg:hidden"><ArrowLeft size={20} /></button>
              <span className="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[#f0e6e2] text-sm font-semibold text-[#8c4c3b]" aria-hidden="true">{getInitials(conversationName)}</span>
              <div className="min-w-0 flex-1"><h2 className="truncate font-bold">{conversationName}</h2><p className="mt-0.5 truncate text-xs text-black/50">{isProductConversation ? `Đang trao đổi về ${productTitle || 'sản phẩm'}` : isAdmin || otherRole === 'admin' ? 'Hỗ trợ khách hàng H-Smart' : 'Hỗ trợ người dùng'}</p></div>
              {isProductConversation && <button type="button" onClick={() => navigate(`/products/${productId}`)} className="hidden items-center gap-2 rounded-full border border-black/10 px-3 py-2 text-xs font-semibold text-black/65 transition hover:bg-black/[0.03] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#1684ff] sm:inline-flex"><ShoppingBag size={15} />Xem sản phẩm</button>}
            </header>

            <div className="flex min-h-0 flex-1 flex-col overflow-y-auto bg-white px-4 py-5 sm:px-8" aria-live="polite" aria-relevant="additions text">
              {conversationLoading && <p className="m-auto text-sm text-black/50" role="status">Đang tải tin nhắn…</p>}
              {!conversationLoading && messages.length > 0 && <div className="mt-auto space-y-3">
                {isProductConversation && <div className="mb-5 text-center"><span className="rounded-full bg-[#f4f5f7] px-3 py-1.5 text-[11px] font-medium text-black/50">Cuộc trò chuyện về: {productTitle}</span></div>}
                {messages.map((message, index) => {
                  const own = message.sender_id === userId
                  const previous = messages[index - 1]
                  const showSender = !own && (!previous || previous.sender_id !== message.sender_id)
                  return <article key={message.id} className={`flex ${own ? 'justify-end' : 'justify-start'}`}>
                    <div className={`max-w-[86%] sm:max-w-[68%] ${own ? 'items-end' : 'items-start'}`}>
                      {showSender && <p className="mb-1 ml-3 text-[11px] font-semibold text-black/45">{message.sender_name}</p>}
                      <div className={`rounded-[20px] px-4 py-2.5 text-[14px] leading-relaxed sm:text-[15px] ${own ? 'rounded-br-md bg-[#1684ff] text-white' : 'rounded-bl-md bg-[#f0f1f3] text-[#17191c]'}`}>
                        <p className="whitespace-pre-wrap break-words">{message.body}</p>
                      </div>
                      <time className={`mt-1 block px-2 text-[10px] text-black/40 ${own ? 'text-right' : ''}`} dateTime={message.created_at}>{formatMessageDate(message.created_at)}</time>
                    </div>
                  </article>
                })}
                <div ref={messagesEndRef} />
              </div>}
              {!conversationLoading && messages.length === 0 && <div className="m-auto max-w-sm px-4 text-center"><span className="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#f0f1f3] text-[#1684ff]"><MessageCircle size={25} /></span><h3 className="mt-4 font-semibold">Bắt đầu cuộc trò chuyện</h3><p className="mt-1 text-sm leading-relaxed text-black/50">{isProductConversation ? 'Hãy hỏi rõ thông tin sản phẩm trước khi giao dịch.' : isAdmin ? 'Người dùng có thể liên hệ admin khi cần hỗ trợ.' : 'Gửi câu hỏi cho đội ngũ quản trị H-Smart.'}</p></div>}
            </div>

            <form className="border-t border-black/[0.07] px-3 py-3 sm:px-5 sm:py-4" onSubmit={send}>
              {error && <Alert variant="error" className="mb-3" role="alert">{error}</Alert>}
              <div className="flex items-end gap-2 sm:gap-3">
                <label className="min-w-0 flex-1"><span className="sr-only">Soạn tin nhắn</span><textarea className={`${fieldClass} max-h-32 min-h-[46px] resize-y`} value={body} onChange={(event) => setBody(event.target.value)} maxLength={2000} rows={1} placeholder="Aa · Viết tin nhắn…" aria-describedby="message-length" /></label>
                <span id="message-length" className="sr-only">Tối đa 2.000 ký tự</span>
                <Button type="submit" aria-label={busy ? 'Đang gửi tin nhắn' : 'Gửi tin nhắn'} title="Gửi tin nhắn" disabled={busy || !body.trim()} className="mb-0.5 grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[#1684ff] p-0 text-white hover:bg-[#0866ff] disabled:bg-[#1684ff]/40"><Send size={19} /></Button>
              </div>
              <p className="mt-2 px-1 text-[11px] text-black/40">Không chia sẻ mật khẩu hoặc mã xác thực trong tin nhắn.</p>
            </form>
          </> : <div className="m-auto max-w-md px-6 py-12 text-center"><span className="mx-auto grid h-16 w-16 place-items-center rounded-full bg-[#eaf3ff] text-[#1684ff]"><MessageCircle size={28} /></span><h2 className="mt-5 text-lg font-semibold">Chọn một cuộc trò chuyện</h2><p className="mt-2 text-sm leading-relaxed text-black/50">{isAdmin ? 'Chọn tin nhắn từ người dùng để phản hồi hỗ trợ.' : 'Liên hệ admin để được hỗ trợ, hoặc mở tin nhắn từ trang sản phẩm để hỏi người bán.'}</p></div>}
        </section>
      </div>
      {!hasConversation && error && <div className="border-t border-black/[0.07] p-4"><Alert variant="error" role="alert">{error}</Alert></div>}
    </section>
    {newMessageOpen && <div className="fixed inset-0 z-50 grid place-items-center bg-black/45 px-4 py-6" onMouseDown={(event) => { if (event.target === event.currentTarget) closeNewMessage() }}>
      <section role="dialog" aria-modal="true" aria-labelledby="new-message-title" className="flex max-h-[min(640px,85vh)] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-2xl" onKeyDown={(event) => { if (event.key === 'Escape') closeNewMessage() }}>
        <header className="flex items-center justify-between border-b border-black/10 px-5 py-4">
          <div><p className="text-xs font-semibold uppercase tracking-[0.12em] text-[#1684ff]">Hỗ trợ H-Smart</p><h2 id="new-message-title" className="mt-1 text-xl font-bold">Liên hệ admin</h2></div>
          <button type="button" onClick={closeNewMessage} aria-label="Đóng cửa sổ liên hệ admin" className="grid h-10 w-10 place-items-center rounded-full bg-[#f1f2f4] text-black/60 transition hover:bg-[#e5e6e8] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#1684ff]"><X size={19} /></button>
        </header>
        <div className="border-b border-black/[0.07] px-5 py-4">
          <label className="flex items-center gap-2 rounded-full bg-[#f1f2f4] px-4 py-3 focus-within:ring-2 focus-within:ring-[#1684ff]/30"><Search size={17} className="shrink-0 text-black/45" /><span className="sr-only">Tìm quản trị viên theo tên</span><input autoFocus value={contactQuery} onChange={(event) => setContactQuery(event.target.value)} className="min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-black/45" placeholder="Tìm quản trị viên" /></label>
          <p className="mt-2 px-2 text-xs text-black/45">Chọn quản trị viên để hỏi về tài khoản, tin đăng hoặc giao dịch.</p>
        </div>
        {contactError && <div className="px-5 pt-4"><Alert variant="error" role="alert">{contactError}</Alert></div>}
        <div className="min-h-40 flex-1 overflow-y-auto p-2" aria-live="polite">
          {contactsLoading && <p className="px-4 py-5 text-sm text-black/50" role="status">Đang tải danh sách admin…</p>}
          {!contactsLoading && !visibleContacts.length && !contactError && <p className="px-4 py-6 text-sm text-black/50">{contacts.length ? 'Không tìm thấy quản trị viên phù hợp.' : 'Hiện chưa có quản trị viên đang hoạt động.'}</p>}
          {!contactsLoading && visibleContacts.map((contact) => <button key={contact.id} type="button" onClick={() => { closeNewMessage(); navigate(`/me/messages?userId=${contact.id}`) }} className="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left transition hover:bg-[#f4f5f7] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#1684ff]">
            <span className="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[#eaf3ff] text-sm font-semibold text-[#0866ff]" aria-hidden="true">{getInitials(contact.full_name ?? 'Quản trị viên')}</span>
            <span className="min-w-0 flex-1"><strong className="block truncate text-sm">{contact.full_name || 'Quản trị viên H-Smart'}</strong><span className="mt-0.5 block text-xs text-black/45">Quản trị viên H-Smart</span></span>
            <MessageCircle size={18} className="text-[#1684ff]" aria-hidden="true" />
          </button>)}
        </div>
      </section>
    </div>}
  </main>
}
