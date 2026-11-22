import { useEffect, useState } from 'react'
import { Eye, RefreshCw, Wallet } from 'lucide-react'
import { Alert } from './components/ui/alert'
import { Button } from './components/ui/button'
import { productRequest } from './productApi'

type Withdrawal = { id: number; user_id: number; username: string | null; email: string | null; amount: number; status: string; reference: string; decision_reason: string | null; created_at: string | null; reviewed_at: string | null; paid_at: string | null }
type Details = Withdrawal & { bank_details: { bank_name: string; account_holder: string; account_number: string } }
type Props = { accessToken: string }
const money = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)
const labels: Record<string, string> = { pending_review: 'Chờ duyệt', processing: 'Đang xử lý', reconciliation_pending: 'Chờ đối soát', rejected: 'Đã từ chối', succeeded_simulated: 'Hoàn tất mô phỏng' }

export default function AdminWithdrawalPage({ accessToken }: Props) {
  const [items, setItems] = useState<Withdrawal[]>([])
  const [detail, setDetail] = useState<Details | null>(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState<number | null>(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  async function load() {
    setLoading(true); setError('')
    try { setItems((await productRequest<{ content: Withdrawal[] }>('admin/withdrawals', accessToken)).data.content) }
    catch (cause) { setError((cause as Error).message) }
    finally { setLoading(false) }
  }
  useEffect(() => { void load() }, [accessToken])

  async function inspect(item: Withdrawal) {
    setError('')
    try { setDetail((await productRequest<Details>(`admin/withdrawals/${item.id}`, accessToken)).data) }
    catch (cause) { setError((cause as Error).message) }
  }

  async function act(item: Withdrawal, action: 'processing' | 'reconciliation' | 'reject' | 'simulate-paid') {
    const reason = action === 'reject' ? window.prompt('Lý do từ chối yêu cầu (5–1000 ký tự):')?.trim() : undefined
    if (action === 'reject' && (!reason || reason.length < 5)) return
    setBusy(item.id); setError(''); setNotice('')
    try {
      const result = await productRequest<Withdrawal>(`admin/withdrawals/${item.id}/${action}`, accessToken, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: action === 'reject' ? JSON.stringify({ reason }) : undefined })
      setNotice(result.message); setItems((current) => current.map((entry) => entry.id === item.id ? { ...entry, ...result.data } : entry))
      if (detail?.id === item.id) setDetail((current) => current ? { ...current, ...result.data } : current)
      await load()
    } catch (cause) { setError((cause as Error).message) }
    finally { setBusy(null) }
  }

  return <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div className="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 p-5"><div><h2 className="flex items-center gap-2 font-semibold"><Wallet size={18}/>Yêu cầu rút tiền</h2><p className="mt-1 text-sm text-slate-500">Mọi khoản chi tại đây chỉ được đánh dấu mô phỏng. Trạng thái chờ đối soát tiếp tục giữ số dư.</p></div><Button size="sm" variant="outline" onClick={() => void load()} disabled={loading}><RefreshCw size={15}/>Làm mới</Button></div>
    {error && <Alert variant="error" className="m-5">{error}</Alert>}{notice && <Alert variant="success" className="m-5">{notice}</Alert>}
    {loading ? <p className="p-6 text-sm text-slate-500">Đang tải…</p> : items.length === 0 ? <p className="p-8 text-center text-sm text-slate-500">Chưa có yêu cầu rút.</p> : <div className="divide-y divide-slate-100">{items.map((item) => <article key={item.id} className="flex flex-wrap items-center justify-between gap-4 p-5"><div><div className="flex flex-wrap items-center gap-2"><span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{labels[item.status] ?? item.status}</span><span className="text-xs text-slate-400">#{item.id} · {item.reference}</span></div><p className="mt-2 text-sm font-medium">{item.username ?? 'Thành viên'} · {item.email ?? `Tài khoản #${item.user_id}`}</p><p className="mt-1 text-sm font-bold">{money(item.amount)}</p></div><div className="flex flex-wrap gap-2"><Button size="sm" variant="outline" onClick={() => void inspect(item)}><Eye size={14}/>Thông tin nhận</Button>{item.status === 'pending_review' && <><Button size="sm" onClick={() => void act(item, 'processing')} disabled={busy === item.id}>Bắt đầu xử lý</Button><Button size="sm" variant="destructive" onClick={() => void act(item, 'reject')} disabled={busy === item.id}>Từ chối và trả số dư</Button></>}{item.status === 'processing' && <Button size="sm" variant="outline" onClick={() => void act(item, 'reconciliation')} disabled={busy === item.id}>Chờ đối soát</Button>}{['processing', 'reconciliation_pending'].includes(item.status) && <Button size="sm" onClick={() => void act(item, 'simulate-paid')} disabled={busy === item.id}>Ghi nhận mô phỏng</Button>}</div></article>)}</div>}
    {detail && <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setDetail(null)}><section role="dialog" aria-modal="true" aria-labelledby="withdrawal-details-title" className="w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl" onClick={(event) => event.stopPropagation()}><h2 id="withdrawal-details-title" className="text-lg font-bold">Thông tin rút #{detail.id}</h2><p className="mt-1 text-xs text-slate-500">Lượt xem thông tin nhận tiền đã ghi vào nhật ký quản trị.</p><dl className="mt-4 grid gap-3 text-sm"><div><dt className="text-slate-500">Ngân hàng</dt><dd className="font-medium">{detail.bank_details.bank_name}</dd></div><div><dt className="text-slate-500">Chủ tài khoản</dt><dd className="font-medium">{detail.bank_details.account_holder}</dd></div><div><dt className="text-slate-500">Số tài khoản</dt><dd className="font-mono font-medium">{detail.bank_details.account_number}</dd></div></dl><div className="mt-5 flex justify-end"><Button variant="outline" onClick={() => setDetail(null)}>Đóng</Button></div></section></div>}
  </section>
}
