import { useCallback, useEffect, useState } from 'react'
import { Activity, AlertTriangle, ArrowLeft, Boxes, ChevronRight, ClipboardList, LayoutDashboard, Package, Search, Shield, ShoppingCart, Users } from 'lucide-react'
import * as api from './adminApi'
import AdminManagementPage from './AdminManagementPage'
import { Button } from './components/ui/button'

type Props = { accessToken: string; onBack: () => void }
type Section = 'overview' | 'reports' | 'orders' | 'audit' | 'management'
const number = (value: number) => new Intl.NumberFormat('vi-VN').format(value)
const date = (value: string) => new Date(value).toLocaleString('vi-VN', { dateStyle: 'medium', timeStyle: 'short' })
const statusNames: Record<string, string> = { pending_payment: 'Chờ thanh toán', paid: 'Đã thanh toán', processing: 'Đang xử lý', shipping: 'Đang giao', completed: 'Hoàn tất', cancelled: 'Đã hủy', pending: 'Chờ xử lý', actioned: 'Đã xử lý', dismissed: 'Đã bỏ qua' }

export default function AdminPage({ accessToken, onBack }: Props) {
  const [section, setSection] = useState<Section>('overview')
  const [stats, setStats] = useState<api.AdminStats | null>(null)
  const [reports, setReports] = useState<api.AdminReport[]>([])
  const [orders, setOrders] = useState<api.AdminOrder[]>([])
  const [logs, setLogs] = useState<api.AdminAuditLog[]>([])
  const [query, setQuery] = useState('')
  const [orderStatus, setOrderStatus] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [busyReport, setBusyReport] = useState<number | null>(null)
  const menu: { id: Section; label: string; Icon: typeof LayoutDashboard }[] = [
    { id: 'overview', label: 'Tổng quan', Icon: LayoutDashboard },
    { id: 'reports', label: 'Báo cáo vi phạm', Icon: AlertTriangle },
    { id: 'orders', label: 'Giao dịch', Icon: ShoppingCart },
    { id: 'audit', label: 'Lịch sử thao tác', Icon: Activity },
    { id: 'management', label: 'Quản lý hệ thống', Icon: Boxes },
  ]

  const load = useCallback(async () => {
    setLoading(true); setError('')
    try {
      if (section === 'overview') setStats(await api.getAdminStats(accessToken))
      if (section === 'reports') setReports((await api.getAdminReports(accessToken)).data)
      if (section === 'orders') setOrders((await api.getAdminOrders(accessToken, query, orderStatus)).data)
      if (section === 'audit') setLogs((await api.getAdminAuditLogs(accessToken)).data)
    } catch (cause) { setError((cause as Error).message) }
    finally { setLoading(false) }
  }, [accessToken, orderStatus, query, section])
  useEffect(() => { if (section !== 'management') void load() }, [load, section])

  async function actOnReport(report: api.AdminReport, action: 'hide_product' | 'suspend_user' | 'dismiss') {
    setBusyReport(report.id); setError('')
    try { await api.resolveReport(accessToken, report.id, action); setReports((items) => items.filter((item) => item.id !== report.id)); setStats((current) => current ? { ...current, pending_reports: Math.max(0, current.pending_reports - 1) } : current) }
    catch (cause) { setError((cause as Error).message) }
    finally { setBusyReport(null) }
  }

  const cards = stats ? [
    { label: 'Người dùng', value: stats.users, Icon: Users, tone: 'bg-blue-50 text-blue-700' },
    { label: 'Tin đăng', value: stats.products, Icon: Package, tone: 'bg-violet-50 text-violet-700' },
    { label: 'Đơn hàng', value: stats.orders, Icon: ShoppingCart, tone: 'bg-emerald-50 text-emerald-700' },
    { label: 'Sản phẩm đã ẩn', value: stats.hidden_products, Icon: Shield, tone: 'bg-amber-50 text-amber-700' },
  ] : []

  return <div className="min-h-screen bg-[#f5f7fb] text-slate-900 lg:flex">
    <aside className="flex w-full shrink-0 flex-col bg-[#111827] text-white lg:sticky lg:top-0 lg:h-screen lg:w-[260px]">
      <div className="flex items-center gap-3 border-b border-white/10 px-6 py-6"><span className="grid h-10 w-10 place-items-center rounded-xl bg-orange-500"><Shield size={21} /></span><div><p className="font-bold">H-Smart</p><p className="text-xs text-slate-400">Quản trị hệ thống</p></div></div>
      <nav className="flex gap-1 overflow-x-auto p-3 lg:flex-1 lg:flex-col" aria-label="Điều hướng quản trị">
        {menu.map(({ id, label, Icon }) => <button key={id} onClick={() => setSection(id)} className={`flex shrink-0 items-center gap-3 rounded-xl px-4 py-3 text-left text-sm transition ${section === id ? 'bg-orange-500 font-semibold text-white' : 'text-slate-300 hover:bg-white/10 hover:text-white'}`}><Icon size={18} />{label}{id === 'reports' && stats?.pending_reports ? <span className="ml-auto rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-orange-600">{stats.pending_reports}</span> : null}</button>)}
      </nav>
      <button onClick={onBack} className="hidden items-center gap-3 border-t border-white/10 px-6 py-5 text-sm text-slate-300 hover:text-white lg:flex"><ArrowLeft size={17} />Về trang chủ</button>
    </aside>
    <main className="min-w-0 flex-1">
      <header className="flex items-center justify-between border-b border-slate-200 bg-white px-5 py-4 sm:px-8"><div><p className="text-xs font-medium uppercase tracking-wider text-slate-400">Không gian quản trị</p><h1 className="mt-1 text-xl font-bold">{menu.find((item) => item.id === section)?.label}</h1></div><Button size="sm" variant="outline" className="lg:hidden" onClick={onBack}>Trang chủ</Button><span className="hidden items-center gap-2 rounded-full bg-slate-100 px-3 py-2 text-xs text-slate-600 sm:flex"><span className="h-2 w-2 rounded-full bg-emerald-500" />Quản trị viên</span></header>
      <div className="mx-auto max-w-[1440px] p-5 sm:p-8">
        {error && <div role="alert" className="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{error}</div>}
        {section === 'overview' && <>
          <div className="mb-7"><h2 className="text-2xl font-bold">Tổng quan</h2><p className="mt-1 text-sm text-slate-500">Theo dõi hoạt động chính của H-Smart.</p></div>
          {loading ? <p className="py-8 text-sm text-slate-500">Đang tải thống kê…</p> : <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{cards.map(({ label, value, Icon, tone }) => <article key={label} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div className="flex items-center justify-between"><span className="text-sm text-slate-500">{label}</span><span className={`grid h-10 w-10 place-items-center rounded-xl ${tone}`}><Icon size={19} /></span></div><p className="mt-4 text-3xl font-bold">{number(value)}</p></article>)}</div>}
          <button onClick={() => setSection('reports')} className="mt-6 flex w-full items-center justify-between rounded-2xl border border-orange-200 bg-orange-50 p-5 text-left hover:bg-orange-100"><span className="flex items-center gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-white text-orange-600"><AlertTriangle size={19} /></span><span><strong className="block">Báo cáo cần xem</strong><span className="text-sm text-slate-600">{number(stats?.pending_reports ?? 0)} báo cáo đang chờ xử lý</span></span></span><ChevronRight size={20} /></button>
          <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-5"><h3 className="font-semibold">Lối tắt quản trị</h3><div className="mt-4 grid gap-3 sm:grid-cols-3">{menu.slice(1).map(({ id, label, Icon }) => <button key={id} onClick={() => setSection(id)} className="flex items-center gap-3 rounded-xl border border-slate-200 p-4 text-left hover:border-orange-300 hover:bg-orange-50"><Icon size={19} className="text-slate-500" /><span className="text-sm font-medium">{label}</span></button>)}</div></section>
        </>}
        {section === 'reports' && <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div className="border-b border-slate-200 p-5"><h2 className="font-semibold">Báo cáo đang chờ xử lý</h2><p className="mt-1 text-sm text-slate-500">Xem nội dung, lý do và người gửi trước khi thực hiện.</p></div>{loading ? <p className="p-6 text-sm text-slate-500">Đang tải…</p> : reports.length === 0 ? <p className="p-8 text-center text-sm text-slate-500">Không có báo cáo đang chờ.</p> : <div className="divide-y divide-slate-100">{reports.map((report) => { const target = report.target_type === 'product' ? report.product?.title ?? `Sản phẩm #${report.target_id}` : report.reported_user?.email ?? `Tài khoản #${report.target_id}`; return <article key={report.id} className="p-5"><div className="flex flex-wrap items-start justify-between gap-4"><div className="min-w-[220px] flex-1"><div className="flex flex-wrap items-center gap-2"><span className="rounded-full bg-orange-50 px-2.5 py-1 text-xs font-semibold text-orange-700">{report.target_type === 'product' ? 'Sản phẩm' : 'Tài khoản'}</span><span className="text-xs text-slate-400">Báo cáo #{report.id}</span></div><h3 className="mt-3 font-semibold">{target}</h3><p className="mt-1 text-sm text-slate-700">{report.reason}</p>{report.details && <p className="mt-2 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">{report.details}</p>}<p className="mt-3 text-xs text-slate-500">Người gửi: {report.reporter?.full_name || report.reporter?.username} · {report.reporter?.email} · {date(report.created_at)}</p></div><div className="flex flex-wrap gap-2">{report.target_type === 'product' && <Button size="sm" variant="destructive" disabled={busyReport === report.id} onClick={() => void actOnReport(report, 'hide_product')}>Ẩn sản phẩm</Button>}{report.target_type === 'user' && <Button size="sm" variant="destructive" disabled={busyReport === report.id} onClick={() => void actOnReport(report, 'suspend_user')}>Khóa tài khoản</Button>}<Button size="sm" variant="outline" disabled={busyReport === report.id} onClick={() => void actOnReport(report, 'dismiss')}>Bỏ qua</Button></div></div></article> })}</div>}</section>}
        {section === 'orders' && <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div className="flex flex-col gap-4 border-b border-slate-200 p-5 md:flex-row md:items-end md:justify-between"><div><h2 className="font-semibold">Tra cứu giao dịch</h2><p className="mt-1 text-sm text-slate-500">Tìm theo mã đơn, mã vận đơn, sản phẩm hoặc email.</p></div><div className="flex flex-col gap-2 sm:flex-row"><label className="relative"><Search size={16} className="absolute left-3 top-3 text-slate-400" /><input className="rounded-xl border border-slate-200 py-2.5 pl-9 pr-3 text-sm outline-none focus:border-orange-400" placeholder="Từ khóa…" value={query} onChange={(event) => setQuery(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') void load() }} /></label><select className="rounded-xl border border-slate-200 px-3 py-2.5 text-sm" value={orderStatus} onChange={(event) => setOrderStatus(event.target.value)}><option value="">Mọi trạng thái</option>{['pending_payment', 'paid', 'processing', 'shipping', 'completed', 'cancelled'].map((key) => <option key={key} value={key}>{statusNames[key]}</option>)}</select><Button size="sm" onClick={() => void load()}>Tìm kiếm</Button></div></div>{loading ? <p className="p-6 text-sm text-slate-500">Đang tải…</p> : <div className="overflow-x-auto"><table className="w-full min-w-[900px] text-left text-sm"><thead className="bg-slate-50 text-xs uppercase text-slate-500"><tr>{['Mã đơn', 'Sản phẩm', 'Người mua', 'Người bán', 'Trạng thái', 'Vận chuyển', 'Tổng tiền'].map((label) => <th key={label} className="px-5 py-3 font-medium">{label}</th>)}</tr></thead><tbody className="divide-y divide-slate-100">{orders.map((order) => <tr key={order.id}><td className="px-5 py-4 font-semibold">#{order.id}<span className="mt-1 block text-xs font-normal text-slate-400">{date(order.created_at)}</span></td><td className="px-5 py-4">{order.product?.title}</td><td className="px-5 py-4">{order.buyer?.full_name || order.buyer?.email}</td><td className="px-5 py-4">{order.seller?.full_name || order.seller?.email}</td><td className="px-5 py-4"><span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs">{statusNames[order.status] || order.status}</span></td><td className="px-5 py-4">{order.tracking_number || 'Chưa có mã'}{order.shipping_carrier && <span className="block text-xs text-slate-500">{order.shipping_carrier}</span>}</td><td className="px-5 py-4 font-semibold">{number(order.total_amount)} đ</td></tr>)}</tbody></table>{orders.length === 0 && <p className="p-8 text-center text-sm text-slate-500">Không tìm thấy đơn hàng.</p>}</div>}</section>}
        {section === 'audit' && <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div className="border-b border-slate-200 p-5"><h2 className="font-semibold">Nhật ký quản trị</h2><p className="mt-1 text-sm text-slate-500">Lưu lại người thực hiện, đối tượng và thời điểm thao tác.</p></div>{loading ? <p className="p-6 text-sm text-slate-500">Đang tải…</p> : logs.length === 0 ? <p className="p-8 text-center text-sm text-slate-500">Chưa có thao tác quản trị.</p> : <div className="divide-y divide-slate-100">{logs.map((log) => <article key={log.id} className="flex flex-wrap items-center justify-between gap-3 p-5"><div className="flex items-start gap-3"><span className="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-600"><ClipboardList size={17} /></span><div><p className="font-medium">{log.summary}</p><p className="mt-1 text-xs text-slate-500">{log.admin?.full_name || log.admin?.email || 'Tài khoản đã xóa'} · {log.action} · {log.subject_type}{log.subject_id ? ` #${log.subject_id}` : ''}</p></div></div><time className="text-xs text-slate-500">{date(log.created_at)}</time></article>)}</div>}</section>}
        {section === 'management' && <AdminManagementPage accessToken={accessToken} onBack={() => setSection('overview')} />}
      </div>
    </main>
  </div>
}
