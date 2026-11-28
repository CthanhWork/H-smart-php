import { useEffect, useState } from 'react'
import { ArrowLeft, ShoppingBag, Trash2 } from 'lucide-react'
import ProductCard from './components/ProductCard'
import { productRequest, type Product } from './productApi'
import { Button } from './components/ui/button'
import { Alert } from './components/ui/alert'

type CartEntry = { id: number; available: boolean; product: Product }
type CheckoutQuote = { product_amount: number; shipping_fee: number; total_amount: number; cod_deposit_percent: number; cod_deposit_amount: number; cod_remaining_amount: number }
type Props = { accessToken: string; navigate: (path: string) => void }
const money = (amount: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount)

export default function CartPage({ accessToken, navigate }: Props) {
  const [items, setItems] = useState<CartEntry[]>([])
  const [loading, setLoading] = useState(true)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [recipient, setRecipient] = useState('')
  const [phone, setPhone] = useState('')
  const [address, setAddress] = useState('')
  const [acceptedPolicy, setAcceptedPolicy] = useState(false)
  const [paymentMethod, setPaymentMethod] = useState<'vnpay' | 'cod'>('vnpay')
  const [quoteForProduct, setQuoteForProduct] = useState<number | null>(null)
  const [quote, setQuote] = useState<CheckoutQuote | null>(null)
  const [idempotencyKeys, setIdempotencyKeys] = useState<Record<number, string>>({})

  async function load() {
    setLoading(true); setError('')
    try { setItems((await productRequest<CartEntry[]>('me/cart', accessToken)).data) }
    catch (cause) { setError((cause as Error).message) }
    finally { setLoading(false) }
  }
  useEffect(() => { void load() }, [accessToken])

  async function remove(item: CartEntry) {
    setBusyId(item.id); setError('')
    try { await productRequest<null>(`me/cart/${item.id}`, accessToken, { method: 'DELETE' }); setItems((current) => current.filter((entry) => entry.id !== item.id)) }
    catch (cause) { setError((cause as Error).message) }
    finally { setBusyId(null) }
  }

  async function beginCheckout(item: CartEntry) {
    setBusyId(item.id); setError(''); setNotice('')
    try {
      const result = await productRequest<CheckoutQuote>(`orders/quote?product_id=${item.product.id}`, accessToken)
      setQuote(result.data); setQuoteForProduct(item.product.id)
    } catch (cause) { setError((cause as Error).message) }
    finally { setBusyId(null) }
  }

  async function createOrder(item: CartEntry) {
    const recipientValue = recipient.trim()
    const phoneValue = phone.trim()
    const addressValue = address.trim()
    if (recipientValue.length < 2 || recipientValue.length > 150) { setError('Tên người nhận cần từ 2 đến 150 ký tự.'); return }
    if (phoneValue.length < 8 || phoneValue.length > 30) { setError('Số điện thoại cần từ 8 đến 30 ký tự.'); return }
    if (addressValue.length < 5 || addressValue.length > 500) { setError('Địa chỉ cần từ 5 đến 500 ký tự.'); return }
    if (!acceptedPolicy) { setError('Hãy đọc và đồng ý với biểu phí và chính sách thanh toán trước khi đặt.'); return }
    if (!quote || quoteForProduct !== item.product.id) { setError('Lấy lại báo giá hiện hành trước khi đặt mua.'); return }
    setBusyId(item.id); setError(''); setNotice('')
    try {
      const result = await productRequest<{ id: number }>('orders', accessToken, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({
        product_id: item.product.id, expected_price: item.product.price, idempotency_key: idempotencyKeys[item.product.id] ?? crypto.randomUUID(),
        recipient_name: recipientValue, phone: phoneValue, address: addressValue, payment_method: paymentMethod, accept_policy: acceptedPolicy,
        expected_shipping_fee: quote.shipping_fee, expected_cod_deposit_percent: quote.cod_deposit_percent,
      }) })
      await productRequest<null>(`me/cart/${item.id}`, accessToken, { method: 'DELETE' })
      setNotice(`Đã tạo đơn #${result.data.id}. Mỗi sản phẩm được thanh toán thành một đơn riêng.`)
      setIdempotencyKeys((current) => { const next = { ...current }; delete next[item.product.id]; return next })
      setItems((current) => current.filter((entry) => entry.id !== item.id))
      navigate('/me/orders')
    } catch (cause) { setError((cause as Error).message); await load() }
    finally { setBusyId(null) }
  }

  return <main className="mx-auto max-w-frame px-4 py-8 sm:py-12">
    <button onClick={() => navigate('/products')} className="mb-5 inline-flex items-center gap-2 text-sm text-black/55 hover:text-black"><ArrowLeft size={16} /> Tiếp tục mua sắm</button>
    <h1 className="text-3xl font-bold">Giỏ hàng</h1><p className="mt-2 text-sm text-black/55">Sản phẩm chưa được giữ chỗ khi nằm trong giỏ. Mỗi sản phẩm tạo một đơn và thanh toán riêng.</p>
    {error && <Alert variant="error" className="mt-5">{error}</Alert>}{notice && <Alert variant="success" className="mt-5">{notice}</Alert>}
    {loading ? <p className="py-12 text-sm text-black/50" role="status">Đang tải giỏ hàng…</p> : items.length === 0 ? <div className="mt-8 rounded-2xl border border-dashed border-black/20 p-10 text-center"><ShoppingBag size={30} className="mx-auto text-black/35"/><h2 className="mt-3 font-semibold">Giỏ hàng đang trống</h2><Button className="mt-5" onClick={() => navigate('/products')}>Tìm sản phẩm</Button></div> : <>
      <section className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{items.map((item) => <article key={item.id} className="rounded-2xl border border-black/10 p-3">
        <ProductCard product={item.product} />
        {!item.available && <p className="mt-3 rounded-lg bg-amber-50 p-2 text-xs text-amber-900">Sản phẩm không còn khả dụng. Xóa khỏi giỏ để tiếp tục.</p>}
        <div className="mt-3 flex items-center justify-between"><strong>{money(item.product.price)}</strong><Button variant="outline" disabled={busyId === item.id} onClick={() => void remove(item)}><Trash2 size={15}/> Xóa</Button></div>
        {item.available && <Button className="mt-3 w-full" variant={quoteForProduct === item.product.id ? 'outline' : 'default'} disabled={busyId === item.id} onClick={() => { if (!idempotencyKeys[item.product.id]) setIdempotencyKeys((current) => ({ ...current, [item.product.id]: crypto.randomUUID() })); void beginCheckout(item) }}>{busyId === item.id ? 'Đang lấy phí…' : quoteForProduct === item.product.id ? 'Cập nhật phí và xác nhận' : 'Xem phí và tiếp tục'}</Button>}
        {quoteForProduct === item.product.id && quote && <div className="mt-3 space-y-2 rounded-xl bg-slate-50 p-3 text-sm"><div className="flex justify-between gap-3"><span>Tiền sản phẩm</span><strong>{money(quote.product_amount)}</strong></div><div className="flex justify-between gap-3"><span>Giao hàng</span><strong>{money(quote.shipping_fee)}</strong></div>{paymentMethod === 'cod' && <><div className="flex justify-between gap-3"><span>Cọc trước ({quote.cod_deposit_percent}%)</span><strong>{money(quote.cod_deposit_amount)}</strong></div><div className="flex justify-between gap-3"><span>Còn thu khi nhận hàng</span><strong>{money(quote.cod_remaining_amount)} + {money(quote.shipping_fee)} giao hàng</strong></div></>}<div className="flex justify-between gap-3 border-t pt-2"><span>Tổng đơn</span><strong>{money(quote.total_amount)}</strong></div><Button className="mt-2 w-full" disabled={busyId === item.id || !acceptedPolicy} onClick={() => void createOrder(item)}>{busyId === item.id ? 'Đang tạo đơn…' : paymentMethod === 'cod' ? `Tạo đơn và đặt cọc ${money(quote.cod_deposit_amount)}` : 'Tạo đơn và thanh toán'}</Button></div>}
      </article>)}</section>
      <section className="mt-8 max-w-xl rounded-2xl border border-black/10 p-5"><h2 className="font-bold">Thông tin nhận hàng</h2><p className="mt-1 text-xs text-black/55">Đơn được tạo riêng cho từng sản phẩm trong giỏ.</p>
        <div className="mt-4 grid gap-3"><label className="text-sm font-medium">Phương thức thanh toán<select className="mt-1 w-full rounded-xl border border-black/15 bg-white px-4 py-3 text-sm" value={paymentMethod} onChange={(e) => { setPaymentMethod(e.target.value as 'vnpay' | 'cod'); setQuote(null); setQuoteForProduct(null) }}><option value="vnpay">VNPay</option><option value="cod">COD có đặt cọc</option></select></label><label className="text-sm font-medium">Người nhận<input aria-label="Người nhận" required minLength={2} maxLength={150} className="mt-1 w-full rounded-xl border border-black/15 px-4 py-3 text-sm" placeholder="Người nhận" value={recipient} onChange={(e) => setRecipient(e.target.value)}/></label><label className="text-sm font-medium">Số điện thoại<input aria-label="Số điện thoại" type="tel" required minLength={8} maxLength={30} inputMode="tel" className="mt-1 w-full rounded-xl border border-black/15 px-4 py-3 text-sm" placeholder="Số điện thoại" value={phone} onChange={(e) => setPhone(e.target.value)}/></label><label className="text-sm font-medium">Địa chỉ nhận hàng<textarea aria-label="Địa chỉ nhận hàng" required minLength={5} maxLength={500} className="mt-1 w-full rounded-xl border border-black/15 px-4 py-3 text-sm" placeholder="Địa chỉ nhận hàng" rows={3} value={address} onChange={(e) => setAddress(e.target.value)}/></label><label className="flex items-start gap-2 text-sm"><input type="checkbox" checked={acceptedPolicy} onChange={(e) => setAcceptedPolicy(e.target.checked)} className="mt-1"/><span>Tôi đồng ý với <a className="underline" href="/policies/fees" target="_blank" rel="noreferrer">biểu phí</a> và <a className="underline" href="/policies/payment" target="_blank" rel="noreferrer">điều khoản thanh toán/đặt cọc/hoàn tiền</a>.</span></label></div>
      </section>
    </>}
  </main>
}
