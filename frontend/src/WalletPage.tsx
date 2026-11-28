import { useEffect, useState, type FormEvent } from 'react'
import { ArrowDownToLine, ArrowLeft, ArrowUpFromLine, RefreshCw, Wallet } from 'lucide-react'
import { Alert } from './components/ui/alert'
import { Button } from './components/ui/button'
import { productRequest } from './productApi'

type Transaction = { reference: string; type: string; status: string; amount: number; mode: string | null; created_at: string | null }
type Withdrawal = { id: number; amount: number; status: string; reference: string; created_at: string | null; decision_reason: string | null }
type Deposit = { reference: string; amount: number; mode: string; status: string; expires_at: string | null; paid_at: string | null; resolution_note: string | null }
type WalletData = { available: number; withdrawal_hold: number; pending_release: number; transactions: Transaction[]; withdrawals: Withdrawal[]; deposits: Deposit[]; vnpay_sandbox_available: boolean }
type Props = { accessToken: string; navigate: (path: string) => void }
const money = (amount: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount)
const withdrawalStatus: Record<string, string> = { pending_review: 'Chờ duyệt', processing: 'Đang xử lý', reconciliation_pending: 'Chờ đối soát', rejected: 'Đã từ chối', failed: 'Thất bại, đã trả số dư', cancelled: 'Đã hủy', succeeded: 'Đã ghi nhận chi trả', succeeded_simulated: 'Đã ghi nhận chi trả (lịch sử demo)' }
const depositStatus: Record<string, string> = { pending: 'Chờ thanh toán Sandbox', paid: 'Đã ghi có ví Sandbox', failed: 'Thanh toán thất bại', cancelled: 'Đã hủy', expired: 'Đã hết hạn', reconciliation_required: 'Chờ quản trị viên đối soát' }
const typeLabel: Record<string, string> = { vnpay_sandbox_deposit: 'Nạp ví qua VNPay', simulated_deposit: 'Giao dịch nạp cũ (demo)', withdrawal_hold: 'Giữ cho yêu cầu rút', withdrawal_rejected: 'Trả lại khoản giữ', withdrawal_failed: 'Trả lại khoản giữ', simulated_withdrawal_paid: 'Chi trả cũ (demo)', withdrawal_paid_manual: 'Chi trả cho người bán', listing_fee: 'Phí đăng tin', product_promotion: 'Gói ưu tiên tin' }

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
  const [savedBank, setSavedBank] = useState<{bank_name:string;account_holder:string;masked_account_number:string}|null>(null)
  const [depositResult, setDepositResult] = useState<Deposit | null>(null)

  async function load() {
    setLoading(true); setError('')
    try {
      setData((await productRequest<WalletData>('me/wallet', accessToken)).data)
      setSavedBank((await productRequest<{bank_name:string;account_holder:string;masked_account_number:string}|null>('me/wallet/bank-account', accessToken)).data)
      const reference = new URLSearchParams(window.location.search).get('depositRef')
      if (reference) {
        const result = (await productRequest<Deposit>(`me/wallet/vnpay-deposits/${encodeURIComponent(reference)}`, accessToken)).data
        setDepositResult(result)
        setNotice(result.status === 'paid' ? 'VNPay Sandbox đã xác nhận và ghi có giao dịch vào sổ ví.' : result.status === 'reconciliation_required' ? 'Giao dịch cần quản trị viên đối soát; hệ thống chưa tự ghi có.' : result.status === 'pending' ? 'Yêu cầu đã tạo; hệ thống vẫn đang chờ xác nhận thanh toán.' : `Yêu cầu nạp: ${depositStatus[result.status] ?? 'Đang chờ quản trị viên kiểm tra'}.`)
      }
    }
    catch (cause) { setError((cause as Error).message) }
    finally { setLoading(false) }
  }
  useEffect(() => { void load() }, [accessToken])

  async function saveBank(event: FormEvent<HTMLFormElement>) { event.preventDefault(); setBusy(true); setError(''); try { const result = await productRequest<typeof savedBank>('me/wallet/bank-account', accessToken, { method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({bank_name:bankName,account_holder:accountHolder,account_number:accountNumber}) }); setSavedBank(result.data); setAccountNumber(''); setNotice(result.message) } catch(cause) { setError((cause as Error).message) } finally { setBusy(false) } }

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
      const result = await productRequest<{bank_snapshot?:{bank_name:string;account_holder:string;masked_account_number:string}}>('me/wallet/withdrawals', accessToken, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ amount: Number(amount), idempotency_key: crypto.randomUUID(), confirm_bank_details: true }) })
      setNotice(`${result.message}${result.data.bank_snapshot ? ` Người nhận: ${result.data.bank_snapshot.bank_name} · ${result.data.bank_snapshot.account_holder} · ${result.data.bank_snapshot.masked_account_number}` : ''}`); await load()
    } catch (cause) { setError((cause as Error).message) }
    finally { setBusy(false) }
  }

  async function cancelWithdrawal(id: number) { setBusy(true); setError(''); setNotice(''); try { const result = await productRequest('me/wallet/withdrawals/'+id+'/cancel', accessToken, {method:'POST'}); setNotice(result.message); await load() } catch(cause) { setError((cause as Error).message) } finally { setBusy(false) } }

  return <main className="mx-auto max-w-frame px-4 py-8 sm:py-12">
    <button onClick={() => navigate('/')} className="mb-5 inline-flex items-center gap-2 text-sm text-black/55"><ArrowLeft size={16}/>Trang chủ</button>
    <div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-xs font-semibold uppercase tracking-[0.16em] text-[#b53a20]">Tài khoản</p><h1 className="mt-2 flex items-center gap-2 text-3xl font-bold"><Wallet/>Ví của tôi</h1></div><Button variant="outline" onClick={() => void load()} disabled={loading}><RefreshCw size={15}/>Làm mới</Button></div>
    <Alert variant="error" className="mt-5">Các khoản nạp và thanh toán qua VNPay sử dụng môi trường Sandbox. Yêu cầu chi trả cho người bán được quản trị viên ghi nhận thủ công trên dữ liệu thử nghiệm; không xác nhận chuyển khoản ngân hàng thật.</Alert>
    {depositResult && <Alert variant={depositResult.status === 'paid' ? 'success' : 'error'} className="mt-4"><span className="font-semibold">{depositStatus[depositResult.status] ?? 'Đang chờ quản trị viên kiểm tra'}</span> · {money(depositResult.amount)} · mã {depositResult.reference}{depositResult.resolution_note ? ` · ${depositResult.resolution_note}` : ''}</Alert>}
    {error && <Alert variant="error" className="mt-4">{error}</Alert>}{notice && <Alert variant="success" className="mt-4">{notice}</Alert>}
    {loading && !data ? <p className="py-12 text-sm text-black/50" role="status">Đang tải số dư…</p> : data && <>
      <section className="mt-6 grid gap-3 sm:grid-cols-3">{[
        ['Số dư khả dụng', data.available], ['Đang giữ cho yêu cầu rút', data.withdrawal_hold], ['Bán hàng chờ giải ngân', data.pending_release],
      ].map(([label, value]) => <article key={String(label)} className="rounded-2xl border border-black/10 bg-white p-5"><p className="text-sm text-black/55">{label}</p><p className="mt-2 text-2xl font-bold">{money(Number(value))}</p></article>)}</section>
      <section className="mt-6 grid gap-4 lg:grid-cols-2"><article className="rounded-2xl border border-black/10 p-5"><h2 className="flex items-center gap-2 font-bold"><ArrowDownToLine size={17}/>Nạp ví</h2><p className="mt-1 text-xs text-black/55">Thanh toán qua VNPay Sandbox. Giới hạn mỗi yêu cầu: 10.000–10.000.000đ.</p><label className="mt-4 block text-sm">Số tiền<input className="mt-1 w-full rounded-xl border border-black/15 px-3 py-2.5" type="number" min={10000} max={10000000} step={1000} value={amount} onChange={(e) => setAmount(e.target.value)}/></label><div className="mt-4 flex flex-wrap gap-2">{data.vnpay_sandbox_available && <Button variant="outline" onClick={() => void vnpayDeposit()} disabled={busy}>{busy ? 'Đang tạo yêu cầu…' : 'Nạp tiền qua VNPay'}</Button>}</div>{!data.vnpay_sandbox_available && <p className="mt-3 text-xs text-slate-500">VNPay hiện chưa khả dụng.</p>}</article>
        <div className="grid gap-4"><form className="rounded-2xl border border-black/10 p-5" onSubmit={saveBank}><h2 className="font-bold">Tài khoản ngân hàng</h2>{savedBank && <p className="mt-2 text-sm">{savedBank.bank_name} · {savedBank.account_holder} · {savedBank.masked_account_number}</p>}<div className="mt-3 grid gap-3 sm:grid-cols-2"><label className="text-sm">Ngân hàng<input className="mt-1 w-full rounded-xl border border-black/15 px-3 py-2.5" maxLength={100} required value={bankName} onChange={(e) => setBankName(e.target.value)}/></label><label className="text-sm">Tên chủ tài khoản<input className="mt-1 w-full rounded-xl border border-black/15 px-3 py-2.5" maxLength={150} required value={accountHolder} onChange={(e) => setAccountHolder(e.target.value)}/></label></div><label className="mt-3 block text-sm">Số tài khoản<input className="mt-1 w-full rounded-xl border border-black/15 px-3 py-2.5" maxLength={40} required value={accountNumber} onChange={(e) => setAccountNumber(e.target.value)}/></label><Button className="mt-3" type="submit" variant="outline" disabled={busy}>Lưu thông tin</Button></form>
        <form className="rounded-2xl border border-black/10 p-5" onSubmit={requestWithdrawal}><h2 className="flex items-center gap-2 font-bold"><ArrowUpFromLine size={17}/>Rút tiền</h2><p className="mt-1 text-xs text-black/55">Quản trị viên xử lý thủ công trong phạm vi demo/Sandbox.</p><label className="mt-4 block text-sm">Số tiền (tối thiểu 50.000đ)<input className="mt-1 w-full rounded-xl border border-black/15 px-3 py-2.5" type="number" min={50000} max={10000000} step={1000} required value={amount} onChange={(e) => setAmount(e.target.value)}/></label><label className="mt-3 flex gap-2 text-sm"><input type="checkbox" required/>Tôi xác nhận thông tin nhận tiền hiển thị phía trên là chính xác.</label>{savedBank && <p className="mt-2 text-xs">{savedBank.bank_name} · {savedBank.account_holder} · {savedBank.masked_account_number}</p>}<Button className="mt-4" type="submit" disabled={busy || !savedBank}>{busy ? 'Đang gửi yêu cầu…' : 'Gửi yêu cầu rút tiền'}</Button></form></div>
      </section>
      <section className="mt-8 grid gap-5 lg:grid-cols-2"><article className="rounded-2xl border border-black/10 p-5"><h2 className="font-bold">Lịch sử sổ giao dịch</h2>{data.transactions.length === 0 ? <p className="mt-4 text-sm text-black/50">Chưa có giao dịch.</p> : <ul className="mt-3 divide-y divide-black/10">{data.transactions.map((transaction) => <li key={transaction.reference} className="flex items-start justify-between gap-3 py-3 text-sm"><div><p className="font-medium">{typeLabel[transaction.type] ?? 'Giao dịch ví'}{transaction.mode === 'sandbox' ? ' · Sandbox' : ''}</p><p className="mt-1 font-mono text-[10px] text-black/45">{transaction.reference}</p>{transaction.created_at && <time className="mt-1 block text-xs text-black/45">{new Date(transaction.created_at).toLocaleString('vi-VN')}</time>}</div><strong>{money(Math.abs(transaction.amount))}</strong></li>)}</ul>}</article>
        <article className="rounded-2xl border border-black/10 p-5"><h2 className="font-bold">Yêu cầu nạp VNPay Sandbox</h2>{data.deposits.length === 0 ? <p className="mt-4 text-sm text-black/50">Chưa có yêu cầu nạp Sandbox.</p> : <ul className="mt-3 divide-y divide-black/10">{data.deposits.map((item) => <li key={item.reference} className="py-3"><div className="flex justify-between gap-3 text-sm"><span className="font-medium">{depositStatus[item.status] ?? 'Đang chờ quản trị viên kiểm tra'} · Sandbox</span><strong>{money(item.amount)}</strong></div><p className="mt-1 font-mono text-[10px] text-black/45">{item.reference}</p><p className="mt-1 text-xs text-black/50">{item.paid_at ? new Date(item.paid_at).toLocaleString('vi-VN') : item.expires_at ? `Hết hạn ${new Date(item.expires_at).toLocaleString('vi-VN')}` : ''}</p>{item.resolution_note && <p className="mt-1 text-xs text-amber-800">{item.resolution_note}</p>}</li>)}</ul>}</article>
        <article className="rounded-2xl border border-black/10 p-5"><h2 className="font-bold">Yêu cầu rút tiền</h2>{data.withdrawals.length === 0 ? <p className="mt-4 text-sm text-black/50">Chưa có yêu cầu.</p> : <ul className="mt-3 divide-y divide-black/10">{data.withdrawals.map((item) => <li key={item.id} className="py-3"><div className="flex justify-between gap-3 text-sm"><span className="font-medium">{withdrawalStatus[item.status] ?? 'Đang xử lý'}</span><strong>{money(item.amount)}</strong></div><p className="mt-1 font-mono text-[10px] text-black/45">{item.reference}</p>{item.decision_reason && <p className="mt-1 text-xs text-black/60">Lý do: {item.decision_reason}</p>}{item.status === 'pending_review' && <button className="mt-2 text-xs font-semibold text-red-700 underline disabled:opacity-50" disabled={busy} onClick={() => void cancelWithdrawal(item.id)}>Hủy yêu cầu để đổi tài khoản nhận</button>}</li>)}</ul>}</article></section>
    </>}
  </main>
}
