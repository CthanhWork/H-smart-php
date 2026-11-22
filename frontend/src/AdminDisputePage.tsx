import { useEffect, useState } from 'react'
import { AlertTriangle, RefreshCw } from 'lucide-react'
import { Alert } from './components/ui/alert'
import { Button } from './components/ui/button'
import { productRequest } from './productApi'

type Dispute = {
  id: number; status: string; reason: string; details: string; evidence_notes: string[] | null; resolution: string | null; resolution_note: string | null
  buyer_wallet_refund_consent_at: string | null; created_at: string; opener: { id: number; name: string } | null
  events: Array<{ action: string; note: string | null; created_at: string }>
  order: { id: number; status: string; payment_status: string | null; payment_provider: string | null; payout_status: string; product_amount: number; shipping_fee: number; platform_fee: number; total_amount: number; buyer: { id: number; username: string; full_name: string | null; email: string } | null; seller: { id: number; username: string; full_name: string | null; email: string } | null; product: { id: number; title: string; status: string } | null }
}
type Props = { accessToken: string }
const money = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)

export default function AdminDisputePage({ accessToken }: Props) {
  const [items, setItems] = useState<Dispute[]>([])
  const [notes, setNotes] = useState<Record<number, string>>({})
  const [loading, setLoading] = useState(true)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  async function load() {
    setLoading(true); setError('')
    try { setItems((await productRequest<{ content: Dispute[] }>('admin/disputes', accessToken)).data.content) }
    catch (cause) { setError((cause as Error).message) }
    finally { setLoading(false) }
  }
  useEffect(() => { void load() }, [accessToken])

  async function resolve(dispute: Dispute, resolution: 'release_to_seller' | 'refund_to_buyer_wallet' | 'manual_reconciliation') {
    const note = (notes[dispute.id] ?? '').trim()
    if (note.length < 5) { setError('Nhập lý do xử lý tối thiểu 5 ký tự.'); return }
    if (resolution === 'refund_to_buyer_wallet' && !window.confirm('Người mua đã đồng ý nhận hoàn vào số dư. Xác nhận ghi bút toán hoàn tiền mô phỏng?')) return
    setBusyId(dispute.id); setError(''); setNotice('')
    try {
      const result = await productRequest<Dispute>(`admin/disputes/${dispute.id}/resolve`, accessToken, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ resolution, note }) })
      setNotice(result.message); await load()
    } catch (cause) { setError((cause as Error).message) }
    finally { setBusyId(null) }
  }

  return <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div className="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 p-5"><div><h2 className="flex items-center gap-2 font-semibold"><AlertTriangle size={18}/>Khiếu nại đơn hàng</h2><p className="mt-1 text-sm text-slate-500">Đơn đang khiếu nại bị giữ. Hoàn vào số dư chỉ bật sau khi người mua chủ động đồng ý.</p></div><Button size="sm" variant="outline" onClick={() => void load()} disabled={loading}><RefreshCw size={15}/>Làm mới</Button></div>
    {error && <Alert variant="error" className="m-5">{error}</Alert>}{notice && <Alert variant="success" className="m-5">{notice}</Alert>}
    {loading ? <p className="p-6 text-sm text-slate-500">Đang tải…</p> : items.length === 0 ? <p className="p-8 text-center text-sm text-slate-500">Không có khiếu nại đang mở.</p> : <div className="divide-y divide-slate-100">{items.map((item) => <article key={item.id} className="p-5 sm:p-6">
      <div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-xs text-slate-500">Khiếu nại #{item.id} · Đơn #{item.order.id} · {new Date(item.created_at).toLocaleString('vi-VN')}</p><h3 className="mt-1 text-lg font-semibold">{item.reason}</h3><p className="mt-1 text-sm text-slate-600">{item.order.product?.title ?? 'Sản phẩm'} · {item.order.payment_provider} · {item.order.payout_status}</p></div><span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">{item.status === 'open' ? 'Đang xử lý' : 'Chờ đối soát'}</span></div>
      <p className="mt-4 whitespace-pre-wrap text-sm leading-6 text-slate-700">{item.details}</p>
      {item.evidence_notes?.length ? <div className="mt-3 rounded-xl bg-slate-50 p-3"><h4 className="text-xs font-semibold uppercase text-slate-500">Ghi chú/bằng chứng người mua gửi</h4><ul className="mt-2 list-inside list-disc space-y-1 text-sm">{item.evidence_notes.map((evidence, index) => <li key={index}>{evidence}</li>)}</ul></div> : null}
      <dl className="mt-4 grid gap-3 rounded-xl bg-slate-50 p-4 text-sm sm:grid-cols-2"><div><dt className="text-slate-500">Người mua</dt><dd className="font-medium">{item.order.buyer?.full_name || item.order.buyer?.email}</dd></div><div><dt className="text-slate-500">Người bán</dt><dd className="font-medium">{item.order.seller?.full_name || item.order.seller?.email}</dd></div><div><dt className="text-slate-500">Tổng đơn</dt><dd className="font-medium">{money(item.order.total_amount)}</dd></div><div><dt className="text-slate-500">Hoa hồng ghi sổ</dt><dd className="font-medium">{money(item.order.platform_fee)}</dd></div></dl>
      {item.buyer_wallet_refund_consent_at ? <p className="mt-3 rounded-lg bg-emerald-50 p-3 text-xs text-emerald-900">Người mua đã đồng ý nhận hoàn vào số dư H-Smart lúc {new Date(item.buyer_wallet_refund_consent_at).toLocaleString('vi-VN')}.</p> : <p className="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-900">Chưa có đồng ý nhận hoàn vào số dư; không thể chọn hoàn theo cách này.</p>}
      {item.events.length > 0 && <details className="mt-3"><summary className="cursor-pointer text-xs font-semibold text-slate-600">Lịch sử xử lý ({item.events.length})</summary><ol className="mt-2 space-y-2">{item.events.map((event, index) => <li key={`${event.action}-${index}`} className="text-xs text-slate-600">{new Date(event.created_at).toLocaleString('vi-VN')} · {event.action}{event.note ? ` — ${event.note}` : ''}</li>)}</ol></details>}
      <label className="mt-4 block text-sm font-medium">Lý do/quyết định xử lý<textarea className="mt-1.5 w-full rounded-xl border border-slate-200 p-3 text-sm" rows={2} maxLength={2000} minLength={5} value={notes[item.id] ?? ''} onChange={(event) => setNotes((current) => ({ ...current, [item.id]: event.target.value }))} placeholder="Tóm tắt căn cứ xử lý và thông tin đối soát"/></label>
      <div className="mt-3 flex flex-wrap gap-2"><Button size="sm" onClick={() => void resolve(item, 'release_to_seller')} disabled={busyId === item.id}>Giải ngân vào số dư người bán</Button><Button size="sm" variant="outline" onClick={() => void resolve(item, 'manual_reconciliation')} disabled={busyId === item.id}>Tiếp tục chờ đối soát</Button><Button size="sm" variant="destructive" onClick={() => void resolve(item, 'refund_to_buyer_wallet')} disabled={busyId === item.id || !item.buyer_wallet_refund_consent_at}>Hoàn vào số dư người mua</Button></div>
    </article>)}</div>}
  </section>
}
