import { useEffect, useRef, useState, type FormEvent } from 'react'
import { productRequest, type Product, type ProductImage } from './productApi'

type FieldErrors = Record<string, string[]>
type VisionDetection = { label: string; translated_label: string; class_id: number; score: number; bbox: number[] }
type VisionResult = { label: string; translated_label: string; confidence: number; num_detections: number; detections: VisionDetection[] }
type CreateResponse = {
  status: 'success' | 'error'
  message: string
  data: { id: number; status: string } | null
  errors?: FieldErrors
}

type Props = {
  accessToken: string | null
  onBack: () => void
  editId?: number
  onSaved?: (id: number) => void
}

export default function SellPage({ accessToken, onBack, editId, onSaved }: Props) {
  const fileInput = useRef<HTMLInputElement>(null)
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [price, setPrice] = useState('')
  const [categoryId, setCategoryId] = useState('')
  const [categories, setCategories] = useState<Array<{ id: number; name: string }>>([])
  const [condition, setCondition] = useState('good')
  const [negotiable, setNegotiable] = useState(false)
  const [minPrice, setMinPrice] = useState('')
  const [images, setImages] = useState<File[]>([])
  const [imagePreviews, setImagePreviews] = useState<string[]>([])
  const [existingImages, setExistingImages] = useState<ProductImage[]>([])
  const [removedIds, setRemovedIds] = useState<number[]>([])
  const [errors, setErrors] = useState<FieldErrors>({})
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [loading, setLoading] = useState(false)
  const [visionLoading, setVisionLoading] = useState(false)
  const [visionError, setVisionError] = useState('')
  const [visionResult, setVisionResult] = useState<VisionResult | null>(null)
  const [descriptionLoading, setDescriptionLoading] = useState(false)
  const [descriptionError, setDescriptionError] = useState('')

  useEffect(() => {
    const urls = images.map((image) => URL.createObjectURL(image))
    setImagePreviews(urls)
    return () => urls.forEach((url) => URL.revokeObjectURL(url))
  }, [images])

  useEffect(() => {
    if (!accessToken) return
    productRequest<Array<{ id: number; name: string }>>('categories', accessToken)
      .then(({ data }) => { setCategories(data); if (!editId && data[0]) setCategoryId(String(data[0].id)) })
      .catch((cause: Error) => setError(cause.message))
  }, [accessToken, editId])

  useEffect(() => {
    if (!editId || !accessToken) return
    let cancelled = false
    productRequest<Product>(`products/${editId}`, accessToken)
      .then(({ data }) => {
        if (cancelled) return
        setTitle(data.title); setDescription(data.description); setPrice(String(data.price))
        setCategoryId(String(data.category_id ?? data.category?.id ?? ''))
        setCondition(data.condition); setExistingImages(data.images); setRemovedIds([])
        setNegotiable(data.negotiable); setMinPrice(data.min_price ? String(data.min_price) : '')
      })
      .catch((cause: Error) => { if (!cancelled) setError(cause.message) })
    return () => { cancelled = true }
  }, [accessToken, editId])

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!accessToken || loading) return
    setErrors({})
    setError('')
    setNotice('')
    const remainingImages = existingImages.filter((image) => !removedIds.includes(image.id)).length
    if (remainingImages + images.length > 5) {
      setErrors({ images: ['Tối đa 5 ảnh cho một sản phẩm.'] })
      return
    }
    if (remainingImages + images.length < 1) {
      setErrors({ images: ['Vui lòng thêm ít nhất một ảnh rõ nét cho sản phẩm.'] })
      return
    }
    if (images.some((image) => !['image/jpeg', 'image/png', 'image/webp'].includes(image.type) || image.size > 10 * 1024 * 1024)) {
      setErrors({ images: ['Chỉ nhận ảnh JPEG, PNG hoặc WebP, tối đa 10 MB mỗi ảnh.'] })
      return
    }
    setLoading(true)

    const body = new FormData()
    body.append('title', title.trim())
    body.append('description', description.trim())
    body.append('price', price)
    body.append('category_id', categoryId)
    body.append('condition', condition)
    body.append('negotiable', negotiable ? '1' : '0')
    if (negotiable && minPrice) body.append('min_price', minPrice)
    images.forEach((file) => body.append('images[]', file))
    if (editId) {
      body.append('_method', 'PATCH')
      removedIds.forEach((id) => body.append('remove_image_ids[]', String(id)))
      existingImages.filter((image) => !removedIds.includes(image.id)).forEach((image) => body.append('image_order[]', String(image.id)))
    }

    try {
      const response = await fetch(editId ? `/api/v1/products/${editId}` : '/api/v1/products', {
        method: 'POST',
        headers: { Accept: 'application/json', Authorization: `Bearer ${accessToken}` },
        body,
      })
      const result = await response.json() as CreateResponse
      if (!response.ok) {
        setErrors(result.errors ?? {})
        setError(result.message || 'Không thể đăng sản phẩm.')
        return
      }
      if (editId) { onSaved?.(editId); return }
      setNotice(`${result.message} Mã sản phẩm: ${result.data?.id}.`)
      setTitle('')
      setDescription('')
      setPrice('')
      if (categories[0]) setCategoryId(String(categories[0].id))
      setCondition('good')
      setNegotiable(false)
      setMinPrice('')
      setImages([])
      if (fileInput.current) fileInput.current.value = ''
    } catch {
      setError('Không kết nối được với máy chủ. Thử lại sau nhé.')
    } finally {
      setLoading(false)
    }
  }

  async function analyzeSelectedImage() {
    if (!accessToken || !images[0] || visionLoading) return
    setVisionLoading(true)
    setVisionError('')
    setVisionResult(null)
    const body = new FormData()
    body.append('image', images[0])
    try {
      const result = await productRequest<VisionResult>('assistant/analyze-image', accessToken, { method: 'POST', body })
      setVisionResult(result.data)
    } catch (cause) {
      setVisionError(cause instanceof Error ? cause.message : 'Không phân tích được ảnh lúc này.')
    } finally {
      setVisionLoading(false)
    }
  }

  async function generateDescription() {
    if (!accessToken || !title.trim() || !categoryId || descriptionLoading) return
    setDescriptionLoading(true)
    setDescriptionError('')
    const body = new FormData()
    const category = categories.find((item) => String(item.id) === categoryId)
    body.append('title', title.trim())
    body.append('category', category?.name ?? 'Sản phẩm')
    body.append('condition', condition)
    body.append('price', price)
    body.append('seller_notes', description.trim())
    if (images[0]) body.append('image', images[0])
    try {
      const result = await productRequest<{ description: string; vision: VisionResult | null }>(
        'assistant/generate-listing-description', accessToken, { method: 'POST', body },
      )
      setDescription(result.data.description)
      if (result.data.vision) setVisionResult(result.data.vision)
    } catch (cause) {
      setDescriptionError(cause instanceof Error ? cause.message : 'Không tạo được mô tả lúc này.')
    } finally {
      setDescriptionLoading(false)
    }
  }

  if (!accessToken) {
    return (
      <main className="max-w-frame mx-auto px-4 xl:px-0 py-16">
        <div className="max-w-lg mx-auto rounded-3xl border border-black/10 bg-white p-8 md:p-10 text-center shadow-sm">
          <span className="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#f1f0ed] text-2xl">＋</span>
          <span className="mt-5 block text-xs uppercase tracking-wider text-black/55">{editId ? 'Chỉnh sửa sản phẩm' : 'Gian hàng H‑Smart'}</span>
          <h1 className="font-satoshi font-bold tracking-tight text-3xl md:text-4xl mt-2">Bắt đầu bán món đồ của bạn</h1>
          <button
            type="button"
            onClick={onBack}
            className="mt-6 inline-flex items-center justify-center rounded-full bg-black text-white px-7 py-3 text-sm font-medium hover:bg-black/80 transition-colors"
          >
            Đăng nhập để tiếp tục
          </button>
        </div>
      </main>
    )
  }

  const categoryName = categories.find((item) => String(item.id) === categoryId)?.name ?? 'Chưa chọn danh mục'
  const conditionName: Record<string, string> = { new: 'Mới', like_new: 'Như mới', good: 'Còn tốt', fair: 'Đã qua sử dụng' }
  const previewUrl = imagePreviews[0] ?? existingImages.find((image) => !removedIds.includes(image.id))?.url
  const totalImages = existingImages.filter((image) => !removedIds.includes(image.id)).length + images.length

  return (
    <main className="max-w-frame mx-auto px-4 xl:px-0 py-8 md:py-12">
      <button onClick={onBack} className="text-sm text-black/60 hover:text-black mb-5">← Quay lại</button>

      <div className="mb-8 md:mb-10">
        <span className="text-xs uppercase tracking-wider text-black/55">{editId ? 'Chỉnh sửa sản phẩm' : 'Gian hàng H‑Smart'}</span>
        <h1 className="font-satoshi font-bold tracking-tight text-3xl md:text-5xl mt-2">{editId ? 'Hoàn thiện tin đăng' : 'Đăng bán thật dễ'}</h1>
        <nav aria-label="Các bước đăng bán" className="mt-7 grid grid-cols-3 max-w-2xl">
          {[['01', 'Ảnh sản phẩm', 'sell-photos'], ['02', 'Thông tin', 'sell-details'], ['03', 'Giá bán', 'sell-price']].map(([number, label, href]) => (
            <a key={href} href={`#${href}`} className="flex items-center gap-2 border-t-2 border-black/15 pt-3 text-xs md:text-sm text-black/65 hover:text-black"><span className="font-semibold text-black">{number}</span>{label}</a>
          ))}
        </nav>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-8 lg:gap-12 items-start">
        <div className="min-w-0">

          {notice && (
            <div className="mt-4 rounded-lg border border-[#2f7049]/30 bg-[#2f7049]/10 text-[#2f7049] px-4 py-3 text-sm">{notice}</div>
          )}
          {error && (
            <div className="mt-4 rounded-lg border border-[#FF3333]/30 bg-[#FF3333]/10 text-[#FF3333] px-4 py-3 text-sm">{error}</div>
          )}

          <form onSubmit={submit} encType="multipart/form-data" className="mt-6 flex flex-col gap-5">
            <section id="sell-photos" className="scroll-mt-24 rounded-3xl border border-black/10 bg-white p-5 md:p-7">
              <div className="flex items-start justify-between gap-4"><div><p className="text-xs font-semibold uppercase tracking-wider text-black/50">Bước 1</p><h2 className="mt-1 text-xl font-semibold">Ảnh sản phẩm</h2></div><span className="shrink-0 rounded-full bg-[#f1f0ed] px-3 py-1 text-xs">{totalImages}/5 ảnh</span></div>
              {editId && existingImages.length > 0 && (
                <div className="mt-5"><strong className="text-xs uppercase tracking-wider text-black/55">Ảnh hiện tại · sắp xếp bằng mũi tên</strong><div className="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-3">
                  {existingImages.map((image, index) => <div key={image.id} className={`rounded-2xl overflow-hidden border bg-white p-2 ${removedIds.includes(image.id) ? 'opacity-50 border-red-300' : 'border-black/10'}`}>
                    <div className="relative"><img src={image.url} alt={`Ảnh ${index + 1}`} className="w-full aspect-square object-cover rounded-xl" /><button type="button" aria-label={removedIds.includes(image.id) ? `Hoàn tác xóa ảnh ${index + 1}` : `Xóa ảnh ${index + 1}`} onClick={() => setRemovedIds((current) => current.includes(image.id) ? current.filter((id) => id !== image.id) : [...current, image.id])} className={`absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-full text-xl leading-none shadow-md ${removedIds.includes(image.id) ? 'bg-black/75 text-white' : 'bg-white/95 text-red-600 hover:bg-red-50'}`}>{removedIds.includes(image.id) ? '↶' : '×'}</button></div>
                    <div className="flex gap-1 mt-2"><button type="button" aria-label="Đưa ảnh lên trước" disabled={index === 0} onClick={() => setExistingImages((current) => { const next = [...current]; [next[index - 1], next[index]] = [next[index], next[index - 1]]; return next })} className="flex-1 text-xs py-1 rounded-md border border-black/20 disabled:opacity-40">↑</button><button type="button" aria-label="Đưa ảnh ra sau" disabled={index === existingImages.length - 1} onClick={() => setExistingImages((current) => { const next = [...current]; [next[index], next[index + 1]] = [next[index + 1], next[index]]; return next })} className="flex-1 text-xs py-1 rounded-md border border-black/20 disabled:opacity-40">↓</button></div>
                  </div>)}
                </div></div>
              )}
              <label htmlFor="sell-image-input" className="mt-5 flex min-h-36 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-black/20 bg-[#faf9f7] px-5 py-6 text-center hover:border-black/50 hover:bg-[#f6f5f2]">
                <span className="text-3xl leading-none">＋</span><span className="mt-2 text-sm font-semibold">Chọn ảnh từ thiết bị</span><span className="mt-1 text-xs text-black/55">JPEG, PNG hoặc WebP · tối đa 10 MB mỗi ảnh</span>
                <input id="sell-image-input" ref={fileInput} type="file" accept="image/jpeg,image/png,image/webp" multiple onChange={(event) => {
                  const selected = Array.from(event.target.files ?? [])
                  const retainedCount = existingImages.filter((image) => !removedIds.includes(image.id)).length
                  if (retainedCount + images.length + selected.length > 5) { setErrors((current) => ({ ...current, images: ['Tối đa 5 ảnh cho một sản phẩm. Hãy bỏ bớt ảnh trước khi thêm.'] })); event.target.value = ''; return }
                  setErrors((current) => ({ ...current, images: [] })); setImages((current) => [...current, ...selected]); setVisionResult(null); setVisionError(''); event.target.value = ''
                }} className="sr-only" />
              </label>
              {imagePreviews.length > 0 && <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">{imagePreviews.map((src, index) => <div key={`${src}-${index}`} className="overflow-hidden rounded-xl border border-black/10 bg-white"><div className="relative"><img src={src} alt={`Ảnh chi tiết đã chọn ${index + 1}`} className="aspect-square w-full object-cover" /><button type="button" aria-label={`Xóa ảnh ${index + 1}`} onClick={() => { setImages((current) => current.filter((_, itemIndex) => itemIndex !== index)); setVisionResult(null); setVisionError('') }} className="absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-full bg-white/95 text-xl leading-none text-red-600 shadow-md hover:bg-red-50">×</button></div></div>)}</div>}
              {images.length > 0 && <div className="mt-4 rounded-2xl border border-black/10 p-4"><div className="flex flex-wrap items-center justify-between gap-3"><p className="text-sm font-medium">Nhận diện ảnh</p><button type="button" onClick={() => void analyzeSelectedImage()} disabled={visionLoading} className="rounded-full border border-black/15 bg-white px-4 py-2 text-xs font-medium hover:bg-black/[0.03] disabled:opacity-50">{visionLoading ? 'Đang phân tích…' : 'Phân tích ảnh'}</button></div>
                {visionError && <p className="mt-3 text-xs text-red-700" role="alert">{visionError}</p>}{visionResult && <div className="mt-3 flex flex-wrap items-center gap-3 border-t border-black/10 pt-3" role="status"><p className="text-sm">Nhận diện: <strong>{visionResult.translated_label}</strong> <span className="text-xs text-black/55">({Math.round(visionResult.confidence * 100)}%)</span></p>{visionResult.confidence > 0 && <button type="button" onClick={() => setTitle(visionResult.translated_label)} className="rounded-full bg-black px-3 py-1.5 text-xs font-medium text-white">Dùng tên này</button>}{visionResult.detections.length > 1 && <ul className="w-full space-y-1 text-xs text-black/55">{visionResult.detections.slice(0, 5).map((item, index) => <li key={`${item.class_id}-${index}`}>{item.translated_label} · {Math.round(item.score * 100)}%</li>)}</ul>}{visionResult.num_detections === 0 && <p className="w-full text-xs text-black/55">Không nhận diện được vật thể.</p>}</div>}</div>}
              {errors.images && <p className="mt-2 text-xs text-[#FF3333]">{errors.images[0]}</p>}
            </section>

            <section id="sell-details" className="scroll-mt-24 rounded-3xl border border-black/10 bg-white p-5 md:p-7">
              <p className="text-xs font-semibold uppercase tracking-wider text-black/50">Bước 2</p><h2 className="mt-1 mb-5 text-xl font-semibold">Thông tin sản phẩm</h2>
              <div>
              <label className="block text-sm font-medium mb-1.5">Tên sản phẩm <span className="text-black/40">· bắt buộc</span></label>
              <input
                value={title}
                onChange={(event) => setTitle(event.target.value)}
                required minLength={3} maxLength={200}
                className="w-full rounded-full border border-black/20 px-5 py-3 text-sm outline-none focus:border-black"
              />
              {errors.title && <p className="mt-1 text-xs text-[#FF3333]">{errors.title[0]}</p>}
            </div>

            <div className="mt-5">
              <label className="block text-sm font-medium mb-1.5">Mô tả / thông tin thêm</label>
              <textarea
                value={description}
                onChange={(event) => setDescription(event.target.value)}
                required minLength={10} maxLength={5000} rows={5}
                placeholder="Mô tả sản phẩm, tình trạng, phụ kiện đi kèm…"
                className="w-full rounded-2xl border border-black/20 px-5 py-3 text-sm outline-none focus:border-black resize-y"
              />
              {errors.description && <p className="mt-1 text-xs text-[#FF3333]">{errors.description[0]}</p>}
              <div className="mt-2 flex flex-wrap items-center gap-3">
                <button type="button" onClick={() => void generateDescription()} disabled={descriptionLoading || !title.trim() || !categoryId} className="rounded-full border border-black/15 bg-[#faf9f7] px-4 py-2 text-xs font-medium hover:bg-black/[0.05] disabled:opacity-50">
                  {descriptionLoading ? 'Đang tạo mô tả…' : 'Tạo mô tả bằng AI'}
                </button>
              </div>
              {descriptionError && <p className="mt-2 text-xs text-red-700" role="alert">{descriptionError}</p>}
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-5 mt-5">
              <div>
                <label className="block text-sm font-medium mb-1.5">Danh mục</label>
                <select
                  value={categoryId} onChange={(event) => setCategoryId(event.target.value)} required
                  className="w-full rounded-full border border-black/20 px-5 py-3 text-sm outline-none focus:border-black bg-white"
                >
                  <option value="" disabled>Chọn danh mục</option>
                  {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
              </div>
            </div>

            <div className="mt-5">
              <label className="block text-sm font-medium mb-1.5">Tình trạng</label>
              <select
                value={condition} onChange={(event) => setCondition(event.target.value)} required
                className="w-full rounded-full border border-black/20 px-5 py-3 text-sm outline-none focus:border-black bg-white"
              >
                <option value="new">Mới</option>
                <option value="like_new">Như mới</option>
                <option value="good">Còn tốt</option>
                <option value="fair">Đã qua sử dụng</option>
              </select>
              {errors.condition && <p className="mt-1 text-xs text-[#FF3333]">{errors.condition[0]}</p>}
            </div>

            </section>

            <section id="sell-price" className="scroll-mt-24 rounded-3xl border border-black/10 bg-white p-5 md:p-7">
              <p className="text-xs font-semibold uppercase tracking-wider text-black/50">Bước 3</p><h2 className="mt-1 mb-5 text-xl font-semibold">Đặt giá bán</h2>
              <label className="block text-sm font-medium mb-1.5">Giá bán (VND)</label>
              <input type="number" min="1" max="999999999999" step="1" value={price} onChange={(event) => setPrice(event.target.value)} required className="w-full rounded-2xl border border-black/20 px-5 py-3 text-sm outline-none focus:border-black" />
              {errors.price && <p className="mt-1 text-xs text-[#FF3333]">{errors.price[0]}</p>}
              <div className="mt-5 rounded-2xl bg-[#faf9f7] p-4">
                <label className="flex items-center gap-2 text-sm font-medium"><input type="checkbox" checked={negotiable} onChange={(event) => setNegotiable(event.target.checked)} className="accent-black w-4 h-4" />Cho phép người mua trả giá</label>
                {negotiable && <div className="mt-4"><label className="block text-sm font-medium mb-1.5">Mức giá thấp nhất bạn có thể nhận (VND)</label><input type="number" min="1" max={price || undefined} step="1" value={minPrice} onChange={(event) => setMinPrice(event.target.value)} required className="w-full rounded-2xl border border-black/20 px-5 py-3 text-sm outline-none focus:border-black bg-white" />{price && <p className="text-xs text-black/60 mt-1">Không vượt quá giá bán ({new Intl.NumberFormat('vi-VN').format(Number(price))} VND).</p>}{errors.min_price && <p className="mt-1 text-xs text-[#FF3333]">{errors.min_price[0]}</p>}</div>}
              </div>
              <button type="submit" disabled={loading} className="mt-6 w-full rounded-full bg-black text-white px-7 py-3.5 text-sm font-medium hover:bg-black/80 transition-colors disabled:opacity-50">{loading ? 'Đang gửi…' : editId ? 'Lưu và gửi duyệt lại' : 'Gửi sản phẩm để duyệt'}</button>
            </section>

          </form>
        </div>

        <aside className="lg:sticky lg:top-6 space-y-4">
          <div className="overflow-hidden rounded-3xl border border-black/10 bg-white shadow-sm">
            <div className="border-b border-black/10 p-5"><p className="text-xs font-semibold uppercase tracking-wider text-black/50">Xem trước</p><h2 className="mt-1 text-lg font-semibold">Tin đăng của bạn</h2></div>
            {previewUrl ? <img src={previewUrl} alt="Ảnh xem trước sản phẩm" className="aspect-[4/3] w-full bg-[#f1f0ed] object-cover" /> : <div className="grid aspect-[4/3] place-items-center bg-[#f1f0ed] text-center text-sm text-black/45">Chưa có ảnh</div>}
            <div className="p-5"><p className="min-h-6 text-lg font-semibold leading-snug">{title || 'Tên sản phẩm'}</p><p className="mt-2 text-2xl font-bold">{price ? `${new Intl.NumberFormat('vi-VN').format(Number(price))} ₫` : 'Giá bán'}</p><div className="mt-3 flex flex-wrap gap-2"><span className="rounded-full bg-[#f1f0ed] px-3 py-1 text-xs">{categoryName}</span><span className="rounded-full bg-[#f1f0ed] px-3 py-1 text-xs">{conditionName[condition] ?? 'Tình trạng'}</span>{negotiable && <span className="rounded-full bg-[#f1f0ed] px-3 py-1 text-xs">Có thể thương lượng</span>}</div>{description && <p className="mt-4 line-clamp-4 whitespace-pre-line text-sm leading-6 text-black/60">{description}</p>}</div>
          </div>
        </aside>
      </div>
    </main>
  )
}
