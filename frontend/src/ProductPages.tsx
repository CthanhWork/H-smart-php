import { useEffect, useState, type FormEvent } from 'react'
import { productRequest, type Product, type ProductPage, type Category, conditionLabel, statusLabel } from './productApi'
import ProductCard from './components/ProductCard'
import { Button } from './components/ui/button'

type Props = { accessToken: string; navigate: (path: string) => void; mine?: boolean }

export function ProductListPage({ accessToken, navigate, mine = false }: Props) {
  const params = new URLSearchParams(window.location.search)
  const initialQ = params.get('q') ?? ''
  const initialCategory = params.get('category') ?? ''
  const [products, setProducts] = useState<Product[]>([])
  const [categories, setCategories] = useState<Category[]>([])
  const [q, setQ] = useState(initialQ)
  const [category, setCategory] = useState(initialCategory)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(0)
  const [totalPages, setTotalPages] = useState(0)

  useEffect(() => {
    productRequest<Category[]>('categories', accessToken)
      .then(({ data }) => setCategories(data))
      .catch(() => { /* ignore */ })
  }, [accessToken])

  useEffect(() => {
    let cancelled = false
    const p = new URLSearchParams({ 'page[number]': String(page), 'page[size]': '12' })
    if (q) p.append('q', q)
    if (category) p.append('filter[category_id]', category)
    productRequest<ProductPage>(`${mine ? 'me/products' : 'products'}?${p}`, accessToken)
      .then(({ data }) => { if (!cancelled) { setProducts(data.content); setTotalPages(data.totalPages) } })
      .catch((cause: Error) => { if (!cancelled) setError(cause.message) })
      .finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true }
  }, [accessToken, q, category, page, mine])

  return (
    <main className="max-w-frame mx-auto px-4 xl:px-0 py-10">
      <h1 className="font-satoshi font-bold tracking-tight text-2xl md:text-4xl uppercase">{mine ? 'Sản phẩm của tôi' : 'Đang bán'}</h1>
      <p className="text-sm text-black/60 mt-2">{mine ? 'Theo dõi trạng thái các sản phẩm bạn đã đăng.' : 'Tìm sách, thiết bị học tập và đồ dùng đã qua sử dụng.'}</p>

      <div className="mt-6 flex flex-col sm:flex-row gap-3">
        <form
          onSubmit={(e) => { e.preventDefault(); setPage(0); setQ((s) => s) }}
          className="flex-1 flex items-center gap-2 bg-muted rounded-full px-5 py-3"
        >
          <input
            value={q}
            onChange={(e) => { setLoading(true); setError(''); setQ(e.target.value) }}
            placeholder="Tìm theo tên sản phẩm…"
            className="bg-transparent outline-none text-sm flex-1"
          />
        </form>
      </div>

      {categories.length > 0 && (
        <div className="mt-4 flex gap-2 overflow-x-auto pb-2">
          <button
            onClick={() => { setLoading(true); setError(''); setCategory(''); setPage(0) }}
            className={`shrink-0 rounded-full px-4 py-2 text-xs font-medium transition-colors ${
              category === '' ? 'bg-black text-white' : 'border border-black/20 hover:bg-black hover:text-white'
            }`}
          >
            Tất cả
          </button>
          {categories.map((c) => (
            <button
              key={c.id}
              onClick={() => { setLoading(true); setError(''); setCategory(String(c.id)); setPage(0) }}
              className={`shrink-0 rounded-full px-4 py-2 text-xs transition-colors ${
                category === String(c.id) ? 'bg-black text-white' : 'border border-black/20 hover:bg-black hover:text-white'
              }`}
            >
              {c.name}
            </button>
          ))}
        </div>
      )}

      {error && <p className="text-sm text-[#FF3333] mt-4">{error}</p>}

      <div className="mt-8">
        {loading ? (
          <p className="text-sm text-black/60">Đang tải sản phẩm…</p>
        ) : products.length === 0 ? (
          <p className="text-sm text-black/60">Không tìm thấy sản phẩm phù hợp.</p>
        ) : (
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
            {products.map((p) => (
              <ProductCard
                key={p.id}
                product={p}
                onClick={() => navigate(`/products/${p.id}`)}
              />
            ))}
          </div>
        )}

        {totalPages > 1 && (
          <div className="mt-8 flex items-center justify-center gap-2">
            <button
              onClick={() => { setLoading(true); setError(''); setPage((p) => Math.max(0, p - 1)) }}
              disabled={page === 0}
              className="px-4 py-2 rounded-full border border-black/20 text-sm disabled:opacity-50"
            >
              Trước
            </button>
            <span className="text-sm text-black/60">Trang {page + 1} / {totalPages}</span>
            <button
              onClick={() => { setLoading(true); setError(''); setPage((p) => Math.min(totalPages - 1, p + 1)) }}
              disabled={page >= totalPages - 1}
              className="px-4 py-2 rounded-full border border-black/20 text-sm disabled:opacity-50"
            >
              Sau
            </button>
          </div>
        )}
      </div>
    </main>
  )
}

export function ProductDetailPage({ accessToken, userId, navigate }: Props & { userId: number | null }) {
  const productId = window.location.pathname.split('/').pop()
  const [product, setProduct] = useState<Product | null>(null)
  const [error, setError] = useState('')
  const [activeImage, setActiveImage] = useState(0)
  const [busy, setBusy] = useState(false)
  const [offerPrice, setOfferPrice] = useState('')
  const [offerError, setOfferError] = useState('')
  const [offerNotice, setOfferNotice] = useState('')

  useEffect(() => {
    let cancelled = false
    productRequest<Product>(`products/${productId}`, accessToken)
      .then(({ data }) => { if (!cancelled) setProduct(data) })
      .catch((cause: Error) => { if (!cancelled) setError(cause.message) })
    return () => { cancelled = true }
  }, [accessToken, productId])

  async function contactSeller() {
    if (!product || userId == null) {
      navigate('/')
      return
    }
    setBusy(true)
    navigate(`/me/messages?productId=${product.id}&userId=${product.seller_id}`)
  }

  async function submitOffer(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!product || !accessToken || busy) return
    setBusy(true); setOfferError(''); setOfferNotice('')
    try {
      const result = await productRequest<unknown>('offers', accessToken, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: product.id, offer_price: Number(offerPrice) }),
      })
      setOfferNotice(result.message)
      navigate('/me/offers')
    } catch (cause) {
      setOfferError((cause as Error).message)
    } finally {
      setBusy(false)
    }
  }

  if (error) {
    return <main className="max-w-frame mx-auto px-4 py-10"><p className="text-[#FF3333]">{error}</p></main>
  }
  if (!product) {
    return <main className="max-w-frame mx-auto px-4 py-10"><p className="text-black/60">Đang tải sản phẩm…</p></main>
  }

  const isOwner = userId === product.seller_id

  return (
    <main className="max-w-frame mx-auto px-4 xl:px-0 py-10">
      <button onClick={() => navigate('/products')} className="text-sm text-black/60 hover:text-black mb-6">
        ← Quay lại danh sách
      </button>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12">
        {/* Gallery */}
        <div>
          <div className="aspect-square bg-tile rounded-2xl overflow-hidden mb-3">
            {product.images[activeImage]?.url ? (
              <img
                src={product.images[activeImage].url}
                alt={product.title}
                className="w-full h-full object-cover"
              />
            ) : (
              <div className="w-full h-full flex items-center justify-center text-black/30">Không có ảnh</div>
            )}
          </div>
          {product.images.length > 1 && (
            <div className="flex gap-2 overflow-x-auto">
              {product.images.map((img, i) => (
                <button
                  key={img.id}
                  onClick={() => setActiveImage(i)}
                  className={`shrink-0 w-20 h-20 rounded-xl overflow-hidden border-2 ${
                    i === activeImage ? 'border-black' : 'border-transparent'
                  }`}
                >
                  <img src={img.url} alt="" className="w-full h-full object-cover" />
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Info */}
        <div>
          <h1 className="font-satoshi font-bold tracking-tight text-2xl md:text-4xl uppercase leading-tight">{product.title}</h1>
          <p className="text-2xl md:text-3xl font-bold mt-4">
            {new Intl.NumberFormat('vi-VN').format(product.price)} đ
            {product.negotiable && <span className="text-base font-normal text-black/60 ml-2">(có thể thương lượng)</span>}
          </p>

          <div className="mt-4 flex flex-wrap gap-2 text-xs">
            {product.category && (
              <span className="bg-muted rounded-full px-3 py-1">{product.category.name}</span>
            )}
            <span className="bg-muted rounded-full px-3 py-1">Tình trạng: {conditionLabel[product.condition] ?? product.condition}</span>
            <span className="bg-muted rounded-full px-3 py-1">Trạng thái: {statusLabel[product.status] ?? product.status}</span>
          </div>

          <div className="mt-6">
            <h2 className="font-bold text-sm uppercase mb-2">Mô tả</h2>
            <p className="text-sm text-black/70 whitespace-pre-wrap leading-relaxed">{product.description}</p>
          </div>

          <div className="mt-8 flex flex-col gap-3">
            {!isOwner && (
              <>
                {product.negotiable && product.status === 'active' && <form onSubmit={submitOffer} className="rounded-2xl border border-black/10 bg-[#F7F7F7] p-4 sm:p-5">
                  <h2 className="font-bold">Đề nghị mức giá của bạn</h2>
                  <p className="mt-1 text-sm text-black/55">{product.min_price ? `Người bán nhận từ ${new Intl.NumberFormat('vi-VN').format(product.min_price)} đ.` : 'Nhập mức giá bạn muốn đề nghị.'}</p>
                  {offerError && <p role="alert" className="mt-3 text-sm text-[#FF3333]">{offerError}</p>}
                  {offerNotice && <p role="status" className="mt-3 text-sm text-[#2f7049]">{offerNotice}</p>}
                  <label className="mt-4 block text-sm font-medium">Giá đề nghị (VND)<input className="mt-1.5 w-full rounded-full border border-black/15 bg-white px-4 py-3 text-sm outline-none focus:border-black" type="number" min={product.min_price ?? 1} max={product.price} step="1" required value={offerPrice} onChange={(event) => setOfferPrice(event.target.value)} /></label>
                  <Button className="mt-3 w-full" disabled={busy}>{busy ? 'Đang gửi…' : 'Gửi đề nghị mua'}</Button>
                </form>}
                <button
                  onClick={contactSeller}
                  disabled={busy}
                  className="rounded-full bg-black text-white px-6 py-3 text-sm font-medium hover:bg-black/80 transition-colors disabled:opacity-50"
                >
                  {busy ? 'Đang mở cuộc trò chuyện…' : 'Nhắn tin người bán'}
                </button>
              </>
            )}
            {isOwner && (
              <p className="text-sm text-black/60">Bạn là người bán sản phẩm này.</p>
            )}
          </div>
        </div>
      </div>
    </main>
  )
}
