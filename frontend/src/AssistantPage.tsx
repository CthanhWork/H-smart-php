import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Bot, MessageSquareText, Plus, Send, Trash2 } from 'lucide-react'
import { productRequest } from './productApi'
import { Button } from './components/ui/button'
import { Alert } from './components/ui/alert'

type ChatMessage = { id: number; role: 'user' | 'assistant'; content: string; created_at: string; citations?: Array<{ source_id: string; title: string; section: string }> }
type Conversation = { id: number; title: string; updated_at: string; messages_count: number }
type Props = { accessToken: string }
const suggestions = ['Làm thế nào để đăng bán sản phẩm?', 'Thanh toán và vận chuyển hiện hoạt động thế nào?', 'Tôi liên hệ quản trị viên ở đâu?']

export default function AssistantPage({ accessToken }: Props) {
  const [conversations, setConversations] = useState<Conversation[]>([])
  const [conversationId, setConversationId] = useState<number | null>(null)
  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [draft, setDraft] = useState('')
  const [loading, setLoading] = useState(false)
  const [historyLoading, setHistoryLoading] = useState(true)
  const [error, setError] = useState('')
  const scrollRef = useRef<HTMLDivElement>(null)

  async function loadConversations() {
    setHistoryLoading(true)
    try {
      const result = await productRequest<Conversation[]>('assistant/conversations', accessToken)
      setConversations(result.data)
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Không tải được lịch sử trò chuyện.')
    } finally { setHistoryLoading(false) }
  }

  useEffect(() => { void loadConversations() }, [accessToken])
  useEffect(() => { scrollRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' }) }, [messages, loading])

  async function openConversation(id: number) {
    setError('')
    try {
      const result = await productRequest<{ id: number; title: string; messages: ChatMessage[] }>(`assistant/conversations/${id}`, accessToken)
      setConversationId(result.data.id)
      setMessages(result.data.messages)
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Không mở được cuộc trò chuyện.') }
  }

  function newConversation() { setConversationId(null); setMessages([]); setError('') }

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
      const result = await productRequest<{ conversation_id: number; user_message: ChatMessage; assistant_message: ChatMessage }>(
        'assistant/chat', accessToken, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ conversation_id: conversationId, message }) },
      )
      setConversationId(result.data.conversation_id)
      setMessages((current) => [...current.filter((item) => item.id !== optimisticId), result.data.user_message, result.data.assistant_message])
      await loadConversations()
    } catch (cause) {
      setMessages((current) => current.filter((item) => item.id !== optimisticId))
      setDraft(message)
      setError(cause instanceof Error ? cause.message : 'Trợ lý đang tạm thời không khả dụng.')
    } finally { setLoading(false) }
  }

  return <main className="mx-auto max-w-frame px-4 py-8 sm:py-12">
    <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div><h1 className="mt-2 text-3xl font-bold tracking-tight">Trợ lý</h1><p className="mt-2 max-w-2xl text-sm text-black/55">Hỏi về cách sử dụng và chính sách. Câu trả lời dựa trên hướng dẫn đã duyệt của H-Smart.</p></div>
      <Button variant="outline" onClick={newConversation} disabled={loading}><Plus size={17} /> Cuộc trò chuyện mới</Button>
    </div>
    {error && <Alert variant="error" className="mb-4" role="alert">{error}</Alert>}
    <div className="grid min-h-[560px] overflow-hidden rounded-2xl border border-black/10 bg-white shadow-sm lg:grid-cols-[260px_minmax(0,1fr)]">
      <aside className="border-b border-black/10 bg-[#faf9f7] p-4 lg:border-b-0 lg:border-r">
        <h2 className="mb-3 text-xs font-semibold uppercase tracking-wider text-black/45">Lịch sử gần đây</h2>
        {historyLoading ? <p className="text-sm text-black/45">Đang tải…</p> : conversations.length === 0 ? <p className="text-sm leading-6 text-black/45">Các câu hỏi của bạn sẽ xuất hiện ở đây.</p> : <ul className="space-y-1">
          {conversations.map((conversation) => <li key={conversation.id} className={`group flex items-center gap-1 rounded-xl ${conversationId === conversation.id ? 'bg-[#fff0eb]' : 'hover:bg-black/[0.04]'}`}>
            <button type="button" onClick={() => void openConversation(conversation.id)} disabled={loading} className="flex min-w-0 flex-1 items-center gap-2 px-3 py-2.5 text-left text-sm disabled:opacity-50"><MessageSquareText size={16} className="shrink-0 text-black/40" /><span className="truncate">{conversation.title}</span></button>
            <button type="button" onClick={() => void deleteConversation(conversation.id)} disabled={loading} aria-label={`Xóa ${conversation.title}`} className="mr-2 rounded-lg p-1.5 text-black/35 opacity-100 hover:bg-white hover:text-red-600 disabled:opacity-30 sm:opacity-0 sm:group-hover:opacity-100"><Trash2 size={15} /></button>
          </li>)}
        </ul>}
        <div className="mt-6 rounded-xl bg-white p-3 text-xs leading-5 text-black/55 ring-1 ring-black/[0.06]">Trợ lý tra cứu đơn bạn tham gia. Không nhập mật khẩu, mã xác thực hoặc thông tin thanh toán.</div>
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
          <p className="mt-2 text-center text-[11px] text-black/40">Trợ lý chỉ hỗ trợ hướng dẫn chung về H-Smart. Không gửi thông tin cá nhân nhạy cảm.</p>
        </form>
      </section>
    </div>
  </main>
}
