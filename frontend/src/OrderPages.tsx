import { useEffect, useState, type FormEvent } from 'react'
import { ArrowLeft, ArrowRight, PackageCheck, Star } from 'lucide-react'
import { productRequest, type Product } from './productApi'
import { Button } from './components/ui/button'
import { Alert } from './components/ui/alert'
import { Badge } from './components/ui/badge'

type Offer = {
  id: number; offer_price: number; status: string; product: Product
  buyer: { id: number; full_name: string | null }; seller: { id: number; full_name: string | null }
}
type Order = {
  id: number; product_amount: number; shipping_fee: number; total_amount: number; status: string
  shipping_address: { recipient_name: string; phone: string; address: string }
  expires_at: string; paid_at: string | null; shipped_at: string | null; completed_at: string | null
  created_at: string; product: Product; buyer: { id: number; full_name: string | null }
  seller: { id: number; full_name: string | null }; offer_id: number
  payment: { id: number; txn_ref: string; provider: string; amount: number; status: string; paid_at: string | null }
  review: { id: number; rating: number; comment: string | null } | null
}
type OrderPage = { content: Order[]; pageNo: number; pageSize: number; totalElements: number; totalPages: number; last: boolean }
type Props = { accessToken: string; userId: number; navigate: (path: string) => void; mode: 'list' | 'create' }
const money = (amount: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount)
const labels: Record<string, string> = {
  pending_payment: 'Chờ thanh toán', paid: 'Đã thanh toán', processing: 'Đang chuẩn bị hàng',
  shipping: 'Đang giao', completed: 'Hoàn tất', cancelled: 'Đã hủy',
}
const statusVariant: Record<string, 'warning' | 'success' | 'default' | 'destructive' | 'outline'> = {
  pending_payment: 'warning', paid: 'success', processing: 'default', shipping: 'default', completed: 'success', cancelled: 'destructive',
}
const fieldClass = 'mt-1.5 w-full rounded-2xl border border-black/15 bg-white px-4 py-3 text-sm outline-none transition focus:border-black'

export default function OrderPages({ accessToken, userId, navigate, mode }: Props) {
  const offerId = mode === 'create' ? new URLSearchParams(window.location.search).get('offerId') : null
  const [orders, setOrders] = useState<Order[]>([])
  const [order, setOrder] = useState<Order | null>(null)
  const [offer, setOffer] = useState<Offer | null>(null)
  const [recipientName, setRecipientName] = useState('')
  const [phone, setPhone] = useState('')
  const [address, setAddress] = useState('')
  const [loading, setLoading] = useState(mode === 'list' || Boolean(offerId))
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [reviewRating, setReviewRating] = useState('5')
  const [reviewComment, setReviewComment] = useState('')
  const [version, setVersion] = useState(0)

  useEffect(() => {
    let cancelled = false
    if (mode === 'create' && offerId) {
      productRequest<Offer>(`offers/${offerId}`, accessToken)
        .then(({ data }) => {
          if (cancelled) return
          setOffer(data)
          setRecipientName(data.buyer.full_name ?? '')
          if (data.status !== 'accepted') setError('Bạn chỉ có thể đặt hàng sau khi người bán chấp nhận đề nghị.')
        })
        .catch((cause: Error) => { if (!cancelled) setError(cause.message) })
        .finally(() => { if (!cancelled) setLoading(false) })
    } else if (mode === 'list') {
      productRequest<OrderPage>('orders?page=1', accessToken)
        .then(({ data }) => { if (!cancelled) setOrders(data.content) })
        .catch((cause: Error) => { if (!cancelled) setError(cause.message) })
        .finally(() => { if (!cancelled) setLoading(false) })
    }
    return () => { cancelled = true }
  }, [accessToken, mode, offerId, version])

  async function createOrder(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!offer || !offerId) return
    setBusy(true); setError(''); setNotice('')
    try {
      const result = await productRequest<Order>('orders', accessToken, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ offer_id: Number(offerId), recipient_name: recipientName.trim(), phone: phone.trim(), address: address.trim() }),
      })
      setOrder(result.data); setNotice(result.message)
    } catch (cause) { setError((cause as Error).message) }
    finally { setBusy(false) }
  }

  async function act(orderId: number, action: 'simulate-payment' | 'cancel' | 'confirm' | 'ship' | 'complete') {
    setBusy(true); setLoading(mode === 'list'); setError(''); setNotice('')
    try {
      const result = await productRequest<Order>(`orders/${orderId}/${action}`, accessToken, { method: 'POST' })
      setNotice(result.message)
      if (mode === 'create') setOrder(result.data)
      else setVersion((value) => value + 1)
    } catch (cause) { setError((cause as Error).message) }
    finally { setBusy(false) }
  }

  async function submitReview(event: FormEvent<HTMLFormElement>, orderId: number) {
    event.preventDefault(); setBusy(true); setError(''); setNotice('')
    try {
      const result = await productRequest<unknown>(`orders/${orderId}/review`, accessToken, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ rating: Number(reviewRating), comment: reviewComment.trim() }),
      })
      setNotice(result.message); setReviewComment(''); setVersion((value) => value + 1)
    } catch (cause) { setError((cause as Error).message) }
    finally { setBusy(false) }
  }

  function actions(item: Order) {
    const buyer = item.buyer.id === userId
    if (item.status === 'pending_payment' && buyer) return <>
      <Button disabled={busy} onClick={() => act(item.id, 'simulate-payment')}>Thanh toán thử</Button>
      <Button variant="outline" disabled={busy} onClick={() => act(item.id, 'cancel')}>Hủy đơn</Button>
    </>
    if (item.status === 'paid' && !buyer) return <Button disabled={busy} onClick={() => act(item.id, 'confirm')}>Xác nhận đơn</Button>
    if (item.status === 'processing' && !buyer) return <Button disabled={busy} onClick={() => act(item.id, 'ship')}>Xác nhận gửi hàng</Button>
    if (item.status === 'shipping' && buyer) return <Button disabled={busy} onClick={() => act(item.id, 'complete')}>Đã nhận hàng</Button>
    return null
  }

  function card(item: Order) {
    return <article className="rounded-[20px] border border-black/10 bg-white p-5 shadow-sm sm:p-7" key={item.id}>
      <div className="flex flex-col gap-3 border-b border-black/10 pb-5 sm:flex-row sm:items-start sm:justify-between">
        <div><p className="text-xs font-medium uppercase tracking-[0.16em] text-black/50">Đơn hàng #{item.id}</p><h2 className="mt-2 text-xl font-bold sm:text-2xl">{item.product.title}</h2></div>
        <Badge variant={statusVariant[item.status] ?? 'outline'}>{labels[item.status] ?? item.status}</Badge>
      </div>
      <dl className="grid gap-x-8 gap-y-4 py-5 text-sm sm:grid-cols-2">
        <div><dt className="text-black/50">Người mua</dt><dd className="mt-1 font-medium">{item.buyer.full_name || 'Thành viên'}</dd></div>
        <div><dt className="text-black/50">Người bán</dt><dd className="mt-1 font-medium">{item.seller.full_name || 'Thành viên'}</dd></div>
        <div><dt className="text-black/50">Giá đã thỏa thuận</dt><dd className="mt-1 font-medium">{money(item.product_amount)}</dd></div>
        <div><dt className="text-black/50">Tổng thanh toán</dt><dd className="mt-1 font-bold">{money(item.total_amount)}</dd></div>
        <div className="sm:col-span-2"><dt className="text-black/50">Giao đến</dt><dd className="mt-1 font-medium">{item.shipping_address.recipient_name}, {item.shipping_address.phone}, {item.shipping_address.address}</dd></div>
        <div><dt className="text-black/50">Thanh toán</dt><dd className="mt-1 font-medium">{item.payment.provider === 'mock' ? 'Mô phỏng' : item.payment.provider} · {item.payment.status === 'paid' ? 'Đã thanh toán' : item.payment.status === 'pending' ? 'Chưa thanh toán' : item.payment.status}</dd></div>
        {item.status === 'pending_payment' && <div><dt className="text-black/50">Giữ sản phẩm đến</dt><dd className="mt-1 font-medium">{new Date(item.expires_at).toLocaleString('vi-VN')}</dd></div>}
      </dl>
      {item.review && <div className="rounded-2xl bg-[#F0F0F0] p-4 text-sm"><p className="font-medium">Đánh giá của bạn <span className="ml-2 text-amber-500">{'★'.repeat(item.review.rating)}{'☆'.repeat(5 - item.review.rating)}</span></p>{item.review.comment && <p className="mt-1 text-black/70">{item.review.comment}</p>}</div>}
      {item.status === 'completed' && item.buyer.id === userId && !item.review && <form className="mt-5 rounded-2xl bg-[#F0F0F0] p-4 sm:p-5" onSubmit={(event) => submitReview(event, item.id)}>
        <h3 className="flex items-center gap-2 font-bold"><Star size={17} /> Đánh giá món hàng</h3>
        <label className="mt-4 block text-sm font-medium">Số sao<select className={fieldClass} value={reviewRating} onChange={(event) => setReviewRating(event.target.value)}>{[5, 4, 3, 2, 1].map((rating) => <option key={rating} value={rating}>{rating} sao</option>)}</select></label>
        <label className="mt-4 block text-sm font-medium">Nhận xét (không bắt buộc)<textarea className={fieldClass} maxLength={1000} rows={3} value={reviewComment} onChange={(event) => setReviewComment(event.target.value)} /></label>
        <Button className="mt-4" disabled={busy}>Gửi đánh giá</Button>
      </form>}
      {mode === 'list' && <div className="mt-5 flex flex-wrap gap-2">{actions(item)}</div>}
    </article>
  }

  return <main className="max-w-frame mx-auto min-h-[55vh] px-4 py-10 xl:px-0">
    <button className="mb-6 inline-flex items-center gap-2 text-sm text-black/55 transition hover:text-black" onClick={() => navigate(mode === 'create' ? '/me/offers' : '/')}><ArrowLeft size={16} />{mode === 'create' ? 'Quay lại đề nghị mua' : 'Về tài khoản'}</button>
    <div className="mb-3 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div><p className="text-xs font-medium uppercase tracking-[0.16em] text-black/50">Giao dịch</p><h1 className="mt-2 font-satoshi font-bold tracking-tight text-2xl uppercase sm:text-4xl">{mode === 'create' ? 'Tạo đơn hàng' : 'Đơn hàng của tôi'}</h1></div>
      {mode === 'create' && <Button variant="outline" onClick={() => navigate('/me/orders')}>Xem đơn hàng <ArrowRight className="ml-2" size={16} /></Button>}
    </div>
    <p className="mb-6 text-sm text-black/55">Đây là bước thanh toán thử nghiệm, chưa trừ tiền thật.</p>
    {notice && <Alert variant="success" className="mb-4" role="status">{notice}</Alert>}
    {error && <Alert variant="error" className="mb-4">{error}</Alert>}
    {mode === 'create' && !offerId && <Alert variant="error" className="mb-4">Thiếu mã đề nghị mua.</Alert>}
    {loading && <p className="py-8 text-sm text-black/55">Đang tải đơn hàng…</p>}
    {mode === 'create' && !loading && !order && offer?.status === 'accepted' && <form className="max-w-2xl rounded-[20px] border border-black/10 bg-white p-5 shadow-sm sm:p-8" onSubmit={createOrder}>
      <div className="mb-6 border-b border-black/10 pb-5"><p className="text-xs uppercase tracking-wider text-black/50">Sản phẩm đã thỏa thuận</p><h2 className="mt-2 text-2xl font-bold">{offer.product.title}</h2><p className="mt-2 text-sm text-black/60">Giá người bán đã chấp nhận: <strong className="text-black">{money(offer.offer_price)}</strong></p></div>
      <label className="mb-4 block text-sm font-medium">Người nhận<input className={fieldClass} required minLength={2} maxLength={150} value={recipientName} onChange={(event) => setRecipientName(event.target.value)} /></label>
      <label className="mb-4 block text-sm font-medium">Số điện thoại<input className={fieldClass} type="tel" required minLength={8} maxLength={30} value={phone} onChange={(event) => setPhone(event.target.value)} /></label>
      <label className="block text-sm font-medium">Địa chỉ nhận hàng<textarea className={fieldClass} required minLength={5} maxLength={500} rows={3} value={address} onChange={(event) => setAddress(event.target.value)} /></label>
      <p className="my-5 rounded-xl bg-[#F0F0F0] p-4 text-sm text-black/60">Sau khi tạo đơn, sản phẩm sẽ được giữ trong 30 phút để bạn thanh toán. Hiện chưa tính phí giao hàng.</p>
      <Button className="w-full sm:w-auto" type="submit" disabled={busy}>{busy ? 'Đang tạo đơn…' : 'Tạo đơn và giữ sản phẩm'}</Button>
    </form>}
    {mode === 'create' && order && <div className="space-y-4">{card(order)}<div className="flex flex-wrap gap-2">{actions(order)}<Button variant="outline" onClick={() => navigate('/me/orders')}>Tất cả đơn hàng</Button></div></div>}
    {mode === 'list' && !loading && <div className="space-y-4">{orders.length ? orders.map(card) : <div className="rounded-[20px] border border-dashed border-black/20 bg-white p-10 text-center text-sm text-black/55"><PackageCheck className="mx-auto mb-3" size={28} />Chưa có đơn hàng nào.</div>}</div>}
  </main>
}
