import { useEffect, useState } from 'react'
import { AlertTriangle, RefreshCw } from 'lucide-react'
import { Alert } from './components/ui/alert'
import { Button } from './components/ui/button'
import { productRequest } from './productApi'

type Dispute = {
  id: number; status: string; reason: string; details: string; evidence_notes: string[] | null; resolution: string | null; resolution_note: string | null
  buyer_wallet_refund_consent_at: string | null; created_at: string; opener: { id: number; name: string } | null
  events: Array<{ action: string; note: string | null; created_at: string }>
  order: { id: number; status: string; payment_status: string | null; payment_method: string | null; payment_provider: string | null; cod_remaining_collection_type: string | null; cod_deposit_amount: number; carrier_settled_at: string | null; payout_status: string; product_amount: number; shipping_fee: number; platform_fee: number; total_amount: number; buyer: { id: number; username: string; full_name: string | null; email: string } | null; seller: { id: number; username: string; full_name: string | null; email: string } | null; product: { id: number; title: string; status: string } | null }
}
type Props = { accessToken: string }
const money = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
const providerLabels: Record<string, string> = { vnpay: 'VNPay Sandbox', vnpay_mock: 'Thanh toán dữ liệu demo', cod: 'COD', vietqr: 'VietQR (lịch sử)' }
const payoutLabels: Record<string, string> = { awaiting_dispute_window: 'Trong thời hạn khiếu nại', awaiting_carrier_settlement: 'Chờ đối soát COD', eligible: 'Chờ quản trị viên giải ngân', held: 'Đang tạm giữ do khiếu nại', credited_to_wallet: 'Đã giải ngân vào ví', not_ready: 'Chưa đủ điều kiện', refunded: 'Đã hoàn tiền' }
const eventLabels: Record<string, string> = { release_to_seller: 'Giải ngân cho người bán', refund_to_buyer_wallet: 'Hoàn vào ví người mua', manual_reconciliation: 'Tiếp tục chờ đối soát' }

export default function AdminDisputePage({ accessToken }: Props) {
  const [items, setItems] = useState<Dispute[]>([])
  const [notes, setNotes] = useState<Record<number, string>>({})
  const [loading, setLoading] = useState(true)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [view, setView] = useState<'open' | 'resolved'>('open')

  async function load() {
    setLoading(true); setError('')
      try { setItems((await productRequest<{ content: Dispute[] }>(`admin/disputes?status=${view}`, accessToken)).data.content) }
    catch (cause) { setError((cause as Error).message) }
    finally { setLoading(false) }
  }
  useEffect(() => { void load() }, [accessToken, view])

  async function resolve(dispute: Dispute, resolution: 'release_to_seller' | 'refund_to_buyer_wallet' | 'manual_reconciliation') {
    const note = (notes[dispute.id] ?? '').trim()
    if (note.length < 5) { setError('Nhập lý do xử lý tối thiểu 5 ký tự.'); return }
    if (resolution === 'refund_to_buyer_wallet' && !window.confirm('Người mua đã đồng ý nhận hoàn vào số dư. Xác nhận ghi bút toán hoàn tiền vào ví người mua?')) return
    setBusyId(dispute.id); setError(''); setNotice('')
    try {
      const result = await productRequest<Dispute>(`admin/disputes/${dispute.id}/resolve`, accessToken, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ resolution, note }) })
      setNotice(result.message); await load()
    } catch (cause) { setError((cause as Error).message) }
    finally { setBusyId(null) }
  }

  return <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div className="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 p-5"><div><h2 className="flex items-center gap-2 font-semibold"><AlertTriangle size={18}/>Khiếu nại đơn hàng</h2><p className="mt-1 text-sm text-slate-500">Đơn đang khiếu nại bị giữ. Hoàn vào số dư chỉ bật sau khi người mua chủ động đồng ý.</p><div className="mt-3 flex gap-2"><Button size="sm" variant={view === 'open' ? 'default' : 'outline'} onClick={() => setView('open')}>Đang xử lý</Button><Button size="sm" variant={view === 'resolved' ? 'default' : 'outline'} onClick={() => setView('resolved')}>Lịch sử đã xử lý</Button></div></div><Button size="sm" variant="outline" onClick={() => void load()} disabled={loading}><RefreshCw size={15}/>Làm mới</Button></div>
    {error && <Alert variant="error" className="m-5">{error}</Alert>}{notice && <Alert variant="success" className="m-5">{notice}</Alert>}
    {loading ? <p className="p-6 text-sm text-slate-500">Đang tải…</p> : items.length === 0 ? <p className="p-8 text-center text-sm text-slate-500">{view === 'open' ? 'Không có khiếu nại đang mở.' : 'Chưa có khiếu nại đã xử lý.'}</p> : <div className="divide-y divide-slate-100">{items.map((item) => <article key={item.id} className="p-5 sm:p-6">
      <div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-xs text-slate-500">Khiếu nại #{item.id} · Đơn #{item.order.id} · {new Date(item.created_at).toLocaleString('vi-VN')}</p><h3 className="mt-1 text-lg font-semibold">{item.reason}</h3><p className="mt-1 text-sm text-slate-600">{item.order.product?.title ?? 'Sản phẩm'} · {item.order.payment_method?.toUpperCase() ?? 'Thanh toán'}{item.order.payment_provider ? ` / ${providerLabels[item.order.payment_provider] ?? 'Thanh toán'}` : ''} · {payoutLabels[item.order.payout_status] ?? 'Đang được kiểm tra'}</p></div><span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">{item.status === 'open' ? 'Đang xử lý' : item.status === 'reconciliation_pending' ? 'Chờ đối soát' : 'Đã xử lý'}</span></div>
      <p className="mt-4 whitespace-pre-wrap text-sm leading-6 text-slate-700">{item.details}</p>
      {item.evidence_notes?.length ? <div className="mt-3 rounded-xl bg-slate-50 p-3"><h4 className="text-xs font-semibold uppercase text-slate-500">Ghi chú/bằng chứng người mua gửi</h4><ul className="mt-2 list-inside list-disc space-y-1 text-sm">{item.evidence_notes.map((evidence, index) => <li key={index}>{evidence}</li>)}</ul></div> : null}
      <dl className="mt-4 grid gap-3 rounded-xl bg-slate-50 p-4 text-sm sm:grid-cols-2"><div><dt className="text-slate-500">Người mua</dt><dd className="font-medium">{item.order.buyer?.full_name || item.order.buyer?.email}</dd></div><div><dt className="text-slate-500">Người bán</dt><dd className="font-medium">{item.order.seller?.full_name || item.order.seller?.email}</dd></div><div><dt className="text-slate-500">Tổng đơn</dt><dd className="font-medium">{money(item.order.total_amount)}</dd></div><div><dt className="text-slate-500">Hoa hồng ghi sổ</dt><dd className="font-medium">{money(item.order.platform_fee)}</dd></div></dl>
      {item.buyer_wallet_refund_consent_at ? <p className="mt-3 rounded-lg bg-emerald-50 p-3 text-xs text-emerald-900">Người mua đã đồng ý nhận hoàn vào số dư H-Smart lúc {new Date(item.buyer_wallet_refund_consent_at).toLocaleString('vi-VN')}.</p> : <p className="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-900">Chưa có đồng ý nhận hoàn vào số dư; không thể chọn hoàn theo cách này.</p>}
      {item.events.length > 0 && <details className="mt-3"><summary className="cursor-pointer text-xs font-semibold text-slate-600">Lịch sử xử lý ({item.events.length})</summary><ol className="mt-2 space-y-2">{item.events.map((event, index) => <li key={`${event.action}-${index}`} className="text-xs text-slate-600">{new Date(event.created_at).toLocaleString('vi-VN')} · {eventLabels[event.action] ?? 'Cập nhật khiếu nại'}{event.note ? ` — ${event.note}` : ''}</li>)}</ol></details>}
      {item.status !== 'resolved' && <><label className="mt-4 block text-sm font-medium">Lý do/quyết định xử lý<textarea className="mt-1.5 w-full rounded-xl border border-slate-200 p-3 text-sm" rows={2} maxLength={2000} minLength={5} value={notes[item.id] ?? ''} onChange={(event) => setNotes((current) => ({ ...current, [item.id]: event.target.value }))} placeholder="Tóm tắt căn cứ xử lý và thông tin đối soát"/></label>
      <div className="mt-3 flex flex-wrap gap-2"><Button size="sm" onClick={() => void resolve(item, 'release_to_seller')} disabled={busyId === item.id || item.order.payment_status !== 'paid' || (item.order.payment_method === 'cod' && item.order.cod_remaining_collection_type !== 'direct' && !item.order.carrier_settled_at)}>Giải ngân vào số dư người bán</Button><Button size="sm" variant="outline" onClick={() => void resolve(item, 'manual_reconciliation')} disabled={busyId === item.id}>Tiếp tục chờ đối soát</Button><Button size="sm" variant="destructive" onClick={() => void resolve(item, 'refund_to_buyer_wallet')} disabled={busyId === item.id || !item.buyer_wallet_refund_consent_at || item.order.payment_status !== 'paid' || (item.order.payment_method === 'cod' && item.order.cod_deposit_amount <= 0) || (item.order.payment_method === 'cod' && item.order.cod_remaining_collection_type !== 'direct' && !item.order.carrier_settled_at)}>Hoàn vào số dư người mua</Button></div>
      {item.order.payment_status !== 'paid' ? <p className="mt-2 text-xs text-amber-800">Chưa có xác nhận thu đủ tiền; cần hoàn tất thanh toán/đối soát trước khi giải ngân hoặc hoàn tiền.</p> : item.order.payment_method === 'cod' && item.order.cod_deposit_amount <= 0 ? <p className="mt-2 text-xs text-amber-800">Đơn COD không có tiền cọc do sàn giữ; không thể hoàn từ số dư H-Smart. Cần tiếp tục đối soát thủ công.</p> : item.order.payment_method === 'cod' && item.order.cod_remaining_collection_type !== 'direct' && !item.order.carrier_settled_at ? <p className="mt-2 text-xs text-amber-800">Đơn COD chờ đơn vị giao nhận đối soát; có thể tiếp tục chờ đối soát nhưng chưa thể giải ngân hoặc hoàn tiền.</p> : null}</>}
    </article>)}</div>}
  </section>
}
