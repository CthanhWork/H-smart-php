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
  expires_at: string; paid_at: string | null; confirmed_at: string | null; shipped_at: string | null; completed_at: string | null
  shipping_carrier: string | null; tracking_number: string | null
  platform_fee: number; commission_rate: number; delivery_status: string | null; payout_status: string
  release_eligible_at: string | null
  returned_at: string | null; buyer_confirmed_at: string | null
  delivery_failed_reason: string | null
  cod_collected_at: string | null; cod_deposit_amount: number; cod_deposit_percent: number | null; cod_remaining_amount: number; cod_remaining_collected_at: string | null; cod_remaining_collection_type: string | null; cod_deposit_wallet_consent_at: string | null; carrier_settled_at: string | null; seller_paid_at: string | null
  created_at: string; cancelled_at: string | null; product: Product; buyer: { id: number; full_name: string | null }
  seller: { id: number; full_name: string | null }; offer_id: number
  payment: { id: number; txn_ref: string | null; provider: string; method: string; amount: number; status: string; paid_at: string | null } | null
  review: { id: number; rating: number; comment: string | null } | null
  status_history: Array<{ from_status: string | null; to_status: string; changed_at: string }>
  disputes?: Array<{ id: number; status: string; reason: string; details: string; evidence_notes: string[] | null; resolution: string | null; resolution_note: string | null; buyer_wallet_refund_consent_at: string | null; created_at: string; events: Array<{ action: string; note: string | null; created_at: string }> }>
}
type OrderPage = { content: Order[]; pageNo: number; pageSize: number; totalElements: number; totalPages: number; last: boolean }
type OrderQuote = { product_amount: number; shipping_fee: number; total_amount: number; commission_rate: number; platform_fee: number; seller_amount: number; cod_deposit_percent: number; cod_deposit_amount: number; cod_remaining_amount: number }
type VietQrInstruction = { provider: string; reference: string; amount: number; bank_id: string; account_number: string; account_name: string; qr_url: string; confirmation: string }
type Props = { accessToken: string; userId: number; navigate: (path: string) => void; mode: 'list' | 'create' }
const money = (amount: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount)
const labels: Record<string, string> = {
  pending_payment: 'Chờ thanh toán', paid: 'Đã thanh toán', processing: 'Đang chuẩn bị hàng',
  shipping: 'Đang giao', completed: 'Hoàn tất', cancelled: 'Đã hủy',
}
const statusVariant: Record<string, 'warning' | 'success' | 'default' | 'destructive' | 'outline'> = {
  pending_payment: 'warning', paid: 'success', processing: 'default', shipping: 'default', completed: 'success', cancelled: 'destructive',
}
const orderStages = ['pending_payment', 'paid', 'processing', 'shipping', 'completed']
const fieldClass = 'mt-1.5 w-full rounded-2xl border border-black/15 bg-white px-4 py-3 text-sm outline-none transition focus:border-black'

export default function OrderPages({ accessToken, userId, navigate, mode }: Props) {
  const offerId = mode === 'create' ? new URLSearchParams(window.location.search).get('offerId') : null
  const [orders, setOrders] = useState<Order[]>([])
  const [order, setOrder] = useState<Order | null>(null)
  const [offer, setOffer] = useState<Offer | null>(null)
  const [quote, setQuote] = useState<OrderQuote | null>(null)
  const [recipientName, setRecipientName] = useState('')
  const [phone, setPhone] = useState('')
  const [address, setAddress] = useState('')
  const [paymentMethod, setPaymentMethod] = useState<'vnpay' | 'cod'>('vnpay')
  const [acceptedPolicy, setAcceptedPolicy] = useState(false)
  const [loading, setLoading] = useState(mode === 'list' || Boolean(offerId))
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [reviewRating, setReviewRating] = useState('5')
  const [reviewComment, setReviewComment] = useState('')
  const [shipments, setShipments] = useState<Record<number, { carrier: string; tracking: string }>>({})
  const [version, setVersion] = useState(0)
  const [page, setPage] = useState(1)
  const [totalPages, setTotalPages] = useState(1)
  const [totalOrders, setTotalOrders] = useState(0)
  const [disputeDrafts, setDisputeDrafts] = useState<Record<number, { reason: string; details: string; evidence: string }>>({})
  const [vietQr, setVietQr] = useState<VietQrInstruction | null>(null)

  useEffect(() => {
    const paymentResult = new URLSearchParams(window.location.search).get('payment')
    if (paymentResult === 'mock_success') setNotice('Thanh toán mô phỏng thành công. Đơn hàng đã được cập nhật; không có tiền thật được chuyển.')
    else if (paymentResult === 'mock_failed') setError('Thanh toán mô phỏng đã thất bại hoặc bị hủy. Bạn có thể thử lại từ đơn hàng.')
    else if (paymentResult === 'success') setNotice('Thanh toán VNPay Sandbox thành công. Đơn hàng đã được cập nhật.')
    else if (paymentResult === 'processing') setNotice('VNPay đã trả kết quả. Đang đồng bộ trạng thái giao dịch, vui lòng tải lại đơn hàng sau ít giây nếu trạng thái chưa đổi.')
    else if (paymentResult === 'failed') setError('Thanh toán chưa thành công. Bạn có thể thử thanh toán lại từ đơn hàng.')
    else if (paymentResult === 'invalid') setError('Không xác minh được kết quả thanh toán. Vui lòng kiểm tra trạng thái đơn hàng hoặc liên hệ hỗ trợ.')
  }, [])

  useEffect(() => {
    let cancelled = false
    if (mode === 'create' && offerId) {
      const profilePromise = productRequest<{ full_name: string | null; phone_number: string | null; shipping_address: string | null }>('me/profile', accessToken)
        .then(({ data }) => data)
        .catch(() => null)
      Promise.all([productRequest<Offer>(`offers/${offerId}`, accessToken), profilePromise])
        .then(async ([{ data }, profile]) => {
          if (cancelled) return
          setOffer(data)
          setRecipientName(profile?.full_name || data.buyer.full_name || '')
          setPhone(profile?.phone_number ?? '')
          setAddress(profile?.shipping_address ?? '')
          if (data.status === 'ordered') {
            const { data: orderPage } = await productRequest<OrderPage>(`orders?offer_id=${offerId}`, accessToken)
            if (cancelled) return
            const existingOrder = orderPage.content[0]
            if (existingOrder) setOrder(existingOrder)
            else setError('Không tìm thấy đơn hàng đã tạo cho đề nghị này.')
          } else if (data.status !== 'accepted') {
            setError('Bạn chỉ có thể đặt hàng sau khi người bán chấp nhận đề nghị.')
          } else {
            const quoteResult = await productRequest<OrderQuote>(`orders/quote?offer_id=${data.id}`, accessToken)
            if (!cancelled) setQuote(quoteResult.data)
          }
        })
        .catch((cause: Error & { status?: number }) => {
          if (!cancelled) setError(cause.status === 404 ? 'Không tìm thấy đề nghị mua hoặc đề nghị không còn tồn tại.' : cause.message)
        })
        .finally(() => { if (!cancelled) setLoading(false) })
    } else if (mode === 'list') {
      setLoading(true)
      productRequest<OrderPage>(`orders?page=${page}`, accessToken)
        .then(({ data }) => { if (!cancelled) { setOrders(data.content); setTotalPages(data.totalPages); setTotalOrders(data.totalElements) } })
        .catch((cause: Error) => { if (!cancelled) setError(cause.message) })
        .finally(() => { if (!cancelled) setLoading(false) })
    }
    return () => { cancelled = true }
  }, [accessToken, mode, offerId, version, page])

  async function createOrder(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!offer || !offerId) return
    setBusy(true); setError(''); setNotice('')
    try {
      const result = await productRequest<Order>('orders', accessToken, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ offer_id: Number(offerId), recipient_name: recipientName.trim(), phone: phone.trim(), address: address.trim(), payment_method: paymentMethod, accept_policy: acceptedPolicy, expected_shipping_fee: quote?.shipping_fee, expected_commission_rate: quote?.commission_rate, expected_cod_deposit_percent: quote?.cod_deposit_percent }),
      })
      setOrder(result.data); setNotice(result.message)
    } catch (cause) { setError((cause as Error).message) }
    finally { setBusy(false) }
  }

  async function pay(orderId: number) {
    setBusy(true); setError(''); setNotice('')
    try {
      const result = await productRequest<{ payment_url: string }>(`orders/${orderId}/payment`, accessToken, { method: 'POST' })
      window.location.assign(result.data.payment_url)
    } catch (cause) { setError((cause as Error).message); setBusy(false) }
  }

  async function createVietQr(orderId: number) {
    setBusy(true); setError(''); setNotice('')
    try { const result = await productRequest<VietQrInstruction>(`orders/${orderId}/vietqr`, accessToken, { method: 'POST' }); setVietQr(result.data); setNotice(result.message) }
    catch (cause) { setError((cause as Error).message) }
    finally { setBusy(false) }
  }

  async function act(orderId: number, action: 'cancel' | 'confirm' | 'ship' | 'complete', shipment?: { shipping_carrier: string; tracking_number: string }) {
    setBusy(true); setLoading(mode === 'list'); setError(''); setNotice('')
    try {
      const result = await productRequest<Order>(`orders/${orderId}/${action}`, accessToken, {
        method: 'POST',
        ...(shipment ? { headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(shipment) } : {}),
      })
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

  async function openDispute(event: FormEvent<HTMLFormElement>, orderId: number) {
    event.preventDefault(); const draft = disputeDrafts[orderId]; if (!draft) return
    setBusy(true); setError(''); setNotice('')
    try {
      const evidence_notes = draft.evidence.split('\n').map((line) => line.trim()).filter(Boolean).slice(0, 5)
      const result = await productRequest<unknown>(`orders/${orderId}/disputes`, accessToken, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ reason: draft.reason.trim(), details: draft.details.trim(), evidence_notes }) })
      setNotice(result.message); setDisputeDrafts((current) => { const next = { ...current }; delete next[orderId]; return next }); setVersion((value) => value + 1)
    } catch (cause) { setError((cause as Error).message) }
    finally { setBusy(false) }
  }

  async function consentWalletRefund(orderId: number) {
    if (!window.confirm('Ghi nhận rằng bạn đồng ý nhận khoản hoàn vào số dư H-Smart nếu quản trị viên chấp thuận phương án này?')) return
    setBusy(true); setError(''); setNotice('')
    try {
      const result = await productRequest<unknown>(`orders/${orderId}/disputes/refund-consent`, accessToken, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accept: true }) })
      setNotice(result.message); setVersion((value) => value + 1)
    } catch (cause) { setError((cause as Error).message) }
    finally { setBusy(false) }
  }

  async function consentCodDepositWalletRefund(orderId: number) {
    if (!window.confirm('Đồng ý nhận lại tiền cọc COD vào số dư H-Smart mô phỏng nếu quản trị viên xác minh và chấp thuận?')) return
    setBusy(true); setError(''); setNotice('')
    try { const result = await productRequest<unknown>(`orders/${orderId}/cod-deposit-refund-consent`, accessToken, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accept: true }) }); setNotice(result.message); setVersion((value) => value + 1) }
    catch (cause) { setError((cause as Error).message) }
    finally { setBusy(false) }
  }

  function actions(item: Order) {
    const buyer = item.buyer.id === userId
    if (item.status === 'pending_payment' && buyer) return <>
      {(item.payment?.method === 'cod' ? item.cod_deposit_amount > 0 && item.payment.status !== 'paid' : item.payment?.provider !== 'cod') && <Button disabled={busy} onClick={() => void pay(item.id)}>{item.payment?.method === 'cod' ? `Thanh toán tiền cọc ${money(item.payment.amount)}` : 'Thanh toán qua VNPay'}</Button>}
      {item.payment?.status !== 'paid' && (item.payment?.method === 'cod' ? item.cod_deposit_amount > 0 : item.payment?.provider !== 'cod') && <Button variant="outline" disabled={busy} onClick={() => void createVietQr(item.id)}>Tạo mã VietQR</Button>}
      {!item.payment?.txn_ref && <Button variant="outline" disabled={busy} onClick={() => act(item.id, 'cancel')}>Hủy đơn</Button>}
    </>
    if (!buyer && (item.status === 'paid' || (item.status === 'pending_payment' && item.payment?.provider === 'cod' && item.cod_deposit_amount === 0))) return <Button disabled={busy} onClick={() => act(item.id, 'confirm')}>Xác nhận đơn</Button>
    if (item.status === 'processing' && !buyer) {
      const shipment = shipments[item.id] ?? { carrier: '', tracking: '' }
      return <form className="flex w-full flex-col gap-3 rounded-2xl bg-[#f6f7f8] p-4 sm:flex-row sm:items-end" onSubmit={(event) => { event.preventDefault(); void act(item.id, 'ship', { shipping_carrier: shipment.carrier.trim(), tracking_number: shipment.tracking.trim() }) }}>
        <label className="flex-1 text-xs font-medium text-black/60">Đơn vị vận chuyển<input required minLength={2} maxLength={80} className={fieldClass} placeholder="Ví dụ: GHN, Viettel Post" value={shipment.carrier} onChange={(event) => setShipments((current) => ({ ...current, [item.id]: { ...shipment, carrier: event.target.value } }))} /></label>
        <label className="flex-1 text-xs font-medium text-black/60">Mã vận đơn (nếu có)<input maxLength={80} className={fieldClass} value={shipment.tracking} onChange={(event) => setShipments((current) => ({ ...current, [item.id]: { ...shipment, tracking: event.target.value } }))} /></label>
        <Button type="submit" disabled={busy}>Xác nhận gửi hàng</Button>
      </form>
    }
    if (item.status === 'shipping' && buyer && item.payment?.method !== 'cod' && item.payment?.provider !== 'cod') return <Button disabled={busy} onClick={() => act(item.id, 'complete')}>Đã nhận hàng</Button>
    if (item.status === 'completed' && buyer && item.payment?.method === 'cod' && item.payment.status === 'paid' && item.payout_status !== 'credited_to_wallet' && !item.buyer_confirmed_at) return <Button disabled={busy} onClick={() => act(item.id, 'complete')}>Đã nhận hàng và đúng mô tả</Button>
    if (item.status === 'shipping' && !buyer && item.delivery_status === 'returned_to_seller') return <Button disabled={busy} onClick={async () => {
      setBusy(true); setError(''); setNotice('')
      try { const result = await productRequest<Order>(`orders/${item.id}/return-received`, accessToken, { method: 'POST' }); setNotice(result.message); setVersion((value) => value + 1) }
      catch (cause) { setError((cause as Error).message) }
      finally { setBusy(false) }
    }}>Xác nhận đã nhận hàng hoàn</Button>
    return null
  }

  function nextStep(item: Order) {
    const buyer = item.buyer.id === userId
    if (item.payment?.method === 'cod' && item.cod_deposit_amount > 0 && item.payment.status !== 'paid') return buyer ? 'Thanh toán tiền cọc để giữ đơn COD; người bán xác nhận sau khi cọc được xác minh.' : 'Đang chờ người mua thanh toán tiền cọc COD.'
    if (item.status === 'pending_payment' && item.payment?.provider === 'cod') return item.cod_deposit_amount > 0 ? (buyer ? 'Đơn COD đang chờ thanh toán tiền cọc.' : 'Đang chờ người mua đặt cọc.') : (buyer ? 'Đơn COD đang chờ người bán xác nhận.' : 'Xác nhận đơn COD để chuẩn bị hàng.')
    if (item.status === 'pending_payment') return buyer ? 'Thanh toán trước thời hạn giữ sản phẩm để tiếp tục đơn hàng.' : 'Đang chờ người mua thanh toán.'
    if (item.status === 'paid') return buyer ? 'Đang chờ người bán xác nhận đơn.' : 'Xác nhận đơn để bắt đầu chuẩn bị hàng.'
    if (item.status === 'processing') return buyer ? 'Người bán đang chuẩn bị hàng.' : 'Nhập thông tin vận chuyển để xác nhận gửi hàng.'
    if (item.status === 'shipping') return buyer ? (item.payment?.method === 'cod' || item.payment?.provider === 'cod' ? 'Đơn COD đang giao; phần còn lại thu khi nhận hàng. Tiền cọc được giữ riêng đến khi hết thời hạn khiếu nại.' : 'Khi hàng đã đến và đúng mô tả, hãy xác nhận để giải ngân cho người bán.') : 'Đơn hàng đang được vận chuyển.'
    if (item.status === 'completed') return buyer && !item.review ? 'Đơn đã hoàn tất. Bạn có thể để lại đánh giá cho sản phẩm.' : 'Giao dịch đã hoàn tất.'
    return 'Đơn hàng đã được hủy.'
  }

  function card(item: Order) {
    const currentStageIndex = orderStages.indexOf(item.status)
    return <article className="rounded-[20px] border border-black/10 bg-white p-5 shadow-sm sm:p-7" key={item.id}>
      <div className="flex flex-col gap-3 border-b border-black/10 pb-5 sm:flex-row sm:items-start sm:justify-between">
        <div><p className="text-xs font-medium uppercase tracking-[0.16em] text-black/50">Đơn hàng #{item.id}</p><h2 className="mt-2 text-xl font-bold sm:text-2xl">{item.product.title}</h2></div>
        <Badge variant={statusVariant[item.status] ?? 'outline'}>{item.status === 'pending_payment' && item.payment?.provider === 'cod' ? (item.cod_deposit_amount > 0 ? 'Chờ đặt cọc' : 'Chờ người bán xác nhận') : labels[item.status] ?? item.status}</Badge>
      </div>
      <dl className="grid gap-x-8 gap-y-4 py-5 text-sm sm:grid-cols-2">
        <div><dt className="text-black/50">Người mua</dt><dd className="mt-1 font-medium">{item.buyer.full_name || 'Thành viên'}</dd></div>
        <div><dt className="text-black/50">Người bán</dt><dd className="mt-1 font-medium">{item.seller.full_name || 'Thành viên'}</dd></div>
        <div><dt className="text-black/50">Giá đã thỏa thuận</dt><dd className="mt-1 font-medium">{money(item.product_amount)}</dd></div>
        <div><dt className="text-black/50">Tổng thanh toán</dt><dd className="mt-1 font-bold">{money(item.total_amount)}</dd></div>
        <div><dt className="text-black/50">Phí sàn ({item.commission_rate}%)</dt><dd className="mt-1 font-medium">{money(item.platform_fee)}</dd></div>
        <div className="sm:col-span-2"><dt className="text-black/50">Giao đến</dt><dd className="mt-1 font-medium">{item.shipping_address.recipient_name}, {item.shipping_address.phone}, {item.shipping_address.address}</dd></div>
        <div><dt className="text-black/50">Thanh toán</dt><dd className="mt-1 font-medium">{item.payment ? `${item.payment.provider === 'vnpay_mock' ? 'VNPay mô phỏng' : item.payment.provider === 'vnpay' ? 'VNPay' : item.payment.provider === 'vietqr' ? 'VietQR' : 'COD'} · ${item.payment.status === 'paid' ? 'Đã xác minh' : item.payment.status === 'pending' ? 'Chờ xác minh' : item.payment.status === 'failed' ? 'Chưa thành công' : item.payment.status}` : 'Chưa có thông tin thanh toán'}</dd></div>
        {(item.payment?.method === 'cod' || item.payment?.provider === 'cod') && <><div><dt className="text-black/50">Tiền cọc COD</dt><dd className="mt-1 font-medium">{money(item.cod_deposit_amount || item.payment.amount)}{item.cod_deposit_percent ? ` · ${item.cod_deposit_percent}%` : ''} · {item.payment.status === 'paid' ? 'Đã xác minh' : 'Chưa xác minh'}</dd></div><div><dt className="text-black/50">Còn thu khi nhận hàng</dt><dd className="mt-1 font-medium">{money(item.cod_remaining_amount)} · {item.cod_remaining_collected_at ? `Đã thu ${item.cod_remaining_collection_type === 'direct' ? 'trực tiếp' : 'qua đơn vị giao nhận'}` : 'Chưa thu'}</dd></div><div><dt className="text-black/50">Đối soát COD</dt><dd className="mt-1 font-medium">{item.seller_paid_at ? 'Tiền cọc đã giải ngân vào số dư' : item.cod_remaining_collection_type === 'direct' && item.cod_remaining_collected_at ? 'Đã thu trực tiếp; khoản này không được ghi có vào ví' : item.carrier_settled_at ? 'Đơn vị giao nhận đã đối soát' : 'Chờ xác nhận thu và đối soát'}{item.delivery_status === 'failed' ? ` · Giao thất bại: ${item.delivery_failed_reason || 'chưa rõ lý do'}` : item.delivery_status === 'returned_to_seller' ? ' · Đã hoàn về người bán' : ''}</dd></div></>}
        {item.status === 'pending_payment' && <div><dt className="text-black/50">Giữ sản phẩm đến</dt><dd className="mt-1 font-medium">{new Date(item.expires_at).toLocaleString('vi-VN')}</dd></div>}
        {item.release_eligible_at && item.payout_status !== 'credited_to_wallet' && <div><dt className="text-black/50">Thời điểm dự kiến đủ điều kiện giải ngân</dt><dd className="mt-1 font-medium">{new Date(item.release_eligible_at).toLocaleString('vi-VN')}</dd></div>}
        {item.shipping_carrier && <div><dt className="text-black/50">Đơn vị vận chuyển</dt><dd className="mt-1 font-medium">{item.shipping_carrier}</dd></div>}
        {item.tracking_number && <div><dt className="text-black/50">Mã vận đơn</dt><dd className="mt-1 font-mono font-medium">{item.tracking_number}</dd></div>}
      </dl>
      {(item.payment?.method === 'cod' || item.payment?.provider === 'cod') && item.status === 'cancelled' && item.delivery_status === 'returned_to_seller' && item.payment.status === 'paid' && !item.cod_deposit_wallet_consent_at && item.buyer.id === userId && <Button className="mb-4" variant="outline" disabled={busy} onClick={() => void consentCodDepositWalletRefund(item.id)}>Đồng ý nhận hoàn cọc vào số dư mô phỏng</Button>}
      <section className="mb-5 rounded-2xl bg-[#f8f8f8] p-4 sm:p-5"><h3 className="text-sm font-bold">Tiến trình đơn hàng</h3>{item.status === 'cancelled' ? <p className="mt-3 rounded-xl bg-red-50 px-3 py-2 text-sm font-medium text-red-800">Đơn hàng đã bị hủy.</p> : <ol className="mt-4 space-y-3" aria-label="Các bước của đơn hàng">{orderStages.map((status, index) => { const event = item.status_history?.find((entry) => entry.to_status === status); const done = index < currentStageIndex; const current = index === currentStageIndex; return <li key={status} aria-current={current ? 'step' : undefined} className="flex items-start gap-3 text-sm"><span className={`mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full text-[10px] font-bold ${done ? 'bg-[#2f7049] text-white' : current ? 'bg-[#e65332] text-white' : 'border border-black/20 bg-white text-black/45'}`}>{done ? '✓' : index + 1}</span><span className="min-w-0"><strong className={done ? 'text-[#2f7049]' : current ? 'text-[#b53a20]' : 'text-black/45'}>{labels[status]}</strong>{event && <time className="mt-0.5 block text-xs text-black/50">{new Date(event.changed_at).toLocaleString('vi-VN')}</time>}</span></li> })}</ol>}
        <details className="mt-4 border-t border-black/10 pt-3"><summary className="cursor-pointer text-xs font-semibold text-black/60">Xem lịch sử cập nhật</summary><ol className="mt-3 space-y-3">{(item.status_history ?? []).map((event, index) => <li key={`${event.to_status}-${event.changed_at}-${index}`} className="flex items-start gap-3 text-sm"><span className={`mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full text-[10px] font-bold ${event.to_status === 'cancelled' ? 'bg-red-600 text-white' : 'bg-[#2f7049] text-white'}`}>{event.to_status === 'cancelled' ? '!' : '✓'}</span><span><strong className={event.to_status === 'cancelled' ? 'text-red-700' : 'text-black'}>{index === 0 ? 'Đơn được tạo · ' : ''}{labels[event.to_status] ?? event.to_status}</strong><time className="mt-0.5 block text-xs text-black/50">{new Date(event.changed_at).toLocaleString('vi-VN')}</time></span></li>)}</ol></details>
      </section>
      <div className={`mb-4 rounded-xl border px-4 py-3 ${item.status === 'cancelled' ? 'border-red-200 bg-red-50 text-red-800' : item.status === 'completed' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-[#f4c7b8] bg-[#fff7f3] text-[#85351f]'}`}><p className="text-[11px] font-bold uppercase tracking-wider opacity-70">Việc tiếp theo</p><p className="mt-1 text-sm font-medium">{nextStep(item)}</p></div>
      {item.review && <div className="rounded-2xl bg-[#F0F0F0] p-4 text-sm"><p className="font-medium">Đánh giá của bạn <span className="ml-2 text-amber-500">{'★'.repeat(item.review.rating)}{'☆'.repeat(5 - item.review.rating)}</span></p>{item.review.comment && <p className="mt-1 text-black/70">{item.review.comment}</p>}</div>}
      {item.disputes?.map((dispute) => <section key={dispute.id} className="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm"><h3 className="font-semibold text-amber-950">Khiếu nại #{dispute.id} · {dispute.status === 'open' ? 'Đang xử lý' : dispute.status === 'reconciliation_pending' ? 'Chờ đối soát' : 'Đã xử lý'}</h3><p className="mt-1 font-medium">{dispute.reason}</p><p className="mt-1 whitespace-pre-wrap text-amber-950/80">{dispute.details}</p>{dispute.resolution_note && <p className="mt-2 border-t border-amber-200 pt-2">Quyết định: {dispute.resolution_note}</p>}{dispute.status === 'open' && item.buyer.id === userId && !dispute.buyer_wallet_refund_consent_at && <Button className="mt-3" size="sm" variant="outline" disabled={busy} onClick={() => void consentWalletRefund(item.id)}>Đồng ý nhận hoàn vào số dư nếu được duyệt</Button>}{dispute.buyer_wallet_refund_consent_at && <p className="mt-2 text-xs text-emerald-800">Đã đồng ý nhận hoàn vào số dư H-Smart.</p>}<details className="mt-3"><summary className="cursor-pointer text-xs font-semibold">Lịch sử khiếu nại ({dispute.events.length})</summary><ol className="mt-2 space-y-1 text-xs">{dispute.events.map((event, index) => <li key={`${event.action}-${index}`}>{new Date(event.created_at).toLocaleString('vi-VN')} · {event.action}{event.note ? ` — ${event.note}` : ''}</li>)}</ol></details></section>)}
      {mode === 'list' && item.buyer.id === userId && ['shipping', 'completed'].includes(item.status) && item.payment?.status === 'paid' && item.payout_status !== 'credited_to_wallet' && !item.disputes?.some((dispute) => dispute.status === 'open') && <form className="mt-4 rounded-2xl border border-amber-200 bg-white p-4" onSubmit={(event) => void openDispute(event, item.id)}><h3 className="font-semibold">Mở khiếu nại</h3><p className="mt-1 text-xs text-black/55">Khiếu nại sẽ tạm giữ khoản tiền chưa giải ngân trong lúc quản trị viên xem xét.</p><div className="mt-3 grid gap-3 sm:grid-cols-2"><label className="text-sm font-medium">Lý do<select className={fieldClass} required value={disputeDrafts[item.id]?.reason ?? ''} onChange={(event) => setDisputeDrafts((current) => ({ ...current, [item.id]: { reason: event.target.value, details: current[item.id]?.details ?? '', evidence: current[item.id]?.evidence ?? '' } }))}><option value="">Chọn lý do</option>{['Không nhận được hàng', 'Hàng khác mô tả', 'Hàng hư hỏng', 'Đơn hàng có vấn đề khác'].map((reason) => <option key={reason}>{reason}</option>)}</select></label><label className="text-sm font-medium">Ghi chú bằng chứng (mỗi dòng một mục)<textarea className={fieldClass} rows={2} maxLength={5000} value={disputeDrafts[item.id]?.evidence ?? ''} onChange={(event) => setDisputeDrafts((current) => ({ ...current, [item.id]: { reason: current[item.id]?.reason ?? '', details: current[item.id]?.details ?? '', evidence: event.target.value } }))} placeholder="Mã vận đơn, ảnh đã gửi hỗ trợ…" /></label></div><label className="mt-3 block text-sm font-medium">Mô tả tình huống<textarea className={fieldClass} required minLength={10} maxLength={5000} rows={3} value={disputeDrafts[item.id]?.details ?? ''} onChange={(event) => setDisputeDrafts((current) => ({ ...current, [item.id]: { reason: current[item.id]?.reason ?? '', details: event.target.value, evidence: current[item.id]?.evidence ?? '' } }))} /></label><Button className="mt-3" size="sm" disabled={busy}>Gửi khiếu nại</Button></form>}
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
      <div><h1 className="mt-2 font-satoshi font-bold tracking-tight text-2xl uppercase sm:text-4xl">{mode === 'create' ? 'Tạo đơn hàng' : 'Đơn hàng của tôi'}</h1></div>
      {mode === 'create' && <Button variant="outline" onClick={() => navigate('/me/orders')}>Xem đơn hàng <ArrowRight className="ml-2" size={16} /></Button>}
    </div>

    {notice && <Alert variant="success" className="mb-4" role="status">{notice}</Alert>}
    {error && <Alert variant="error" className="mb-4">{error}</Alert>}
    {mode === 'create' && !offerId && <Alert variant="error" className="mb-4">Thiếu mã đề nghị mua.</Alert>}
    {loading && <p className="py-8 text-sm text-black/55">Đang tải đơn hàng…</p>}
    {mode === 'create' && !loading && !order && offer?.status === 'accepted' && <form className="max-w-2xl rounded-[20px] border border-black/10 bg-white p-5 shadow-sm sm:p-8" onSubmit={createOrder}>
      <div className="mb-6 border-b border-black/10 pb-5"><p className="text-xs uppercase tracking-wider text-black/50">Sản phẩm đã thỏa thuận</p><h2 className="mt-2 text-2xl font-bold">{offer.product.title}</h2><p className="mt-2 text-sm text-black/60">Giá người bán đã chấp nhận: <strong className="text-black">{money(offer.offer_price)}</strong></p></div>
      <label className="mb-4 block text-sm font-medium">Người nhận<input className={fieldClass} required minLength={2} maxLength={150} value={recipientName} onChange={(event) => setRecipientName(event.target.value)} /></label>
      <label className="mb-4 block text-sm font-medium">Số điện thoại<input className={fieldClass} type="tel" required minLength={8} maxLength={30} value={phone} onChange={(event) => setPhone(event.target.value)} /></label>
      <label className="block text-sm font-medium">Địa chỉ nhận hàng<textarea className={fieldClass} required minLength={5} maxLength={500} rows={3} value={address} onChange={(event) => setAddress(event.target.value)} /></label>
      <label className="mt-5 block text-sm font-medium">Phương thức thanh toán<select className={fieldClass} value={paymentMethod} onChange={(event) => setPaymentMethod(event.target.value as 'vnpay' | 'cod')}><option value="vnpay">VNPay</option><option value="cod">Thanh toán khi nhận hàng (COD)</option></select></label>
      {quote && <dl className="my-5 grid gap-3 rounded-xl bg-[#F0F0F0] p-4 text-sm sm:grid-cols-2"><div><dt className="text-black/55">Giá sản phẩm</dt><dd className="mt-1 font-medium">{money(quote.product_amount)}</dd></div><div><dt className="text-black/55">Phí giao hàng</dt><dd className="mt-1 font-medium">{money(quote.shipping_fee)}</dd></div><div><dt className="text-black/55">Hoa hồng sàn ({quote.commission_rate}%)</dt><dd className="mt-1 font-medium">{money(quote.platform_fee)}</dd></div><div><dt className="text-black/55">Người bán dự kiến nhận</dt><dd className="mt-1 font-medium">{money(quote.seller_amount)}</dd></div>{paymentMethod === 'cod' && <><div><dt className="text-black/55">Đặt cọc trước ({quote.cod_deposit_percent}%)</dt><dd className="mt-1 font-semibold">{money(quote.cod_deposit_amount)}</dd></div><div><dt className="text-black/55">Còn thu khi nhận hàng</dt><dd className="mt-1 font-semibold">{money(quote.cod_remaining_amount)} + {money(quote.shipping_fee)} giao hàng</dd></div></>}<div className="sm:col-span-2"><dt className="text-black/55">{paymentMethod === 'cod' ? 'Tổng đơn hàng' : 'Tổng thanh toán VNPay'}</dt><dd className="mt-1 text-lg font-bold">{money(quote.total_amount)}</dd></div></dl>}
      <p className="my-5 rounded-xl bg-[#F0F0F0] p-4 text-sm leading-relaxed text-black/60">{paymentMethod === 'cod' ? `Đơn COD cần thanh toán trước ${quote ? money(quote.cod_deposit_amount) : 'tiền cọc'}; người bán chỉ xác nhận và gửi hàng sau khi khoản cọc được xác minh. Phần còn lại thu khi nhận hàng.` : 'Sản phẩm được giữ 30 phút để hoàn tất thanh toán VNPay.'}</p>
      <label className="mb-5 flex items-start gap-2 text-sm"><input required type="checkbox" checked={acceptedPolicy} onChange={(event) => setAcceptedPolicy(event.target.checked)} className="mt-1"/><span>Tôi đã đọc và đồng ý <a className="underline" href="/policies/fees" target="_blank" rel="noreferrer">biểu phí</a>, <a className="underline" href="/policies/payment" target="_blank" rel="noreferrer">điều khoản thanh toán, đặt cọc và hoàn tiền</a>.</span></label>
      <Button className="w-full sm:w-auto" type="submit" disabled={busy || !acceptedPolicy || !quote}>{busy ? 'Đang tạo đơn…' : 'Tạo đơn và giữ sản phẩm'}</Button>
    </form>}
    {mode === 'create' && order && <div className="space-y-4">{card(order)}<div className="flex flex-wrap gap-2">{actions(order)}<Button variant="outline" onClick={() => navigate('/me/orders')}>Tất cả đơn hàng</Button></div></div>}
    {mode === 'list' && !loading && <div className="space-y-4">{orders.length ? orders.map(card) : <div className="rounded-[20px] border border-dashed border-black/20 bg-white p-10 text-center"><PackageCheck className="mx-auto mb-3 text-black/45" size={28} /><h2 className="font-semibold">Chưa có đơn hàng nào</h2><p className="mt-2 text-sm text-black/55">Đơn hàng sẽ xuất hiện tại đây sau khi người bán chấp nhận đề nghị của bạn.</p><Button className="mt-5" variant="outline" onClick={() => navigate('/products')}>Khám phá sản phẩm</Button></div>}</div>}
    {mode === 'list' && !loading && totalPages > 1 && <div className="mt-7 flex items-center justify-center gap-3"><Button variant="outline" disabled={page <= 1} onClick={() => setPage((current) => current - 1)}>Trước</Button><span className="text-sm text-black/55">Trang {page} / {totalPages} · {totalOrders} đơn hàng</span><Button variant="outline" disabled={page >= totalPages} onClick={() => setPage((current) => current + 1)}>Sau</Button></div>}
    {vietQr && <div role="dialog" aria-modal="true" aria-label="Hướng dẫn thanh toán VietQR" className="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"><section className="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl"><div className="flex items-start justify-between gap-3"><div><h2 className="text-lg font-bold">Thanh toán bằng VietQR</h2><p className="mt-1 text-sm text-slate-600">Quét mã và nhập đúng số tiền, nội dung chuyển khoản.</p></div><Button variant="outline" size="sm" onClick={() => setVietQr(null)}>Đóng</Button></div><img className="mx-auto mt-4 aspect-square w-64 rounded-xl border object-contain" src={vietQr.qr_url} alt="Mã VietQR thanh toán" /><dl className="mt-4 space-y-2 text-sm"><div className="flex justify-between gap-3"><dt className="text-slate-500">Ngân hàng</dt><dd className="font-medium">{vietQr.bank_id}</dd></div><div className="flex justify-between gap-3"><dt className="text-slate-500">Tài khoản</dt><dd className="font-medium">{vietQr.account_number} · {vietQr.account_name}</dd></div><div className="flex justify-between gap-3"><dt className="text-slate-500">Số tiền</dt><dd className="font-bold">{money(vietQr.amount)}</dd></div><div className="flex justify-between gap-3"><dt className="text-slate-500">Nội dung</dt><dd className="font-mono font-semibold">{vietQr.reference}</dd></div></dl><p className="mt-4 rounded-xl bg-amber-50 p-3 text-xs leading-5 text-amber-900">Ảnh QR chỉ tạo hướng dẫn chuyển khoản. Đơn vẫn chờ xác minh nguồn tiền; không giao hàng chỉ dựa vào ảnh hoặc ảnh chụp chuyển khoản.</p></section></div>}
  </main>
}
