import { useCallback, useEffect, useState } from 'react'
import { AlertCircle, ChevronLeft, ChevronRight, RefreshCw } from 'lucide-react'
import { productRequest } from './productApi'
import { Alert } from './components/ui/alert'
import { Button } from './components/ui/button'

type Period = { preset: string; from: string; to: string; timezone: string; grain: string }
type Metric = Record<string, number>
type Point = { period: string; sales_net?: number; refunds?: number; credited_to_wallet?: number; commission?: number; listing_fees?: number; promotions?: number; net?: number }
type Detail = { occurred_at?: string; posted_at?: string; event_type?: string; type?: string; related_order_id?: number | null; source_id?: number | null; reference?: string | null; status?: string; amount: number; description?: string; source_type?: string; transaction_id?: number; metadata?: Record<string, unknown> | null }
type Report = { period: Period; metrics: Metric; series: Point[]; details: { content: Detail[]; page: number; total_pages: number; total: number }; mode?: string; definitions?: Record<string, string> }
type Props = { accessToken: string; admin?: boolean; navigate?: (path: string) => void }

const money = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(value || 0)
const dateTime = (value?: string) => value ? new Date(value).toLocaleString('vi-VN') : '—'
const metricNames: Record<string, string> = {
  completed_product_amount: 'Tiền hàng đơn hoàn tất', commission_amount: 'Hoa hồng', refund_amount: 'Đảo tiền thuần người bán',
  net_sales_revenue: 'Doanh thu thuần bán hàng', pending_release_balance: 'Đang chờ giải ngân', credited_to_wallet: 'Đã ghi có ví trong kỳ',
  direct_cod_received: 'COD người bán thu trực tiếp', completed_orders: 'Đơn hoàn tất', cancelled_orders: 'Đơn hủy', refunded_orders: 'Đơn có hoàn tiền',
  paid_goods_value: 'Giá trị tiền hàng đã thanh toán', paid_orders: 'Đơn đã thanh toán', commission_revenue: 'Doanh thu hoa hồng',
  listing_fee_revenue: 'Phí đăng tin đã thu', promotion_revenue: 'Phí VIP/đẩy tin đã thu', revenue_reversals: 'Đảo doanh thu',
  net_platform_revenue: 'Doanh thu sàn thuần', held_for_sellers: 'Tiền đang giữ cho người bán', user_wallet_liabilities: 'Nghĩa vụ số dư ví',
  pending_withdrawals: 'Tiền chờ rút', wallet_deposits_requiring_reconciliation: 'Khoản nạp chờ đối soát', wallet_deposit_reconciliation_amount: 'Giá trị nạp chờ đối soát',
}
const colors: Record<string, string> = { sales_net: '#2563eb', refunds: '#dc2626', credited_to_wallet: '#16a34a', commission: '#2563eb', listing_fees: '#7c3aed', promotions: '#0891b2', net: '#16a34a' }

function TrendChart({ points, admin }: { points: Point[]; admin: boolean }) {
  const keys = admin ? ['commission', 'listing_fees', 'promotions', 'refunds'] : ['sales_net', 'refunds', 'credited_to_wallet']
  if (!points.length || !keys.some((key) => points.some((point) => Number(point[key as keyof Point] ?? 0) !== 0))) {
    return <p className="py-12 text-center text-sm text-slate-500">Chưa có giao dịch trong khoảng thời gian này.</p>
  }
  const width = 840; const height = 230; const inset = 20
  const values = points.flatMap((point) => keys.map((key) => Number(point[key as keyof Point] ?? 0)))
  const min = Math.min(0, ...values); const max = Math.max(1, ...values)
  const y = (value: number) => inset + (height - inset * 2) * (1 - (value - min) / (max - min || 1))
  const x = (index: number) => inset + (points.length < 2 ? (width - inset * 2) / 2 : index * (width - inset * 2) / (points.length - 1))
  return <div className="overflow-x-auto"><svg className="h-64 min-w-[680px] w-full" viewBox={`0 0 ${width} ${height}`} role="img" aria-label="Biểu đồ doanh thu theo ngày hoặc tháng">
    {[0, 0.5, 1].map((ratio) => { const lineY = inset + (height - inset * 2) * ratio; return <line key={ratio} x1={inset} x2={width - inset} y1={lineY} y2={lineY} stroke="#e2e8f0" strokeDasharray="4 4" /> })}
    {keys.map((key) => <polyline key={key} fill="none" stroke={colors[key]} strokeWidth="3" strokeLinejoin="round" strokeLinecap="round" points={points.map((point, index) => `${x(index)},${y(Number(point[key as keyof Point] ?? 0))}`).join(' ')} />)}
    <text x={inset} y={height - 2} fontSize="11" fill="#64748b">{points[0]?.period}</text><text x={width - inset} y={height - 2} textAnchor="end" fontSize="11" fill="#64748b">{points.at(-1)?.period}</text>
  </svg><div className="flex flex-wrap gap-x-5 gap-y-2 px-2 pb-3 text-xs text-slate-600">{keys.map((key) => <span className="inline-flex items-center gap-2" key={key}><i className="h-2.5 w-2.5 rounded-full" style={{ background: colors[key] }} />{key === 'sales_net' ? 'Doanh thu thuần bán hàng' : key === 'credited_to_wallet' ? 'Ghi có ví' : key === 'listing_fees' ? 'Phí đăng tin' : key === 'promotions' ? 'VIP/đẩy tin' : key === 'commission' ? 'Hoa hồng' : 'Đảo/hoàn tiền'}</span>)}</div></div>
}

export default function RevenuePage({ accessToken, admin = false, navigate }: Props) {
  const [preset, setPreset] = useState<'this_month' | 'last_month' | 'custom'>('this_month')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [page, setPage] = useState(1)
  const [report, setReport] = useState<Report | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const endpoint = admin ? 'admin/revenue' : 'me/wallet/revenue'

  const load = useCallback(async () => {
    setLoading(true); setError('')
    const params = new URLSearchParams({ period: preset, page: String(page) })
    if (preset === 'custom') { params.set('from', from); params.set('to', to) }
    try { setReport((await productRequest<Report>(`${endpoint}?${params.toString()}`, accessToken)).data) }
    catch (cause) { setReport(null); setError((cause as Error).message) }
    finally { setLoading(false) }
  }, [accessToken, endpoint, from, page, preset, to])

  useEffect(() => { if (preset !== 'custom' || (from && to)) void load() }, [from, load, preset, to])

  const changePeriod = (value: 'this_month' | 'last_month' | 'custom') => { setPage(1); setPreset(value) }
  const detailNames: Record<string, string> = {
    order: 'Đơn hàng', order_proceeds_released: 'Giải ngân vào ví', order_refund_to_wallet: 'Hoàn tiền cho người mua',
    cod_deposit_refunded: 'Hoàn cọc COD', order_escrow_received: 'Thanh toán đơn', cod_deposit_received: 'Nhận tiền cọc COD',
    listing_fee: 'Phí đăng tin', product_promotion: 'Gói ưu tiên tin', vnpay_sandbox_deposit: 'Nạp ví qua VNPay Sandbox',
    withdrawal_hold: 'Yêu cầu rút tiền', withdrawal_cancelled: 'Hủy yêu cầu rút tiền', withdrawal_rejected: 'Yêu cầu rút bị từ chối',
    withdrawal_failed: 'Yêu cầu rút thất bại', withdrawal_paid_manual: 'Chi trả cho người bán',
  }
  const detailType = (detail: Detail) => {
    const value = detail.event_type ?? detail.type ?? ''
    return detailNames[value] ?? 'Giao dịch tài chính'
  }
  const detailDescription = (detail: Detail) => {
    if (detail.description) return detailNames[detail.description] ?? (/^[a-z][a-z0-9_]*$/.test(detail.description) ? 'Giao dịch trong sổ' : detail.description)
    if (detail.source_type) return detailNames[detail.source_type] ?? 'Giao dịch trong sổ'
    return detail.reference ? `Mã tham chiếu ${detail.reference}` : 'Giao dịch trong sổ'
  }

  return <main className="mx-auto w-full max-w-6xl space-y-5 p-4 sm:p-6">
    <header className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{admin ? 'Quản trị tài chính' : 'Khu vực người bán'}</p><h1 className="mt-1 text-2xl font-bold">{admin ? 'Doanh thu sàn' : 'Doanh thu bán hàng'}</h1><p className="mt-1 text-sm text-slate-600">Mốc thời gian theo Asia/Ho_Chi_Minh. Nạp/rút ví không phải doanh thu bán hàng.</p></div><Button variant="outline" size="sm" onClick={() => void load()} disabled={loading}><RefreshCw size={15} />Làm mới</Button></header>
    {admin && <Alert className="border-blue-200 bg-blue-50 text-blue-900">{report?.mode ?? 'Báo cáo từ sổ giao dịch H-Smart; thanh toán VNPay sử dụng Sandbox.'}</Alert>}
    <section className="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4">
      <div className="flex flex-wrap gap-2"><Button size="sm" variant={preset === 'this_month' ? 'default' : 'outline'} onClick={() => changePeriod('this_month')}>Tháng này</Button><Button size="sm" variant={preset === 'last_month' ? 'default' : 'outline'} onClick={() => changePeriod('last_month')}>Tháng trước</Button><Button size="sm" variant={preset === 'custom' ? 'default' : 'outline'} onClick={() => changePeriod('custom')}>Khoảng ngày</Button></div>
      {preset === 'custom' && <><label className="text-xs text-slate-600">Từ<input className="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm" type="date" value={from} onChange={(event) => { setPage(1); setFrom(event.target.value) }} /></label><label className="text-xs text-slate-600">Đến<input className="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm" type="date" value={to} onChange={(event) => { setPage(1); setTo(event.target.value) }} /></label></>}
      {report && <p className="ml-auto text-xs text-slate-500">{report.period.from} – {report.period.to} · {report.period.timezone}</p>}
    </section>
    {error && <Alert variant="error" className="flex items-center gap-2"><AlertCircle size={16} />{error}<Button className="ml-auto" size="sm" variant="outline" onClick={() => void load()}>Thử lại</Button></Alert>}
    {loading && !report ? <div className="rounded-2xl border border-slate-200 bg-white p-8 text-sm text-slate-500" role="status">Đang tải báo cáo…</div> : report && <>
      <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">{Object.entries(report.metrics).map(([key, value]) => <article key={key} className="rounded-2xl border border-slate-200 bg-white p-4"><p className="text-xs leading-5 text-slate-500">{metricNames[key] ?? key.replaceAll('_', ' ')}</p><p className="mt-2 text-lg font-bold">{key.endsWith('_orders') || key === 'paid_orders' || key.includes('reconciliation') && !key.includes('amount') ? new Intl.NumberFormat('vi-VN').format(value) : money(value)}</p></article>)}</section>
      <section className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5"><h2 className="font-semibold">Xu hướng {report.period.grain === 'month' ? 'theo tháng' : 'theo ngày'}</h2>{loading ? <p className="py-10 text-center text-sm text-slate-500">Đang cập nhật…</p> : <TrendChart points={report.series} admin={admin} />}</section>
      {report.definitions && <details className="rounded-2xl border border-slate-200 bg-white p-4"><summary className="cursor-pointer text-sm font-semibold">Cách tính các chỉ số</summary><dl className="mt-3 grid gap-3 text-xs leading-5 text-slate-600 sm:grid-cols-2">{Object.entries(report.definitions).map(([key, definition]) => <div key={key}><dt className="font-semibold text-slate-800">{metricNames[key] ?? key}</dt><dd>{definition}</dd></div>)}</dl></details>}
      <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white"><div className="border-b border-slate-200 p-4"><h2 className="font-semibold">Chi tiết đơn và bút toán</h2><p className="mt-1 text-xs text-slate-500">{report.details.total} mục trong kỳ đã chọn.</p></div>{report.details.content.length === 0 ? <p className="p-8 text-center text-sm text-slate-500">Chưa có dữ liệu trong kỳ này.</p> : <><div className="divide-y divide-slate-100">{report.details.content.map((detail, index) => { const relatedId = detail.related_order_id ?? detail.source_id; return <article key={`${detail.reference ?? detail.transaction_id ?? relatedId}-${index}`} className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"><div className="min-w-0"><p className="font-medium">{detailType(detail)}{relatedId ? ` · #${relatedId}` : ''}</p><p className="mt-1 truncate text-xs text-slate-500">{detailDescription(detail)}{detail.reference ? ` · ${detail.reference}` : ''} · {dateTime(detail.occurred_at ?? detail.posted_at)}</p></div><div className={`shrink-0 font-semibold ${detail.amount < 0 ? 'text-red-700' : 'text-slate-900'}`}>{money(detail.amount)}</div>{!admin && relatedId && navigate && <Button size="sm" variant="outline" onClick={() => navigate(`/me/orders?order=${relatedId}`)}>Mở đơn</Button>}</article> })}</div><div className="flex items-center justify-between gap-3 border-t border-slate-200 p-3"><Button size="sm" variant="outline" disabled={report.details.page <= 1 || loading} onClick={() => setPage((current) => current - 1)}><ChevronLeft size={16} />Trước</Button><span className="text-xs text-slate-500">Trang {report.details.page} / {Math.max(1, report.details.total_pages)}</span><Button size="sm" variant="outline" disabled={report.details.page >= report.details.total_pages || loading} onClick={() => setPage((current) => current + 1)}>Sau<ChevronRight size={16} /></Button></div></>}</section>
    </>}
  </main>
}
