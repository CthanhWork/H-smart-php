import { useEffect, useState, type FormEvent } from 'react'
import { ArrowDownToLine, ArrowLeft, ArrowUpFromLine, RefreshCw, Wallet } from 'lucide-react'
import { Alert } from './components/ui/alert'
import { Button } from './components/ui/button'
import { productRequest } from './productApi'

type Transaction = { reference: string; type: string; status: string; amount: number; created_at: string | null }
type Withdrawal = { id: number; amount: number; status: string; reference: string; created_at: string | null; decision_reason: string | null }
type WalletData = { available: number; withdrawal_hold: number; pending_release: number; transactions: Transaction[]; withdrawals: Withdrawal[]; mode: string }
type Props = { accessToken: string; navigate: (path: string) => void }
const money = (amount: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount)
const withdrawalStatus: Record<string, string> = { pending_review: 'Chờ duyệt', processing: 'Đang xử lý', reconciliation_pending: 'Chờ đối soát', rejected: 'Đã từ chối', succeeded_simulated: 'Đã hoàn tất mô phỏng' }
const typeLabel: Record<string, string> = { simulated_deposit: 'Nạp mô phỏng', withdrawal_hold: 'Giữ cho yêu cầu rút', withdrawal_rejected: 'Hoàn tiền giữ do bị từ chối', simulated_withdrawal_paid: 'Chi trả mô phỏng', listing_fee: 'Phí đăng tin mô phỏng', product_promotion: 'Gói ưu tiên tin mô phỏng' }

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

  async function load() {
    setLoading(true); setError('')
    try { setData((await productRequest<WalletData>('me/wallet', accessToken)).data) }
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
    <Alert variant="error" className="mt-5">Chế độ mô phỏng: số dư, nạp và rút hiển thị tại đây không đại diện cho tiền thật. Không nhập thông tin tài khoản ngân hàng thật vào bản demo.</Alert>
    {error && <Alert variant="error" className="mt-4">{error}</Alert>}{notice && <Alert variant="success" className="mt-4">{notice}</Alert>}
    {loading && !data ? <p className="py-12 text-sm text-black/50" role="status">Đang tải số dư…</p> : data && <>
      <section className="mt-6 grid gap-3 sm:grid-cols-3">{[
        ['Số dư khả dụng', data.available], ['Đang giữ cho yêu cầu rút', data.withdrawal_hold], ['Bán hàng chờ giải ngân', data.pending_release],
      ].map(([label, value]) => <article key={String(label)} className="rounded-2xl border border-black/10 bg-white p-5"><p className="text-sm text-black/55">{label}</p><p className="mt-2 text-2xl font-bold">{money(Number(value))}</p></article>)}</section>
      <section className="mt-6 grid gap-4 lg:grid-cols-2"><article className="rounded-2xl border border-black/10 p-5"><h2 className="flex items-center gap-2 font-bold"><ArrowDownToLine size={17}/>Nạp tiền mô phỏng</h2><p className="mt-1 text-xs text-black/55">Chỉ dùng để trình diễn; không phát sinh giao dịch ngân hàng.</p><label className="mt-4 block text-sm">Số tiền (10.000–10.000.000đ)<input className="mt-1 w-full rounded-xl border border-black/15 px-3 py-2.5" type="number" min={10000} max={10000000} step={1000} value={amount} onChange={(e) => setAmount(e.target.value)}/></label><Button className="mt-4" onClick={() => void simulatedDeposit()} disabled={busy}>{busy ? 'Đang ghi sổ…' : 'Cộng số dư mô phỏng'}</Button></article>
        <form className="rounded-2xl border border-black/10 p-5" onSubmit={requestWithdrawal}><h2 className="flex items-center gap-2 font-bold"><ArrowUpFromLine size={17}/>Yêu cầu rút mô phỏng</h2><p className="mt-1 text-xs text-black/55">Yêu cầu sẽ giữ số dư và chờ quản trị viên xử lý; không gửi tiền thật.</p><label className="mt-4 block text-sm">Số tiền (tối thiểu 50.000đ)<input className="mt-1 w-full rounded-xl border border-black/15 px-3 py-2.5" type="number" min={50000} max={10000000} step={1000} required value={amount} onChange={(e) => setAmount(e.target.value)}/></label><div className="mt-3 grid gap-3 sm:grid-cols-2"><label className="text-sm">Ngân hàng<input className="mt-1 w-full rounded-xl border border-black/15 px-3 py-2.5" maxLength={100} required value={bankName} onChange={(e) => setBankName(e.target.value)}/></label><label className="text-sm">Tên chủ tài khoản<input className="mt-1 w-full rounded-xl border border-black/15 px-3 py-2.5" maxLength={150} required value={accountHolder} onChange={(e) => setAccountHolder(e.target.value)}/></label></div><label className="mt-3 block text-sm">Số tài khoản<input className="mt-1 w-full rounded-xl border border-black/15 px-3 py-2.5" maxLength={40} required value={accountNumber} onChange={(e) => setAccountNumber(e.target.value)}/></label><Button className="mt-4" type="submit" disabled={busy}>{busy ? 'Đang gửi yêu cầu…' : 'Gửi yêu cầu rút'}</Button></form>
      </section>
      <section className="mt-8 grid gap-5 lg:grid-cols-2"><article className="rounded-2xl border border-black/10 p-5"><h2 className="font-bold">Lịch sử sổ giao dịch</h2>{data.transactions.length === 0 ? <p className="mt-4 text-sm text-black/50">Chưa có giao dịch.</p> : <ul className="mt-3 divide-y divide-black/10">{data.transactions.map((transaction) => <li key={transaction.reference} className="flex items-start justify-between gap-3 py-3 text-sm"><div><p className="font-medium">{typeLabel[transaction.type] ?? transaction.type}</p><p className="mt-1 font-mono text-[10px] text-black/45">{transaction.reference}</p>{transaction.created_at && <time className="mt-1 block text-xs text-black/45">{new Date(transaction.created_at).toLocaleString('vi-VN')}</time>}</div><strong>{money(Math.abs(transaction.amount))}</strong></li>)}</ul>}</article>
        <article className="rounded-2xl border border-black/10 p-5"><h2 className="font-bold">Yêu cầu rút tiền</h2>{data.withdrawals.length === 0 ? <p className="mt-4 text-sm text-black/50">Chưa có yêu cầu.</p> : <ul className="mt-3 divide-y divide-black/10">{data.withdrawals.map((item) => <li key={item.id} className="py-3"><div className="flex justify-between gap-3 text-sm"><span className="font-medium">{withdrawalStatus[item.status] ?? item.status}</span><strong>{money(item.amount)}</strong></div><p className="mt-1 font-mono text-[10px] text-black/45">{item.reference}</p>{item.decision_reason && <p className="mt-1 text-xs text-black/60">Lý do: {item.decision_reason}</p>}</li>)}</ul>}</article></section>
    </>}
  </main>
}
