import { useEffect, useState, type FormEvent } from 'react'
import { ArrowDownToLine, ArrowLeft, ArrowUpFromLine, RefreshCw, Wallet } from 'lucide-react'
import { Alert } from './components/ui/alert'
import { Button } from './components/ui/button'
import { productRequest } from './productApi'

type Transaction = { reference: string; type: string; status: string; amount: number; mode: string | null; created_at: string | null }
type Withdrawal = { id: number; amount: number; status: string; reference: string; created_at: string | null; decision_reason: string | null }
type Deposit = { reference: string; amount: number; mode: string; status: string; expires_at: string | null; paid_at: string | null; resolution_note: string | null }
type WalletData = { available: number; withdrawal_hold: number; pending_release: number; transactions: Transaction[]; withdrawals: Withdrawal[]; deposits: Deposit[]; mode: string; simulation_enabled: boolean; vnpay_sandbox_available: boolean }
type Props = { accessToken: string; navigate: (path: string) => void }
const money = (amount: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount)
const withdrawalStatus: Record<string, string> = { pending_review: 'Chờ duyệt', processing: 'Đang xử lý', reconciliation_pending: 'Chờ đối soát', rejected: 'Đã từ chối', succeeded_simulated: 'Đã hoàn tất mô phỏng' }
const depositStatus: Record<string, string> = { pending: 'Chờ thanh toán Sandbox', paid: 'Đã ghi có ví Sandbox', failed: 'Thanh toán thất bại', cancelled: 'Đã hủy', expired: 'Đã hết hạn', reconciliation_required: 'Chờ quản trị viên đối soát' }
const typeLabel: Record<string, string> = { simulated_deposit: 'Nạp mô phỏng', vnpay_sandbox_deposit: 'Nạp VNPay Sandbox', withdrawal_hold: 'Giữ cho yêu cầu rút', withdrawal_rejected: 'Hoàn tiền giữ do bị từ chối', simulated_withdrawal_paid: 'Chi trả mô phỏng', listing_fee: 'Phí đăng tin mô phỏng', product_promotion: 'Gói ưu tiên tin mô phỏng' }

export default function WalletPage({ accessToken, navigate }: Props) {
  const [data, setData] = useState<WalletData | null>(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [amount, setAmount] = useState('100000')
  const [bankName, setBankName] = useState('')
  const [accountHolder, setAccountHolder] = useState('')
  const [accountNumber, setAccountNumber] = useState('')
  const [depositResult, setDepositResult] = useState<Deposit | null>(null)

  async function load() {
    setLoading(true); setError('')
    try {
      setData((await productRequest<WalletData>('me/wallet', accessToken)).data)
      const reference = new URLSearchParams(window.location.search).get('depositRef')
      if (reference) {
        const result = (await productRequest<Deposit>(`me/wallet/vnpay-deposits/${encodeURIComponent(reference)}`, accessToken)).data
        setDepositResult(result)
        setNotice(result.status === 'paid' ? 'VNPay Sandbox đã xác nhận và ghi có giao dịch vào sổ ví.' : result.status === 'reconciliation_required' ? 'Giao dịch cần quản trị viên đối soát; hệ thống chưa tự ghi có.' : result.status === 'pending' ? 'Yêu cầu đã tạo; hệ thống vẫn đang chờ xác nhận thanh toán.' : `Yêu cầu nạp: ${depositStatus[result.status] ?? result.status}.`)
      }
    }
    catch (cause) { setError((cause as Error).message) }
    finally { setLoading(false) }
  }
  useEffect(() => { void load() }, [accessToken])

  async function simulatedDeposit() {
    setBusy(true); setError(''); setNotice('')
    try {
      const result = await productRequest<{ balance: WalletData }>('me/wallet/simulated-deposits', accessToken, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ amount: Number(amount), idempotency_key: crypto.randomUUID() }) })
      setNotice(result.message); await load()
    } catch (cause) { setError((cause as Error).message) }
    finally { setBusy(false) }
  }

  async function vnpayDeposit() {
    setBusy(true); setError(''); setNotice('')
    try {
      const result = await productRequest<{ payment_url: string }>('me/wallet/vnpay-deposits', accessToken, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ amount: Number(amount), idempotency_key: crypto.randomUUID() }) })
      window.location.assign(result.data.payment_url)
    } catch (cause) { setError((cause as Error).message); setBusy(false) }
  }

  async function requestWithdrawal(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    try {
      const result = await productRequest<unknown>('me/wallet/withdrawals', accessToken, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ amount: Number(amount), idempotency_key: crypto.randomUUID(), bank_name: bankName, account_holder: accountHolder, account_number: accountNumber }) })
      setNotice(result.message); setAccountNumber(''); await load()
    } catch (cause) { setError((cause as Error).message) }
    finally { setBusy(false) }
  }

  return <main className="mx-auto max-w-frame px-4 py-8 sm:py-12">
    <button onClick={() => navigate('/')} className="mb-5 inline-flex items-center gap-2 text-sm text-black/55"><ArrowLeft size={16}/>Trang chủ</button>
    <div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-xs font-semibold uppercase tracking-[0.16em] text-[#b53a20]">Tài khoản</p><h1 className="mt-2 flex items-center gap-2 text-3xl font-bold"><Wallet/>Số dư H-Smart</h1></div><Button variant="outline" onClick={() => void load()} disabled={loading}><RefreshCw size={15}/>Làm mới</Button></div>
    <Alert variant="error" className="mt-5">Ví demo có thể hiển thị nạp mô phỏng hoặc giao dịch VNPay Sandbox. Chúng không phải tiền thật đã quyết toán hoặc có thể rút ra ngân hàng. Chỉ nhập dữ liệu thử nghiệm.</Alert>
    {depositResult && <Alert variant={depositResult.status === 'paid' ? 'success' : 'error'} className="mt-4"><span className="font-semibold">{depositStatus[depositResult.status] ?? depositResult.status}</span> · {money(depositResult.amount)} · mã {depositResult.reference}{depositResult.resolution_note ? ` · ${depositResult.resolution_note}` : ''}</Alert>}
    {error && <Alert variant="error" className="mt-4">{error}</Alert>}{notice && <Alert variant="success" className="mt-4">{notice}</Alert>}
    {loading && !data ? <p className="py-12 text-sm text-black/50" role="status">Đang tải số dư…</p> : data && <>
      <section className="mt-6 grid gap-3 sm:grid-cols-3">{[
        ['Số dư khả dụng', data.available], ['Đang giữ cho yêu cầu rút', data.withdrawal_hold], ['Bán hàng chờ giải ngân', data.pending_release],
      ].map(([label, value]) => <article key={String(label)} className="rounded-2xl border border-black/10 bg-white p-5"><p className="text-sm text-black/55">{label}</p><p className="mt-2 text-2xl font-bold">{money(Number(value))}</p></article>)}</section>
      <section className="mt-6 grid gap-4 lg:grid-cols-2"><article className="rounded-2xl border border-black/10 p-5"><h2 className="flex items-center gap-2 font-bold"><ArrowDownToLine size={17}/>Nạp số dư</h2><p className="mt-1 text-xs text-black/55">Chọn rõ một chế độ. Giới hạn mỗi yêu cầu: 10.000–10.000.000đ.</p><label className="mt-4 block text-sm">Số tiền<input className="mt-1 w-full rounded-xl border border-black/15 px-3 py-2.5" type="number" min={10000} max={10000000} step={1000} value={amount} onChange={(e) => setAmount(e.target.value)}/></label><div className="mt-4 flex flex-wrap gap-2">{data.simulation_enabled && <Button onClick={() => void simulatedDeposit()} disabled={busy}>{busy ? 'Đang ghi sổ…' : 'Nạp mô phỏng QA'}</Button>}{data.vnpay_sandbox_available && <Button variant="outline" onClick={() => void vnpayDeposit()} disabled={busy}>{busy ? 'Đang tạo yêu cầu…' : 'Nạp qua VNPay Sandbox'}</Button>}</div>{!data.vnpay_sandbox_available && <p className="mt-3 text-xs text-slate-500">Chưa cấu hình VNPay Sandbox trong môi trường này.</p>}</article>
        <form className="rounded-2xl border border-black/10 p-5" onSubmit={requestWithdrawal}><h2 className="flex items-center gap-2 font-bold"><ArrowUpFromLine size={17}/>Yêu cầu rút mô phỏng</h2><p className="mt-1 text-xs text-black/55">Yêu cầu sẽ giữ số dư và chờ quản trị viên xử lý; không gửi tiền thật.</p><label className="mt-4 block text-sm">Số tiền (tối thiểu 50.000đ)<input className="mt-1 w-full rounded-xl border border-black/15 px-3 py-2.5" type="number" min={50000} max={10000000} step={1000} required value={amount} onChange={(e) => setAmount(e.target.value)}/></label><div className="mt-3 grid gap-3 sm:grid-cols-2"><label className="text-sm">Ngân hàng<input className="mt-1 w-full rounded-xl border border-black/15 px-3 py-2.5" maxLength={100} required value={bankName} onChange={(e) => setBankName(e.target.value)}/></label><label className="text-sm">Tên chủ tài khoản<input className="mt-1 w-full rounded-xl border border-black/15 px-3 py-2.5" maxLength={150} required value={accountHolder} onChange={(e) => setAccountHolder(e.target.value)}/></label></div><label className="mt-3 block text-sm">Số tài khoản<input className="mt-1 w-full rounded-xl border border-black/15 px-3 py-2.5" maxLength={40} required value={accountNumber} onChange={(e) => setAccountNumber(e.target.value)}/></label><Button className="mt-4" type="submit" disabled={busy}>{busy ? 'Đang gửi yêu cầu…' : 'Gửi yêu cầu rút'}</Button></form>
      </section>
      <section className="mt-8 grid gap-5 lg:grid-cols-2"><article className="rounded-2xl border border-black/10 p-5"><h2 className="font-bold">Lịch sử sổ giao dịch</h2>{data.transactions.length === 0 ? <p className="mt-4 text-sm text-black/50">Chưa có giao dịch.</p> : <ul className="mt-3 divide-y divide-black/10">{data.transactions.map((transaction) => <li key={transaction.reference} className="flex items-start justify-between gap-3 py-3 text-sm"><div><p className="font-medium">{typeLabel[transaction.type] ?? transaction.type}{transaction.mode ? ` · ${transaction.mode === 'sandbox' ? 'Sandbox' : transaction.mode === 'simulation' ? 'Mô phỏng' : transaction.mode}` : ''}</p><p className="mt-1 font-mono text-[10px] text-black/45">{transaction.reference}</p>{transaction.created_at && <time className="mt-1 block text-xs text-black/45">{new Date(transaction.created_at).toLocaleString('vi-VN')}</time>}</div><strong>{money(Math.abs(transaction.amount))}</strong></li>)}</ul>}</article>
        <article className="rounded-2xl border border-black/10 p-5"><h2 className="font-bold">Yêu cầu nạp VNPay Sandbox</h2>{data.deposits.length === 0 ? <p className="mt-4 text-sm text-black/50">Chưa có yêu cầu nạp Sandbox.</p> : <ul className="mt-3 divide-y divide-black/10">{data.deposits.map((item) => <li key={item.reference} className="py-3"><div className="flex justify-between gap-3 text-sm"><span className="font-medium">{depositStatus[item.status] ?? item.status} · Sandbox</span><strong>{money(item.amount)}</strong></div><p className="mt-1 font-mono text-[10px] text-black/45">{item.reference}</p><p className="mt-1 text-xs text-black/50">{item.paid_at ? new Date(item.paid_at).toLocaleString('vi-VN') : item.expires_at ? `Hết hạn ${new Date(item.expires_at).toLocaleString('vi-VN')}` : ''}</p>{item.resolution_note && <p className="mt-1 text-xs text-amber-800">{item.resolution_note}</p>}</li>)}</ul>}</article>
        <article className="rounded-2xl border border-black/10 p-5"><h2 className="font-bold">Yêu cầu rút tiền</h2>{data.withdrawals.length === 0 ? <p className="mt-4 text-sm text-black/50">Chưa có yêu cầu.</p> : <ul className="mt-3 divide-y divide-black/10">{data.withdrawals.map((item) => <li key={item.id} className="py-3"><div className="flex justify-between gap-3 text-sm"><span className="font-medium">{withdrawalStatus[item.status] ?? item.status}</span><strong>{money(item.amount)}</strong></div><p className="mt-1 font-mono text-[10px] text-black/45">{item.reference}</p>{item.decision_reason && <p className="mt-1 text-xs text-black/60">Lý do: {item.decision_reason}</p>}</li>)}</ul>}</article></section>
    </>}
  </main>
}
