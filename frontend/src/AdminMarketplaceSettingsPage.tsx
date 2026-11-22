import { useEffect, useState, type FormEvent } from 'react'
import * as api from './adminApi'
import { Button } from './components/ui/button'

type Props = { accessToken: string }
const descriptions: Array<[keyof api.MarketplaceSettings, string, string]> = [
  ['commission_rate', 'Hoa hồng sàn', '% trên sản phẩm và giao hàng'],
  ['shipping_fee', 'Phí giao hàng mặc định', 'đồng'],
  ['listing_free_monthly', 'Lượt đăng miễn phí mỗi tháng', 'lượt'],
  ['listing_fee', 'Phí đăng tin sau hạn mức', 'đồng'],
  ['boost_price', 'Giá gói Boost', 'đồng'],
  ['boost_days', 'Thời hạn Boost', 'ngày'],
  ['vip_price', 'Giá gói VIP', 'đồng'],
  ['vip_days', 'Thời hạn VIP', 'ngày'],
  ['promotion_slots', 'Số vị trí ưu tiên', 'vị trí'],
  ['cod_deposit_percent', 'Tỷ lệ cọc COD', '% (được phép từ 20 đến 30)'],
]

export default function AdminMarketplaceSettingsPage({ accessToken }: Props) {
  const [values, setValues] = useState<api.MarketplaceSettings | null>(null)
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')

  useEffect(() => { void api.getMarketplaceSettings(accessToken).then(setValues).catch((cause) => setError((cause as Error).message)) }, [accessToken])

  async function save(event: FormEvent) {
    event.preventDefault()
    if (!values) return
    setBusy(true); setError(''); setMessage('')
    try { setValues(await api.updateMarketplaceSettings(accessToken, values)); setMessage('Đã lưu thiết lập. Đơn và giao dịch đã tạo vẫn giữ nguyên mức phí đã chụp.') }
    catch (cause) { setError((cause as Error).message) }
    finally { setBusy(false) }
  }

  if (!values) return <section className="rounded-2xl border bg-white p-6">{error ? <p role="alert" className="text-red-700">{error}</p> : 'Đang tải thiết lập…'}</section>
  return <form onSubmit={save} className="rounded-2xl border bg-white p-5 sm:p-7">
    <div className="mb-6"><h2 className="text-xl font-semibold">Biểu phí và giới hạn</h2><p className="mt-1 text-sm text-slate-600">Các thay đổi áp dụng cho giao dịch mới. Mức cọc COD được giới hạn trong khoảng 20–30%.</p></div>
    <div className="grid gap-4 sm:grid-cols-2">{descriptions.map(([key, label, suffix]) => <label key={key} className="block text-sm font-medium">{label}<span className="mt-1.5 flex items-center gap-2"><input type="number" min={key === 'cod_deposit_percent' ? 20 : 0} max={key === 'cod_deposit_percent' ? 30 : undefined} step="1" value={values[key]} onChange={(event) => setValues({ ...values, [key]: Number(event.target.value) })} className="h-10 w-full rounded-lg border px-3" /><span className="w-40 shrink-0 text-xs text-slate-500">{suffix}</span></span></label>)}</div>
    {error && <p role="alert" className="mt-4 text-sm text-red-700">{error}</p>}{message && <p role="status" className="mt-4 text-sm text-emerald-700">{message}</p>}
    <div className="mt-6 flex justify-end"><Button type="submit" disabled={busy}>{busy ? 'Đang lưu…' : 'Lưu thiết lập'}</Button></div>
  </form>
}
