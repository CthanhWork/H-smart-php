import { useEffect, useState } from 'react'
import * as api from './adminApi'

type Props = { accessToken: string }
const money = (amount: number) => new Intl.NumberFormat('vi-VN').format(amount)
const labels: Record<string, string> = {
  matched: 'Đã khớp theo dữ liệu lịch sử',
  unmatched: 'Không tìm thấy mã đơn',
  not_payable: 'Đơn không còn chờ thanh toán',
  late: 'Giao dịch đến sau hạn giữ đơn',
  amount_mismatch: 'Sai lệch số tiền',
}

export default function AdminPaymentReconciliationPage({ accessToken }: Props) {
  const [items, setItems] = useState<api.PaymentReconciliation[]>([])
  const [error, setError] = useState('')

  async function load() {
    try { setItems((await api.getPaymentReconciliations(accessToken)).data); setError('') }
    catch (cause) { setError((cause as Error).message) }
  }
  useEffect(() => { void load() }, [accessToken])

  return <section className="rounded-2xl border bg-white">
    <header className="border-b p-5">
      <h2 className="text-xl font-semibold">Lịch sử đối soát VietQR</h2>
      <p className="mt-2 text-sm leading-6 text-slate-600">Chỉ tra cứu lịch sử. Không thể tạo hoặc xác nhận giao dịch VietQR mới; đơn cũ còn chờ được giữ nguyên để xử lý theo hồ sơ riêng.</p>
    </header>
    {error && <p role="alert" className="p-4 text-sm text-red-700">{error}</p>}
    {items.length ? <div className="divide-y">{items.map((item) => <article key={item.id} className="p-4 text-sm">
      <div className="flex flex-wrap justify-between gap-2"><strong>{labels[item.status] ?? 'Đã lưu lịch sử đối soát'}</strong><span>{money(item.amount)} đ</span></div>
      <p className="mt-1 text-xs text-slate-600">Mã H-Smart: {item.payment_reference} · Mã đối soát: {item.bank_reference}</p>
      <p className="mt-1 text-slate-600">Đơn #{item.order_id ?? 'chưa khớp'} · {item.evidence_note}</p>
      <time className="mt-1 block text-xs text-slate-500">{item.reconciled_at ? new Date(item.reconciled_at).toLocaleString('vi-VN') : 'Chưa xử lý'}</time>
    </article>)}</div> : <p className="p-8 text-center text-sm text-slate-500">Chưa có bản ghi đối soát lịch sử.</p>}
  </section>
}
