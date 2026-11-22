import { useEffect, useState, type FormEvent } from 'react'
import * as api from './adminApi'
import { Button } from './components/ui/button'

type Props = { accessToken: string }
const money = (amount: number) => new Intl.NumberFormat('vi-VN').format(amount)
const labels: Record<string, string> = { matched: 'Đã khớp, đơn đã thanh toán', unmatched: 'Không tìm thấy mã đơn', not_payable: 'Đơn không còn chờ thanh toán', late: 'Tiền đến sau hạn giữ đơn', amount_mismatch: 'Sai lệch số tiền' }

export default function AdminPaymentReconciliationPage({ accessToken }: Props) {
  const [items, setItems] = useState<api.PaymentReconciliation[]>([])
  const [paymentReference, setPaymentReference] = useState('')
  const [bankReference, setBankReference] = useState('')
  const [amount, setAmount] = useState('')
  const [evidence, setEvidence] = useState('')
  const [confirmed, setConfirmed] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  async function load() {
    try { setItems((await api.getPaymentReconciliations(accessToken)).data) }
    catch (cause) { setError((cause as Error).message) }
  }
  useEffect(() => { void load() }, [accessToken])

  async function submit(event: FormEvent) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    try {
      const record = await api.reconcileVietQr(accessToken, { payment_reference: paymentReference.trim(), bank_reference: bankReference.trim(), amount: Number(amount), evidence_note: evidence.trim() })
      setNotice(labels[record.status] || record.status)
      setPaymentReference(''); setBankReference(''); setAmount(''); setEvidence(''); setConfirmed(false)
      await load()
    } catch (cause) { setError((cause as Error).message) }
    finally { setBusy(false) }
  }

  return <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
    <form onSubmit={submit} className="rounded-2xl border bg-white p-5"><h2 className="text-xl font-semibold">Đối soát VietQR</h2><p className="mt-2 text-sm leading-6 text-slate-600">Chỉ khớp tiền sau khi kiểm tra giao dịch trong sao kê tài khoản nhận. QR hoặc ảnh chụp do người mua gửi không phải bằng chứng xác nhận.</p>
      <div className="mt-4 grid gap-3"><label className="text-sm font-medium">Mã thanh toán H-Smart<input required minLength={8} maxLength={100} value={paymentReference} onChange={(e) => setPaymentReference(e.target.value)} className="mt-1 block h-10 w-full rounded-lg border px-3" /></label><label className="text-sm font-medium">Mã giao dịch ngân hàng<input required minLength={3} maxLength={100} value={bankReference} onChange={(e) => setBankReference(e.target.value)} className="mt-1 block h-10 w-full rounded-lg border px-3" /></label><label className="text-sm font-medium">Số tiền thực nhận (đ)<input required type="number" min={1} step={1} value={amount} onChange={(e) => setAmount(e.target.value)} className="mt-1 block h-10 w-full rounded-lg border px-3" /></label><label className="text-sm font-medium">Ghi chú sao kê / nguồn xác minh<textarea required minLength={8} maxLength={1000} rows={3} value={evidence} onChange={(e) => setEvidence(e.target.value)} className="mt-1 block w-full rounded-lg border p-3" placeholder="Thời điểm, tài khoản nhận, mô tả giao dịch…" /></label><label className="flex items-start gap-2 text-sm"><input type="checkbox" required checked={confirmed} onChange={(e) => setConfirmed(e.target.checked)} className="mt-1"/><span>Tôi đã kiểm tra trực tiếp sao kê ngân hàng và xác nhận khoản tiền đã vào tài khoản.</span></label></div>
      {error && <p role="alert" className="mt-3 text-sm text-red-700">{error}</p>}{notice && <p role="status" className="mt-3 text-sm text-emerald-800">{notice}. Kết quả sai số, đến muộn hoặc không khớp được lưu để xử lý thủ công và không tự đánh dấu đơn đã thanh toán.</p>}
      <Button className="mt-4" disabled={busy || !confirmed}>{busy ? 'Đang đối soát…' : 'Ghi nhận và đối soát'}</Button>
    </form>
    <section className="rounded-2xl border bg-white"><header className="border-b p-5"><h2 className="text-xl font-semibold">Nhật ký đối soát</h2><p className="mt-1 text-sm text-slate-600">Giao dịch được ghi bất biến theo mã ngân hàng; mã trùng bị từ chối.</p></header>{items.length ? <div className="divide-y">{items.map((item) => <article key={item.id} className="p-4 text-sm"><div className="flex flex-wrap justify-between gap-2"><strong>{labels[item.status] || item.status}</strong><span>{money(item.amount)} đ</span></div><p className="mt-1 font-mono text-xs text-slate-600">Mã H-Smart: {item.payment_reference} · Ngân hàng: {item.bank_reference}</p><p className="mt-1 text-slate-600">Đơn #{item.order_id ?? 'chưa khớp'} · {item.evidence_note}</p><time className="mt-1 block text-xs text-slate-500">{item.reconciled_at ? new Date(item.reconciled_at).toLocaleString('vi-VN') : 'Chưa xử lý'}</time></article>)}</div> : <p className="p-8 text-center text-sm text-slate-500">Chưa có giao dịch đối soát.</p>}</section>
  </div>
}
