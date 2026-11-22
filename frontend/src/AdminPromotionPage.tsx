import { useEffect, useState } from 'react'
import { Megaphone, RefreshCw } from 'lucide-react'
import { Button } from './components/ui/button'
import { productRequest } from './productApi'

type Promotion = {
  id: number; package_type: 'boost' | 'vip'; fee_amount: number; status: string; reference: string
  starts_at: string; expires_at: string; product: { id: number; title: string; status: string } | null
  seller: { id: number; username: string; full_name: string | null; email: string } | null
}
type Props = { accessToken: string }
const money = (amount: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount)
const date = (value: string) => new Date(value).toLocaleString('vi-VN')

export default function AdminPromotionPage({ accessToken }: Props) {
  const [items, setItems] = useState<Promotion[]>([])
  const [status, setStatus] = useState<'all' | 'active' | 'scheduled' | 'expired'>('all')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  async function load() {
    setLoading(true); setError('')
    try { setItems((await productRequest<{ data: Promotion[] }>(`admin/promotions?status=${status}`, accessToken)).data.data) }
    catch (cause) { setError((cause as Error).message) }
    finally { setLoading(false) }
  }
  useEffect(() => { void load() }, [accessToken, status])

  return <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div className="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 p-5"><div><h2 className="flex items-center gap-2 font-semibold"><Megaphone size={18}/>Dịch vụ đẩy tin và VIP</h2><p className="mt-1 text-sm text-slate-500">Theo dõi khoản mô phỏng đã thu, sản phẩm và thời hạn của từng gói.</p></div><div className="flex gap-2"><select aria-label="Lọc dịch vụ" className="rounded-xl border border-slate-200 px-3 py-2 text-sm" value={status} onChange={(event) => setStatus(event.target.value as typeof status)}><option value="all">Mọi trạng thái</option><option value="active">Đang hiệu lực</option><option value="scheduled">Sắp hiệu lực</option><option value="expired">Đã hết hạn</option></select><Button size="sm" variant="outline" onClick={() => void load()} disabled={loading}><RefreshCw size={15}/>Làm mới</Button></div></div>
    {error && <p role="alert" className="m-5 rounded-xl bg-red-50 p-3 text-sm text-red-700">{error}</p>}
    {loading ? <p className="p-6 text-sm text-slate-500">Đang tải…</p> : items.length === 0 ? <p className="p-8 text-center text-sm text-slate-500">Chưa có gói ưu tiên phù hợp.</p> : <div className="divide-y divide-slate-100">{items.map((item) => { const starts = new Date(item.starts_at).getTime(); const ends = new Date(item.expires_at).getTime(); const now = Date.now(); const label = starts > now ? 'Sắp hiệu lực' : ends <= now ? 'Đã hết hạn' : item.status === 'active' ? 'Đang hiệu lực' : item.status; return <article key={item.id} className="grid gap-3 p-5 sm:grid-cols-[1fr_auto] sm:items-center"><div><div className="flex flex-wrap items-center gap-2"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${item.package_type === 'vip' ? 'bg-amber-100 text-amber-900' : 'bg-sky-100 text-sky-900'}`}>{item.package_type === 'vip' ? 'VIP · 7 ngày' : 'Đẩy tin · 3 ngày'}</span><span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs">{label}</span><span className="text-xs text-slate-400">#{item.id} · {item.reference}</span></div><h3 className="mt-2 font-semibold">{item.product?.title ?? 'Sản phẩm đã xóa'}{item.product ? ` · #${item.product.id}` : ''}</h3><p className="mt-1 text-sm text-slate-600">{item.seller?.full_name || item.seller?.username || 'Tài khoản đã xóa'} · {item.seller?.email ?? ''}</p><p className="mt-1 text-xs text-slate-500">{date(item.starts_at)} → {date(item.expires_at)}</p></div><strong className="text-sm">{money(item.fee_amount)}</strong></article> })}</div>}
  </section>
}
