import { useCallback, useEffect, useState } from 'react'
import { Bell, CheckCheck, ChevronRight, LoaderCircle } from 'lucide-react'
import { notificationRequest, type NotificationItem, type NotificationList } from './notificationApi'

type Props = { accessToken: string; navigate: (path: string) => void }
const dateLabel = (value: string) => new Intl.DateTimeFormat('vi-VN', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))

export default function NotificationsPage({ accessToken, navigate }: Props) {
  const [items, setItems] = useState<NotificationItem[]>([])
  const [loading, setLoading] = useState(true)
  const [busyId, setBusyId] = useState<number | 'all' | null>(null)
  const [error, setError] = useState('')

  const load = useCallback(async () => {
    setLoading(true); setError('')
    try {
      const result = await notificationRequest<NotificationList>('/', accessToken)
      setItems(result.data.items)
    } catch (cause) { setError((cause as Error).message) }
    finally { setLoading(false) }
  }, [accessToken])

  useEffect(() => { void load() }, [load])

  async function markRead(id: number) {
    const item = items.find((entry) => entry.id === id)
    if (!item || item.read_at) return
    setBusyId(id); setError('')
    try {
      const result = await notificationRequest<{ id: number; read_at: string }>(`/${id}/read`, accessToken, { method: 'PATCH' })
      setItems((current) => current.map((entry) => entry.id === id ? { ...entry, read_at: result.data.read_at } : entry))
    } catch (cause) { setError((cause as Error).message) }
    finally { setBusyId(null) }
  }

  async function markAllRead() {
    if (!items.some((item) => !item.read_at)) return
    setBusyId('all'); setError('')
    try {
      await notificationRequest<null>('/read-all', accessToken, { method: 'PATCH' })
      const readAt = new Date().toISOString()
      setItems((current) => current.map((item) => item.read_at ? item : { ...item, read_at: readAt }))
    } catch (cause) { setError((cause as Error).message) }
    finally { setBusyId(null) }
  }

  async function open(item: NotificationItem) {
    await markRead(item.id)
    if (item.action_url.startsWith('/') && !item.action_url.startsWith('//')) navigate(item.action_url)
  }

  const unreadCount = items.filter((item) => !item.read_at).length
  return <main className="mx-auto min-h-[60vh] max-w-3xl px-4 py-10 xl:px-0">
    <div className="mb-6 flex items-end justify-between gap-3">
      <div><p className="text-xs font-medium uppercase tracking-[0.16em] text-black/50">Cập nhật tài khoản</p><h1 className="mt-2 font-satoshi text-3xl font-bold">Thông báo</h1><p className="mt-2 text-sm text-black/55">Tin nhắn, đề nghị giá và thay đổi đơn hàng.</p></div>
      <button type="button" onClick={() => void markAllRead()} disabled={unreadCount === 0 || busyId !== null} className="inline-flex items-center gap-2 rounded-xl border border-black/10 px-3 py-2 text-sm font-medium disabled:opacity-40"><CheckCheck size={17} /> <span className="hidden sm:inline">Đánh dấu tất cả đã đọc</span><span className="sm:hidden">Đọc tất cả</span></button>
    </div>
    {error && <div role="alert" className="mb-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{error}<button type="button" onClick={() => void load()} className="ml-2 underline">Thử lại</button></div>}
    <section aria-label="Danh sách thông báo" className="overflow-hidden rounded-2xl border border-black/10 bg-white">
      {loading ? <div className="flex items-center justify-center gap-2 p-12 text-sm text-black/55"><LoaderCircle className="animate-spin" size={18} /> Đang tải thông báo…</div> : items.length === 0 ? <div className="flex flex-col items-center p-12 text-center"><span className="flex h-12 w-12 items-center justify-center rounded-full bg-[#fff0eb] text-[#e65332]"><Bell size={22} /></span><h2 className="mt-4 font-semibold">Chưa có thông báo</h2><p className="mt-1 text-sm text-black/55">Thông báo mới sẽ xuất hiện tại đây.</p></div> : <ul className="divide-y divide-black/[0.07]">{items.map((item) => <li key={item.id}>
        <button type="button" onClick={() => void open(item)} disabled={busyId !== null} className={`flex w-full items-start gap-3 p-4 text-left transition hover:bg-[#fffaf8] sm:p-5 ${!item.read_at ? 'bg-[#fff8f5]' : ''}`}>
          <span className={`mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ${item.read_at ? 'bg-transparent' : 'bg-[#e65332]'}`} aria-label={item.read_at ? 'Đã đọc' : 'Chưa đọc'} />
          <span className="min-w-0 flex-1"><span className="flex flex-wrap items-center gap-x-2 gap-y-1"><strong className="text-sm">{item.title}</strong>{item.actor && <span className="text-xs text-black/50">· {item.actor.name}</span>}</span><span className="mt-1 block text-sm leading-relaxed text-black/65">{item.body}</span><time className="mt-2 block text-xs text-black/45" dateTime={item.created_at}>{dateLabel(item.created_at)}</time></span>
          {busyId === item.id ? <LoaderCircle className="mt-1 shrink-0 animate-spin text-black/40" size={18} /> : <ChevronRight className="mt-1 shrink-0 text-black/30" size={18} />}
        </button>
      </li>)}</ul>}
    </section>
  </main>
}
