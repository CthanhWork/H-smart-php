import { useEffect, useState, type FormEvent } from 'react'
import { Heart, Search, SlidersHorizontal } from 'lucide-react'
import { productRequest, type Product, type ProductPage, type Category } from './productApi'
import ProductCard from './components/ProductCard'

type Props = { accessToken: string; navigate: (path: string) => void; mine?: boolean; favorites?: boolean }
const conditions = [
  { id: '', label: 'Mọi tình trạng' },
  { id: 'new', label: 'Mới' },
  { id: 'like_new', label: 'Như mới' },
  { id: 'good', label: 'Còn tốt' },
  { id: 'fair', label: 'Đã qua sử dụng' },
]
const controlClass = 'h-11 w-full rounded-xl border border-black/10 bg-white px-3 text-sm outline-none focus:border-[#e65332]'

export function ProductListPage({ accessToken, navigate, mine = false, favorites = false }: Props) {
  const params = new URLSearchParams(window.location.search)
  const [products, setProducts] = useState<Product[]>([])
  const [categories, setCategories] = useState<Category[]>([])
  const [query, setQuery] = useState(params.get('q') ?? '')
  const [q, setQ] = useState(params.get('q') ?? '')
  const [category, setCategory] = useState(params.get('category') ?? '')
  const [condition, setCondition] = useState('')
  const [minPrice, setMinPrice] = useState('')
  const [maxPrice, setMaxPrice] = useState('')
  const [sort, setSort] = useState('newest')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [page, setPage] = useState(0)
  const [totalPages, setTotalPages] = useState(0)
  const [favoriteBusyIds, setFavoriteBusyIds] = useState<Set<number>>(() => new Set())

  useEffect(() => {
    if (favorites || mine) return
    productRequest<Category[]>('categories', accessToken)
      .then(({ data }) => setCategories(data))
      .catch(() => { /* Categories are optional for browsing. */ })
  }, [accessToken, favorites, mine])

  useEffect(() => {
    let cancelled = false
    const p = new URLSearchParams({ 'page[number]': String(page), 'page[size]': '12' })
    if (q.trim()) p.set('q', q.trim())
    if (category && !favorites) p.set('filter[category_id]', category)
    if (condition && !favorites) p.set('filter[condition]', condition)
    if (minPrice && !favorites) p.set('filter[min_price]', minPrice)
    if (maxPrice && !favorites) p.set('filter[max_price]', maxPrice)
    if (sort && !favorites) p.set('sort', sort)
    const endpoint = favorites ? `me/favorites?${p}` : `${mine ? 'me/products' : 'products'}?${p}`
    setLoading(true)
    setError('')
    productRequest<ProductPage>(endpoint, accessToken)
      .then(({ data }) => {
        if (!cancelled) { setProducts(data.content); setTotalPages(data.totalPages) }
      })
      .catch((cause: Error) => { if (!cancelled) setError(cause.message) })
      .finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true }
  }, [accessToken, q, category, condition, minPrice, maxPrice, sort, page, mine, favorites])

  async function toggleFavorite(product: Product) {
    if (!accessToken || favoriteBusyIds.has(product.id)) return
    setFavoriteBusyIds((current) => new Set(current).add(product.id))
    setError(''); setNotice('')
    try {
      const result = product.is_favorite
        ? await productRequest<null>(`me/favorites/${product.id}`, accessToken, { method: 'DELETE' })
        : await productRequest<null>('me/favorites', accessToken, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ product_id: product.id }) })
      if (favorites) {
        setProducts((current) => current.filter((item) => item.id !== product.id))
      } else {
        setProducts((current) => current.map((item) => item.id === product.id ? { ...item, is_favorite: !product.is_favorite } : item))
      }
      setNotice(result.message)
    } catch (cause) { setError((cause as Error).message) }
    finally { setFavoriteBusyIds((current) => { const next = new Set(current); next.delete(product.id); return next }) }
  }

  function applySearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setPage(0); setQ(query); setNotice('')
  }

  const title = favorites ? 'Sản phẩm yêu thích' : mine ? 'Sản phẩm của tôi' : 'Khám phá sản phẩm'

  return <main className="mx-auto max-w-frame px-4 py-10 xl:px-0">
    <div className="mb-6 flex items-end justify-between gap-4"><div><p className="text-xs font-medium uppercase tracking-[0.16em] text-black/50">Chợ đồ cũ sinh viên</p><h1 className="mt-2 font-satoshi text-2xl font-bold uppercase tracking-tight sm:text-4xl">{title}</h1><p className="mt-2 text-sm text-black/60">{favorites ? 'Các món đồ bạn đã lưu để xem lại.' : mine ? 'Theo dõi trạng thái các sản phẩm bạn đã đăng.' : 'Lọc theo danh mục, tình trạng và khoảng giá để tìm món phù hợp.'}</p></div>{favorites && <Heart className="hidden text-[#e65332] sm:block" size={28} />}</div>

    {!favorites && !mine && <>
      <form onSubmit={applySearch} className="flex gap-2 rounded-2xl border border-black/10 bg-[#f6f7f8] p-2 focus-within:border-[#e65332]/60">
        <Search className="ml-2 mt-2.5 shrink-0 text-black/40" size={19} />
        <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Tìm theo tên sản phẩm…" aria-label="Tìm theo tên sản phẩm" className="min-w-0 flex-1 bg-transparent px-1 py-2 text-sm outline-none" />
        <button type="submit" className="rounded-xl bg-[#e65332] px-4 text-sm font-semibold text-white hover:bg-[#cc4528]">Tìm kiếm</button>
      </form>

      {categories.length > 0 && <div className="mt-4 flex gap-2 overflow-x-auto pb-2">
        <button type="button" onClick={() => { setCategory(''); setPage(0) }} className={`shrink-0 rounded-full px-4 py-2 text-xs font-medium ${category === '' ? 'bg-black text-white' : 'border border-black/20 hover:bg-black hover:text-white'}`}>Tất cả danh mục</button>
        {categories.map((item) => <button key={item.id} type="button" onClick={() => { setCategory(String(item.id)); setPage(0) }} className={`shrink-0 rounded-full px-4 py-2 text-xs ${category === String(item.id) ? 'bg-black text-white' : 'border border-black/20 hover:bg-black hover:text-white'}`}>{item.name}</button>)}
      </div>}

      <section aria-label="Bộ lọc sản phẩm" className="mt-4 grid gap-3 rounded-2xl border border-black/10 bg-[#fafafa] p-4 sm:grid-cols-2 lg:grid-cols-5">
        <label className="text-xs font-medium text-black/60">Tình trạng<select className={`${controlClass} mt-1`} value={condition} onChange={(event) => { setCondition(event.target.value); setPage(0) }}>{conditions.map((item) => <option key={item.id} value={item.id}>{item.label}</option>)}</select></label>
        <label className="text-xs font-medium text-black/60">Giá từ (₫)<input className={`${controlClass} mt-1`} type="number" min="1" step="1000" placeholder="Ví dụ: 100000" value={minPrice} onChange={(event) => { setMinPrice(event.target.value); setPage(0) }} /></label>
        <label className="text-xs font-medium text-black/60">Giá đến (₫)<input className={`${controlClass} mt-1`} type="number" min="1" step="1000" placeholder="Ví dụ: 5000000" value={maxPrice} onChange={(event) => { setMaxPrice(event.target.value); setPage(0) }} /></label>
        <label className="text-xs font-medium text-black/60">Sắp xếp<select className={`${controlClass} mt-1`} value={sort} onChange={(event) => { setSort(event.target.value); setPage(0) }}><option value="newest">Mới nhất</option><option value="price_asc">Giá thấp đến cao</option><option value="price_desc">Giá cao đến thấp</option></select></label>
        <button type="button" onClick={() => { setQuery(''); setQ(''); setCategory(''); setCondition(''); setMinPrice(''); setMaxPrice(''); setSort('newest'); setPage(0) }} className="mt-auto inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-black/10 bg-white px-3 text-sm font-medium hover:border-[#e65332] hover:text-[#e65332]"><SlidersHorizontal size={16} />Xóa bộ lọc</button>
      </section>
    </>}

    {notice && <p role="status" className="mt-4 text-sm text-emerald-700">{notice}</p>}
    {error && <p role="alert" className="mt-4 text-sm text-red-700">{error}</p>}
    <div className="mt-6">
      {loading ? <p className="text-sm text-black/60">Đang tải sản phẩm…</p> : products.length === 0 ? <div className="rounded-2xl border border-dashed border-black/20 p-10 text-center text-sm text-black/60">{favorites ? 'Bạn chưa lưu sản phẩm nào.' : 'Không tìm thấy sản phẩm phù hợp.'}</div> : <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">{products.map((product) => <ProductCard key={product.id} product={product} onClick={() => navigate(`/products/${product.id}`)} onToggleFavorite={accessToken ? () => toggleFavorite(product) : undefined} favoriteBusy={favoriteBusyIds.has(product.id)} />)}</div>}
      {totalPages > 1 && <div className="mt-8 flex items-center justify-center gap-2"><button type="button" onClick={() => setPage((current) => Math.max(0, current - 1))} disabled={page === 0 || loading} className="rounded-full border border-black/20 px-4 py-2 text-sm disabled:opacity-40">Trước</button><span className="text-sm text-black/60">Trang {page + 1} / {totalPages}</span><button type="button" onClick={() => setPage((current) => Math.min(totalPages - 1, current + 1))} disabled={page >= totalPages - 1 || loading} className="rounded-full border border-black/20 px-4 py-2 text-sm disabled:opacity-40">Sau</button></div>}
    </div>
  </main>
}

export { default as ProductDetailPage } from './ProductDetailPage'
