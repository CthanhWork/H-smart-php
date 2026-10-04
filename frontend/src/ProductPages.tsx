import { useEffect, useState, type FormEvent } from 'react'
import { conditionLabel, productRequest, statusLabel, type Product, type ProductPage } from './productApi'

type Props = { path: string; accessToken: string | null; userId: number | null; navigate: (path: string) => void }
const money = (amount: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount)

export default function ProductPages({ path, accessToken, userId, navigate }: Props) {
  const mine = path === '/me/products'
  const detailId = path.match(/^\/products\/(\d+)$/)?.[1]
  const [items, setItems] = useState<ProductPage | null>(null)
  const [product, setProduct] = useState<Product | null>(null)
  const [q, setQ] = useState('')
  const [draftQ, setDraftQ] = useState('')
  const [condition, setCondition] = useState('')
  const [status, setStatus] = useState('')
  const [minPrice, setMinPrice] = useState('')
  const [maxPrice, setMaxPrice] = useState('')
  const [draftMinPrice, setDraftMinPrice] = useState('')
  const [draftMaxPrice, setDraftMaxPrice] = useState('')
  const [page, setPage] = useState(0)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)
  const [version, setVersion] = useState(0)

  useEffect(() => {
    if (!accessToken) return
    let cancelled = false
    if (detailId) {
      productRequest<Product>(`products/${detailId}`, accessToken)
        .then((result) => { if (!cancelled) setProduct(result.data) })
        .catch((cause: Error) => { if (!cancelled) setError(cause.message) })
        .finally(() => { if (!cancelled) setLoading(false) })
    } else {
      const params = new URLSearchParams({ 'page[number]': String(page), 'page[size]': '12' })
      if (q.trim()) params.set('q', q.trim())
      if (condition) params.set('filter[condition]', condition)
      if (mine && status) params.set('filter[status]', status)
      if (minPrice) params.set('filter[min_price]', minPrice)
      if (maxPrice) params.set('filter[max_price]', maxPrice)
      productRequest<ProductPage>(`${mine ? 'me/products' : 'products'}?${params}`, accessToken)
        .then((result) => { if (!cancelled) setItems(result.data) })
        .catch((cause: Error) => { if (!cancelled) setError(cause.message) })
        .finally(() => { if (!cancelled) setLoading(false) })
    }
    return () => { cancelled = true }
  }, [accessToken, condition, detailId, maxPrice, minPrice, mine, page, q, status, version])

  async function remove() {
    if (!accessToken || !product || !window.confirm('Xóa sản phẩm này?')) return
    try {
      await productRequest<null>(`products/${product.id}`, accessToken, { method: 'DELETE' })
      navigate('/me/products')
    } catch (cause) { setError((cause as Error).message) }
  }

  function search(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setLoading(true)
    setError('')
    setQ(draftQ)
    setMinPrice(draftMinPrice)
    setMaxPrice(draftMaxPrice)
    setPage(0)
    setVersion((current) => current + 1)
  }

  if (!accessToken) return <section className="product-area"><div className="card"><h1>Đăng nhập để xem sản phẩm</h1><button className="primary-button" onClick={() => navigate('/')}>Về trang đăng nhập</button></div></section>

  if (detailId) return <section className="product-area">
    <button className="sell-back" onClick={() => navigate('/products')}>← Danh sách sản phẩm</button>
    {loading && <p>Đang tải sản phẩm…</p>}
    {error && <div className="alert error" role="alert">{error}</div>}
    {product && <article className="card product-detail">
      <div className="product-gallery">{product.images.length ? product.images.map((image) => <img key={image.id} src={image.url} alt={product.title} />) : <div className="product-placeholder">Chưa có ảnh</div>}</div>
      <div><span className="product-status">{statusLabel[product.status] ?? product.status}</span>
        <h1>{product.title}</h1><strong className="product-price">{money(product.price)}</strong>
        <p>Tình trạng: {conditionLabel[product.condition] ?? product.condition}</p>
        <p className="product-description">{product.description}</p>
        {product.seller_id === userId && <div className="product-actions"><button className="primary-button" disabled={['sold', 'reserved'].includes(product.status)} onClick={() => navigate(`/products/${product.id}/edit`)}>Chỉnh sửa</button><button className="danger-button" disabled={['sold', 'reserved'].includes(product.status)} onClick={remove}>Xóa sản phẩm</button></div>}
      </div>
    </article>}
  </section>

  return <section className="product-area">
    <div className="product-heading"><div><span className="eyebrow">SẢN PHẨM</span><h1>{mine ? 'Sản phẩm của tôi' : 'Đang được bán'}</h1></div><div className="product-actions"><button onClick={() => navigate(mine ? '/products' : '/me/products')}>{mine ? 'Xem sản phẩm đang bán' : 'Sản phẩm của tôi'}</button><button className="primary-button" onClick={() => navigate('/sell')}>+ Đăng bán</button></div></div>
    <form className="product-filters" onSubmit={search}>
      <label>Tìm theo tên<input value={draftQ} onChange={(event) => setDraftQ(event.target.value)} placeholder="Tên sản phẩm" /></label>
      <label>Tình trạng<select value={condition} onChange={(event) => { setCondition(event.target.value); setPage(0); setLoading(true); setError('') }}><option value="">Tất cả</option>{Object.entries(conditionLabel).map(([key, value]) => <option key={key} value={key}>{value}</option>)}</select></label>
      {mine && <label>Trạng thái<select value={status} onChange={(event) => { setStatus(event.target.value); setPage(0); setLoading(true); setError('') }}><option value="">Tất cả</option>{Object.entries(statusLabel).map(([key, value]) => <option key={key} value={key}>{value}</option>)}</select></label>}
      <label>Giá từ<input type="number" min="1" value={draftMinPrice} onChange={(event) => setDraftMinPrice(event.target.value)} /></label>
      <label>Đến<input type="number" min="1" value={draftMaxPrice} onChange={(event) => setDraftMaxPrice(event.target.value)} /></label>
      <button className="primary-button" type="submit">Tìm kiếm</button>
    </form>
    {loading && <p>Đang tải sản phẩm…</p>}
    {error && <div className="alert error" role="alert">{error}</div>}
    {items && !loading && <><p className="product-count">{items.totalElements} sản phẩm</p>
      {items.content.length ? <div className="product-grid">{items.content.map((item) => <button className="product-card" key={item.id} onClick={() => navigate(`/products/${item.id}`)}>
        {item.images[0] ? <img src={item.images[0].url} alt="" /> : <div className="product-placeholder">Chưa có ảnh</div>}
        <div className="product-card-body"><span className="product-status">{statusLabel[item.status] ?? item.status}</span><h2>{item.title}</h2><strong>{money(item.price)}</strong><p>{conditionLabel[item.condition] ?? item.condition}</p></div>
      </button>)}</div> : <div className="card empty-products">Chưa có sản phẩm phù hợp.</div>}
      <div className="pagination"><button disabled={page === 0} onClick={() => { setPage(page - 1); setLoading(true); setError('') }}>← Trước</button><span>Trang {page + 1} / {Math.max(items.totalPages, 1)}</span><button disabled={items.last} onClick={() => { setPage(page + 1); setLoading(true); setError('') }}>Sau →</button></div>
    </>}
  </section>
}
