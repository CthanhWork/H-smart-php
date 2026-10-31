import { useEffect, useState } from 'react'
import { productRequest, type Product } from './productApi'
import { ArrowRight, Handshake } from 'lucide-react'
import { Button } from './components/ui/button'
import { Badge } from './components/ui/badge'
import { Alert } from './components/ui/alert'

type Offer = {
  id: number; offer_price: number; status: string; expires_at: string | null
  created_at: string; product: Product; buyer: { id: number; full_name: string | null; avatar_url: string | null }
  seller: { id: number; full_name: string | null; avatar_url: string | null }
}
type OfferPage = { content: Offer[]; pageNo: number; pageSize: number; totalElements: number; totalPages: number; last: boolean }
type Props = { accessToken: string; userId: number; navigate: (path: string) => void }
const money = (amount: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount)
const statuses: Record<string, string> = { pending: 'Đang chờ', accepted: 'Đã chấp nhận', rejected: 'Đã từ chối', cancelled: 'Đã hủy', expired: 'Hết hạn', ordered: 'Đã tạo đơn' }

export default function OfferPages({ accessToken, userId, navigate }: Props) {
  const [offers, setOffers] = useState<Offer[]>([])
  const [loading, setLoading] = useState(true)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [version, setVersion] = useState(0)

  useEffect(() => {
    let cancelled = false
    productRequest<OfferPage>('offers?page=1', accessToken)
      .then(({ data }) => { if (!cancelled) setOffers(data.content) })
      .catch((cause: Error) => { if (!cancelled) setError(cause.message) })
      .finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true }
  }, [accessToken, version])

  async function act(offer: Offer, action: 'accept' | 'reject' | 'cancel') {
    setBusyId(offer.id); setLoading(true); setError(''); setNotice('')
    try {
      const result = await productRequest<Offer>(`offers/${offer.id}/${action}`, accessToken, { method: 'POST' })
      setNotice(result.message)
      setVersion((value) => value + 1)
    } catch (cause) { setError((cause as Error).message) }
    finally { setBusyId(null) }
  }

  const variant: Record<string, 'warning' | 'success' | 'destructive' | 'outline' | 'default'> = { pending: 'warning', accepted: 'success', rejected: 'destructive', cancelled: 'outline', expired: 'outline', ordered: 'default' }
  return <main className="max-w-frame mx-auto min-h-[55vh] px-4 py-10 xl:px-0">
    <div className="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p className="text-xs uppercase tracking-[0.16em] text-black/50">Thỏa thuận giá</p><h1 className="mt-2 font-satoshi font-bold tracking-tight text-2xl uppercase sm:text-4xl">Đề nghị mua bán</h1></div><div className="flex gap-2"><Button variant="outline" onClick={() => navigate('/products')}>Xem sản phẩm</Button><Button variant="outline" onClick={() => navigate('/me/orders')}>Đơn hàng</Button></div></div>
    {notice && <Alert variant="success" className="mb-4" role="status">{notice}</Alert>}{error && <Alert variant="error" className="mb-4">{error}</Alert>}{loading && <p className="py-8 text-sm text-black/55">Đang tải đề nghị…</p>}
    {!loading && offers.length === 0 && <div className="rounded-[20px] border border-dashed border-black/20 p-10 text-center text-sm text-black/55"><Handshake className="mx-auto mb-3" size={30} />Chưa có đề nghị mua bán nào.</div>}
    <div className="space-y-4">{offers.map((offer) => {
      const buyer = offer.buyer.id === userId
      const pending = offer.status === 'pending'
      return <article className="grid gap-5 rounded-[20px] border border-black/10 bg-white p-5 shadow-sm sm:grid-cols-[1fr_auto] sm:p-6" key={offer.id}>
        <div className="flex min-w-0 gap-4">{offer.product.images[0] ? <img className="h-24 w-24 shrink-0 rounded-2xl bg-tile object-cover sm:h-28 sm:w-28" src={offer.product.images[0].url} alt="" /> : <div className="flex h-24 w-24 shrink-0 items-center justify-center rounded-2xl bg-tile text-xs text-black/40 sm:h-28 sm:w-28">Chưa có ảnh</div>}
          <div className="min-w-0"><button className="text-left text-lg font-bold hover:underline" onClick={() => navigate(`/products/${offer.product.id}`)}>{offer.product.title}</button><p className="mt-1 text-sm text-black/60">{buyer ? 'Người bán' : 'Người mua'}: {(buyer ? offer.seller.full_name : offer.buyer.full_name) || 'Thành viên H-Smart'}</p><p className="mt-2 text-xs leading-relaxed text-black/45">Gửi lúc {new Date(offer.created_at).toLocaleString('vi-VN')}{offer.expires_at ? ` · Hạn phản hồi ${new Date(offer.expires_at).toLocaleString('vi-VN')}` : ''}</p></div>
        </div>
        <div className="flex flex-row flex-wrap items-center gap-3 sm:flex-col sm:items-end sm:justify-center"><strong className="text-xl">{money(offer.offer_price)}</strong><Badge variant={variant[offer.status] ?? 'outline'}>{statuses[offer.status] ?? offer.status}</Badge></div>
        {(pending || (buyer && offer.status === 'accepted') || (buyer && offer.status === 'ordered')) && <div className="flex flex-wrap items-center gap-2 border-t border-black/10 pt-4 sm:col-span-2">{pending ? buyer ? <Button variant="outline" disabled={busyId === offer.id} onClick={() => act(offer, 'cancel')}>Hủy đề nghị</Button> : <>{offer.product.status !== 'active' && <span className="mr-auto text-sm text-amber-800">Món này đang có người đặt mua.</span>}{offer.product.status === 'active' && <Button variant="accent-green" disabled={busyId === offer.id} onClick={() => act(offer, 'accept')}>Chấp nhận</Button>}<Button variant="outline" disabled={busyId === offer.id} onClick={() => act(offer, 'reject')}>Từ chối</Button></> : offer.status === 'accepted' ? <Button onClick={() => navigate(`/orders/create?offerId=${offer.id}`)}>Tạo đơn hàng <ArrowRight size={16} className="ml-2" /></Button> : <Button variant="outline" onClick={() => navigate('/me/orders')}>Xem đơn hàng</Button>}</div>}
      </article>
    })}</div>
  </main>
}
