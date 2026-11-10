import { useEffect, useState, type FormEvent } from 'react'
import { ArrowLeft, ArrowRight, BadgeCheck, ChevronLeft, ChevronRight, Flag, Heart, MessageCircle, Percent, Share2, ShieldCheck, ShoppingBag, Star, Store, Truck } from 'lucide-react'
import ProductCard from './components/ProductCard'
import { Button } from './components/ui/button'
import { conditionLabel, productRequest, statusLabel, type Product, type ProductPage } from './productApi'

type Props = { accessToken: string; userId: number | null; navigate: (path: string) => void; isAdmin?: boolean }
const formatPrice = (value: number) => `${new Intl.NumberFormat('vi-VN').format(value)} ₫`

export default function ProductDetailPage({ accessToken, userId, navigate, isAdmin = false }: Props) {
  const productId = window.location.pathname.split('/').pop()
  const [product, setProduct] = useState<Product | null>(null)
  const [recommendations, setRecommendations] = useState<Product[]>([])
  const [error, setError] = useState('')
  const [activeImage, setActiveImage] = useState(0)
  const [busy, setBusy] = useState(false)
  const [offerPrice, setOfferPrice] = useState('')
  const [offerError, setOfferError] = useState('')
  const [offerNotice, setOfferNotice] = useState('')
  const [offerSent, setOfferSent] = useState(false)
  const [shareNotice, setShareNotice] = useState('')
  const [favoriteBusy, setFavoriteBusy] = useState(false)
  const [favoriteNotice, setFavoriteNotice] = useState('')
  const [reportOpen, setReportOpen] = useState(false)
  const [reportTarget, setReportTarget] = useState<'product' | 'user'>('product')
  const [reportReason, setReportReason] = useState('')
  const [reportDetails, setReportDetails] = useState('')
  const [reportError, setReportError] = useState('')
  const [reportNotice, setReportNotice] = useState('')

  useEffect(() => {
    let cancelled = false
    setProduct(null)
    setError('')
    productRequest<Product>(`products/${productId}`, accessToken).then(({ data }) => {
      if (cancelled) return
      setProduct(data)
      setRecommendations([])
      void productRequest<ProductPage>(`products?filter[seller_id]=${data.seller_id}&page[number]=0&page[size]=6`, accessToken)
        .then((page) => {
          if (!cancelled) setRecommendations(page.data.content.filter((item) => item.id !== data.id).slice(0, 4))
        })
        .catch(() => { /* Seller listings are optional; keep the product detail available. */ })
    }).catch((cause: Error & { status?: number }) => {
      if (!cancelled) setError(cause.status === 404 ? 'Không tìm thấy sản phẩm hoặc sản phẩm không còn hiển thị.' : cause.message)
    })
    return () => { cancelled = true }
  }, [accessToken, productId])

  useEffect(() => {
    setActiveImage(0)
    setOfferSent(false)
    setOfferError('')
    setOfferNotice('')
    setOfferPrice('')
  }, [product?.id])

  function requireAccount() {
    if (userId == null || !accessToken) {
      const returnTo = `${window.location.pathname}${window.location.search}`
      navigate(`/login?returnTo=${encodeURIComponent(returnTo)}`)
      return false
    }
    return true
  }

  async function contactSeller() {
    if (!product || !requireAccount()) return
    setBusy(true)
    navigate(`/me/messages?productId=${product.id}&userId=${product.seller_id}`)
  }

  async function toggleFavorite() {
    if (!product) return
    if (!requireAccount()) return
    setFavoriteBusy(true); setFavoriteNotice('')
    try {
      const result = product.is_favorite
        ? await productRequest<null>(`me/favorites/${product.id}`, accessToken, { method: 'DELETE' })
        : await productRequest<null>('me/favorites', accessToken, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ product_id: product.id }) })
      setProduct((current) => current ? { ...current, is_favorite: !product.is_favorite } : current)
      setFavoriteNotice(result.message)
    } catch (cause) { setFavoriteNotice((cause as Error).message) }
    finally { setFavoriteBusy(false) }
  }

  async function submitOffer(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!product || !requireAccount() || busy) return
    setBusy(true); setOfferError(''); setOfferNotice('')
    try {
      const result = await productRequest<unknown>('offers', accessToken, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: product.id, offer_price: Number(offerPrice) }),
      })
      setOfferNotice(result.message)
      setOfferSent(true)
      setOfferPrice('')
    } catch (cause) {
      setOfferError((cause as Error).message)
    } finally {
      setBusy(false)
    }
  }

  async function submitReport(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!product || !requireAccount()) return
    setBusy(true); setReportError(''); setReportNotice('')
    try {
      const result = await productRequest<unknown>('reports', accessToken, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ target_type: reportTarget, target_id: reportTarget === 'product' ? product.id : product.seller_id, reason: reportReason, details: reportDetails || undefined }),
      })
      setReportNotice(result.message); setReportOpen(false); setReportReason(''); setReportDetails('')
    } catch (cause) { setReportError((cause as Error).message) }
    finally { setBusy(false) }
  }

  async function shareProduct() {
    if (!product) return
    const url = window.location.href
    try {
      if (navigator.share) await navigator.share({ title: product.title, url })
      else {
        await navigator.clipboard.writeText(url)
        setShareNotice('Đã sao chép liên kết sản phẩm.')
      }
    } catch (cause) {
      if (cause instanceof Error && cause.name !== 'AbortError') setShareNotice('Không thể chia sẻ liên kết trên trình duyệt này.')
    }
  }

  if (error) return <main className="font-vietnamese min-h-[55vh] bg-[#f5f5f5] px-4 py-16"><div className="mx-auto max-w-[720px] border border-[#eee] bg-white p-8 text-center"><p className="text-sm text-red-700">{error}</p><button type="button" onClick={() => navigate('/products')} className="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-[#ee4d2d]"><ArrowLeft size={16} />Quay lại danh sách</button></div></main>
  if (!product) return <main className="font-vietnamese min-h-[55vh] bg-[#f5f5f5] px-4 py-10"><div className="sr-only" role="status">Đang tải thông tin sản phẩm</div><div className="mx-auto grid max-w-[1200px] gap-4 bg-white p-4 md:grid-cols-2"><div className="aspect-square animate-pulse bg-[#f0f0f0]" /><div className="grid content-start gap-4"><div className="h-7 animate-pulse bg-[#f0f0f0]" /><div className="h-12 animate-pulse bg-[#f0f0f0]" /><div className="h-28 animate-pulse bg-[#f0f0f0]" /></div></div></main>

  const isOwner = userId === product.seller_id
  const isActive = product.status === 'active'
  const images = product.images?.length ? product.images : []
  const selectedImage = images[activeImage]?.url
  const rating = product.rating_average
  const reviewCount = product.review_count ?? 0
  const minPrice = product.min_price == null ? null : Number(product.min_price)
  const priceValue = Number(product.price)
  const canOffer = product.negotiable && isActive && !isOwner

  return <main className={`font-vietnamese min-h-screen bg-[#f5f5f5] text-[#222] ${!isOwner && isActive && !isAdmin ? 'pb-28 lg:pb-12' : 'pb-12'}`}>
    <div className="mx-auto w-[min(calc(100%-24px),1200px)] sm:w-[min(calc(100%-40px),1200px)]">
      <nav aria-label="Đường dẫn sản phẩm" className="flex min-h-10 items-center gap-2 overflow-hidden whitespace-nowrap text-xs text-[#777]">
        <a href="/" onClick={(event) => { if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return; event.preventDefault(); navigate('/') }} className="shrink-0 font-medium text-[#ee4d2d]">H-Smart</a><ChevronRight size={13} className="shrink-0 text-[#aaa]" />
        <a href={product.category ? `/products?category=${product.category.id}` : '/products'} onClick={(event) => { const path = product.category ? `/products?category=${product.category.id}` : '/products'; if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return; event.preventDefault(); navigate(path) }} className="shrink-0 hover:text-[#ee4d2d]">{product.category?.name ?? 'Sản phẩm'}</a><ChevronRight size={13} className="shrink-0 text-[#aaa]" />
        <span className="truncate">{product.title}</span>
      </nav>

      <section className="grid gap-5 border border-[#eee] bg-white p-3 sm:p-5 lg:grid-cols-[minmax(300px,440px)_minmax(0,1fr)] lg:gap-7">
        <div className="min-w-0">
          <div className="group relative aspect-square overflow-hidden border border-[#f0f0f0] bg-[#fafafa]">
            {selectedImage ? <img src={selectedImage} alt={product.title} width={1000} height={1000} fetchPriority="high" className="h-full w-full object-contain" /> : <div className="flex h-full items-center justify-center text-sm text-black/40"><ShoppingBag size={38} className="mr-2" />Chưa có hình ảnh</div>}
            {images.length > 1 && <><button type="button" aria-label="Ảnh trước" onClick={() => setActiveImage((index) => (index - 1 + images.length) % images.length)} className="absolute left-0 top-1/2 grid h-10 w-8 -translate-y-1/2 place-items-center bg-white/85 text-[#444] opacity-100 transition hover:bg-white sm:opacity-0 sm:group-hover:opacity-100"><ChevronLeft size={20} /></button><button type="button" aria-label="Ảnh tiếp theo" onClick={() => setActiveImage((index) => (index + 1) % images.length)} className="absolute right-0 top-1/2 grid h-10 w-8 -translate-y-1/2 place-items-center bg-white/85 text-[#444] opacity-100 transition hover:bg-white sm:opacity-0 sm:group-hover:opacity-100"><ChevronRight size={20} /></button></>}
          </div>
          {images.length > 1 && <div className="mt-2 grid grid-cols-5 gap-2" aria-label="Ảnh sản phẩm">{images.slice(0, 10).map((image, index) => <button key={image.id} type="button" aria-label={`Xem ảnh ${index + 1}`} onClick={() => setActiveImage(index)} className={`aspect-square overflow-hidden border-2 ${index === activeImage ? 'border-[#ee4d2d]' : 'border-transparent hover:border-[#ee4d2d]/50'}`}><img src={image.url} alt={`${product.title} ${index + 1}`} width={120} height={120} loading="lazy" className="h-full w-full object-cover" /></button>)}</div>}
          <div className="mt-4 flex flex-wrap items-center gap-2 text-xs text-[#666] sm:text-sm"><span className="mr-1">Chia sẻ:</span><button type="button" onClick={shareProduct} className="inline-flex h-8 items-center gap-1.5 border border-[#e7e7e7] px-2.5 hover:border-[#ee4d2d] hover:text-[#ee4d2d]"><Share2 size={14} />Chia sẻ sản phẩm</button>{!isOwner && <button type="button" onClick={() => { if (requireAccount()) { setReportTarget('product'); setReportOpen(true); setReportError('') } }} className="inline-flex h-8 items-center gap-1.5 border border-[#e7e7e7] px-2.5 hover:border-[#ee4d2d] hover:text-[#ee4d2d]"><Flag size={14} />Báo cáo</button>}{shareNotice && <span role="status" className="text-emerald-700">{shareNotice}</span>}{reportNotice && <span role="status" className="text-emerald-700">{reportNotice}</span>}</div>
        </div>

        <div className="min-w-0 py-1 sm:px-1">
          <div className="flex flex-wrap items-center gap-2"><span className={`inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold ${isActive ? 'bg-[#fff3ed] text-[#d94322]' : 'bg-black/[0.06] text-[#555]'}`}>{isActive && <BadgeCheck size={14} />}{statusLabel[product.status] ?? product.status}</span><span className="inline-flex items-center gap-1 text-xs text-[#777]"><ShieldCheck size={14} className="text-emerald-700" />Tin đăng trên H-Smart</span></div>
          <h1 className="mt-3 text-xl font-semibold leading-snug text-[#222] sm:text-2xl">{product.title}</h1>
          <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[#777] sm:text-sm">
            {reviewCount > 0 && rating != null ? <><span className="inline-flex items-center gap-1 border-b border-[#ee4d2d] pb-0.5 text-[#ee4d2d]"><strong>{rating.toFixed(1)}</strong><Star size={14} className="fill-[#ee4d2d]" /></span><span>{reviewCount} đánh giá</span></> : <span>Chưa có đánh giá</span>}
            <span>{product.category?.name ?? 'Chưa phân loại'}</span>
          </div>
          <div className="mt-5 flex flex-wrap items-baseline gap-x-3 gap-y-2 bg-[#fafafa] px-4 py-4 sm:px-5"><strong className="text-3xl font-semibold tracking-tight text-[#ee4d2d] sm:text-[34px]">{formatPrice(priceValue)}</strong>{product.negotiable && <span className="inline-flex items-center gap-1 text-sm font-medium text-[#e65a36]"><Percent size={14} />Có thể thương lượng</span>}</div>

          <section className="mt-4 border border-[#eee] bg-white p-4 sm:p-5" aria-labelledby="seller-summary-title">
            <div className="flex flex-wrap items-center justify-between gap-4">
              <div className="flex min-w-0 items-center gap-3"><span className="grid h-12 w-12 shrink-0 place-items-center rounded-full border border-[#ffd8ce] bg-[#fff5f1] text-[#ee4d2d]"><Store size={21} /></span><div className="min-w-0"><h2 id="seller-summary-title" className="truncate text-sm font-semibold"><a href={`/sellers/${product.seller_id}`} aria-label={`Xem hồ sơ người bán: ${product.seller?.name || `Tài khoản #${product.seller_id}`}`} onClick={(event) => { if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return; event.preventDefault(); navigate(`/sellers/${product.seller_id}`) }} className="hover:text-[#ee4d2d] hover:underline">{product.seller?.name || `Tài khoản #${product.seller_id}`}</a></h2><div className="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-black/60">{(product.seller_completed_sales_count ?? 0) > 0 && <span>{product.seller_completed_sales_count} giao dịch hoàn tất</span>}{(product.seller_review_count ?? 0) > 0 && product.seller_review_average != null && <span className="inline-flex items-center gap-1 text-[#8a4b00]"><Star size={12} className="fill-current" />{product.seller_review_average.toFixed(1)} · {product.seller_review_count} đánh giá</span>}{(product.seller_completed_sales_count ?? 0) === 0 && (product.seller_review_count ?? 0) === 0 && <span>Chưa có lịch sử giao dịch</span>}</div></div></div>
              <a href={`/products?seller_id=${product.seller_id}`} onClick={(event) => { if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return; event.preventDefault(); navigate(`/products?seller_id=${product.seller_id}`) }} className="inline-flex h-9 items-center border border-[#ddd] px-3 text-xs font-semibold text-[#444] hover:border-[#ee4d2d] hover:text-[#ee4d2d]">Sản phẩm khác</a>
            </div>
          </section>

          <dl className="mt-4 grid gap-3 text-sm sm:mt-5">
            <div className="grid grid-cols-[100px_minmax(0,1fr)] gap-3"><dt className="text-[#777]">Tình trạng</dt><dd className="m-0 text-[#333]">{conditionLabel[product.condition] ?? product.condition}</dd></div>
            {product.negotiable && minPrice != null && <div className="grid grid-cols-[100px_minmax(0,1fr)] gap-3"><dt className="text-[#777]">Giá tối thiểu</dt><dd className="m-0 text-[#333]">{formatPrice(minPrice)}</dd></div>}
            <div className="grid grid-cols-[100px_minmax(0,1fr)] gap-3"><dt className="text-[#777]">Đăng lúc</dt><dd className="m-0 text-[#333]">{new Intl.DateTimeFormat('vi-VN', { dateStyle: 'medium' }).format(new Date(product.created_at))}</dd></div>
          </dl>

          {favoriteNotice && <p role="status" className="mt-3 text-sm text-emerald-700">{favoriteNotice}</p>}
          {!isOwner && <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
            <section className="border border-[#eee] bg-white p-4"><h2 className="flex items-center gap-2 text-sm font-semibold"><Truck size={17} className="text-[#ee4d2d]" />Giao nhận</h2><ul className="mt-3 grid gap-2 text-xs leading-5 text-[#666]"><li>Thống nhất cách giao và phí vận chuyển với người bán trước khi tạo đơn. H-Smart chưa tự tính phí giao hàng.</li><li>Người bán cập nhật đơn vị vận chuyển và mã vận đơn sau khi gửi hàng.</li></ul></section>
            <section className="border border-[#eee] bg-white p-4"><h2 className="flex items-center gap-2 text-sm font-semibold"><ShieldCheck size={17} className="text-emerald-700" />Giao dịch an toàn</h2><ul className="mt-3 grid gap-2 text-xs leading-5 text-[#666]"><li>Kiểm tra ảnh và tình trạng; hỏi thêm về khuyết điểm trước khi thống nhất.</li><li>Giữ trao đổi trên H-Smart và chỉ thanh toán từ trang đơn hàng.</li><li>Không gửi mật khẩu, mã OTP hoặc thông tin thẻ cho người bán.</li></ul></section>
          </div>}

          <div className="mt-6 flex flex-wrap gap-2.5 border-t border-[#eee] pt-5">
            <button type="button" onClick={toggleFavorite} disabled={favoriteBusy} aria-pressed={Boolean(product.is_favorite)} className="inline-flex min-h-11 items-center justify-center gap-2 border border-[#ddd] px-4 text-sm font-semibold text-[#444] transition hover:border-[#ee4d2d] hover:text-[#ee4d2d] disabled:opacity-60"><Heart size={17} className={product.is_favorite ? 'fill-[#ee4d2d] text-[#ee4d2d]' : ''} />{product.is_favorite ? 'Đã yêu thích' : 'Yêu thích'}</button>
          {!isOwner && isActive && !isAdmin && <>
            {canOffer && <button type="button" onClick={() => document.getElementById('offer-form')?.scrollIntoView({ behavior: 'smooth', block: 'center' })} className="inline-flex min-h-11 items-center justify-center gap-2 border border-[#ee4d2d] bg-[#fff5f1] px-4 text-sm font-semibold text-[#ee4d2d] transition hover:bg-[#ffebe3]"><Percent size={17} />Đề nghị giá</button>}
            <button type="button" onClick={contactSeller} disabled={busy} className="inline-flex min-h-11 items-center justify-center gap-2 bg-[#ee4d2d] px-5 text-sm font-semibold text-white transition hover:bg-[#dc4326] disabled:opacity-60"><MessageCircle size={17} />{busy ? 'Đang mở tin nhắn…' : 'Nhắn tin người bán'}</button>
          </>}
          {isOwner && <button type="button" onClick={() => navigate(`/products/${product.id}/edit`)} className="inline-flex min-h-11 items-center gap-2 border border-[#ee4d2d] px-4 text-sm font-semibold text-[#ee4d2d] hover:bg-[#fff5f1]">Chỉnh sửa tin đăng<ArrowRight size={16} /></button>}
          {!isOwner && !isActive && <p className="self-center text-sm text-[#777]">Sản phẩm hiện không còn nhận đề nghị mua.</p>}
          </div>

          {!isOwner && !isAdmin && canOffer && <form id="offer-form" onSubmit={submitOffer} className="mt-4 border border-[#f1ddd5] bg-[#fffaf8] p-4 sm:p-5">
            <h2 className="text-sm font-bold text-[#333]">Gửi đề nghị giá cho người bán</h2><p className="mt-1 text-xs leading-5 text-[#777]">Người bán sẽ xem xét và phản hồi đề nghị của bạn.</p>
            {offerError && <p role="alert" className="mt-3 text-sm text-red-700">{offerError}</p>}{offerNotice && <p role="status" className="mt-3 text-sm text-emerald-700">{offerNotice}</p>}
            <label className="mt-3 block text-xs font-medium text-[#555]">Giá bạn muốn đề nghị<input className="mt-1.5 h-11 w-full border border-[#ddd] bg-white px-3 text-sm outline-none focus:border-[#ee4d2d] disabled:bg-[#f5f5f5]" type="number" min={minPrice ?? 1} max={priceValue} step="1" required value={offerPrice} onChange={(event) => setOfferPrice(event.target.value)} placeholder="Nhập số tiền (₫)" disabled={offerSent} /></label>
            <Button className="mt-3 rounded-sm bg-[#ee4d2d] hover:bg-[#dc4326]" disabled={busy || offerSent}>{busy ? 'Đang gửi…' : offerSent ? 'Đã gửi đề nghị' : 'Gửi đề nghị mua'}</Button>
          </form>}

        </div>
      </section>

      <div className="mt-3 grid items-start gap-3 lg:grid-cols-[minmax(0,1fr)_260px]">
        <div className="grid gap-3">
          <section className="border border-[#eee] bg-white p-4 sm:p-5">
            <h2 className="bg-[#fafafa] px-3 py-3 text-sm font-semibold uppercase tracking-wide">Mô tả sản phẩm</h2>
            <p className="mt-4 whitespace-pre-line px-3 text-sm leading-7 text-[#444]">{product.description || 'Người bán chưa thêm mô tả cho sản phẩm này.'}</p>
          </section>

          <section className="border border-[#eee] bg-white p-4 sm:p-5">
            <h2 className="bg-[#fafafa] px-3 py-3 text-sm font-semibold uppercase tracking-wide">Đánh giá sản phẩm</h2>
            <div className="mt-4 flex flex-wrap items-center gap-3 border border-[#f7ddcf] bg-[#fff8f6] p-4 sm:p-5">{reviewCount > 0 && rating != null ? <><strong className="text-2xl font-semibold text-[#ee4d2d]">{rating.toFixed(1)} <span className="text-sm font-normal">/ 5</span></strong><span className="inline-flex text-[#ee4d2d]" aria-label={`${rating.toFixed(1)} trên 5 sao`}>{Array.from({ length: 5 }, (_, index) => <Star key={index} size={16} fill={index < Math.round(rating) ? 'currentColor' : 'none'} />)}</span><span className="text-xs text-[#777]">{reviewCount} đánh giá từ giao dịch đã hoàn tất</span></> : <span className="text-sm text-[#777]">Chưa có đánh giá cho sản phẩm này.</span>}</div>
          </section>
        </div>

        <aside className="grid gap-3 lg:sticky lg:top-24">
          <section className="border border-[#eee] bg-white p-3"><h2 className="mb-3 bg-[#fafafa] px-3 py-3 text-sm font-semibold uppercase">Sản phẩm khác của người bán này</h2>{recommendations.length ? <div className="grid grid-cols-2 gap-2 lg:grid-cols-1">{recommendations.map((item) => <ProductCard key={item.id} product={item} compact onClick={() => navigate(`/products/${item.id}`)} />)}</div> : <div className="px-3 py-5 text-center text-xs text-[#777]">Người bán chưa có sản phẩm nào khác đang hiển thị.</div>}</section>
        </aside>
      </div>
    </div>
    {!isOwner && isActive && !isAdmin && <div className="fixed inset-x-0 bottom-0 z-30 flex items-center gap-2 border-t border-black/10 bg-white/95 px-3 pt-3 shadow-[0_-6px_24px_rgba(0,0,0,.08)] backdrop-blur lg:hidden" style={{ paddingBottom: 'max(0.75rem, env(safe-area-inset-bottom))' }} aria-label="Hành động sản phẩm">
      <div className="mr-auto min-w-0"><span className="block text-[10px] text-black/50">Giá bán</span><strong className="block truncate text-base text-[#d94322]">{formatPrice(priceValue)}</strong></div>
      {canOffer && <button type="button" onClick={() => document.getElementById('offer-form')?.scrollIntoView({ behavior: 'smooth', block: 'center' })} className="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border border-[#ee4d2d] bg-white px-3 text-xs font-semibold text-[#d94322]"><Percent size={15} />Trả giá</button>}
      <button type="button" onClick={contactSeller} disabled={busy} className="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-[#ee4d2d] px-3 text-xs font-semibold text-white disabled:opacity-60"><MessageCircle size={16} />{busy ? 'Đang mở…' : 'Nhắn người bán'}</button>
    </div>}
    {reportOpen && <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={() => setReportOpen(false)}><section role="dialog" aria-modal="true" aria-labelledby="report-title" className="w-full max-w-lg bg-white p-5 shadow-2xl sm:p-6" onClick={(event) => event.stopPropagation()}><h2 id="report-title" className="text-lg font-semibold">Gửi báo cáo</h2><p className="mt-1 text-sm text-[#666]">Báo cáo sẽ được quản trị viên xem xét.</p>{reportError && <p role="alert" className="mt-3 text-sm text-red-700">{reportError}</p>}<form className="mt-4 grid gap-3" onSubmit={submitReport}><label className="text-sm">Nội dung báo cáo<select className="mt-1 block h-11 w-full border border-[#ddd] bg-white px-3" value={reportTarget} onChange={(event) => setReportTarget(event.target.value as 'product' | 'user')}><option value="product">Sản phẩm: {product.title}</option><option value="user">Người bán</option></select></label><label className="text-sm">Lý do<textarea className="mt-1 block w-full border border-[#ddd] p-3" minLength={5} maxLength={500} rows={3} required value={reportReason} onChange={(event) => setReportReason(event.target.value)} placeholder="Mô tả lý do báo cáo" /></label><label className="text-sm">Thông tin bổ sung (không bắt buộc)<textarea className="mt-1 block w-full border border-[#ddd] p-3" maxLength={2000} rows={3} value={reportDetails} onChange={(event) => setReportDetails(event.target.value)} placeholder="Cung cấp thêm chi tiết nếu cần" /></label><div className="mt-1 flex justify-end gap-2"><button type="button" className="h-10 border border-[#ddd] px-4 text-sm" onClick={() => setReportOpen(false)}>Đóng</button><button type="submit" disabled={busy} className="h-10 bg-[#ee4d2d] px-4 text-sm font-semibold text-white disabled:opacity-60">{busy ? 'Đang gửi…' : 'Gửi báo cáo'}</button></div></form></section></div>}
  </main>
}
