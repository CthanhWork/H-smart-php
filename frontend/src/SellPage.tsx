import { useEffect, useRef, useState, type FormEvent } from 'react'
import { productRequest, type Product, type ProductImage } from './productApi'

type FieldErrors = Record<string, string[]>
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

  if (!accessToken) {
    return (
      <main className="max-w-frame mx-auto px-4 xl:px-0 py-16">
        <div className="max-w-md mx-auto text-center">
          <span className="text-xs uppercase tracking-wider text-black/60">{editId ? 'Chỉnh sửa sản phẩm' : 'Đăng bán sản phẩm'}</span>
          <h1 className="font-satoshi font-bold tracking-tight text-3xl md:text-4xl uppercase mt-3">Bạn cần đăng nhập</h1>
          <p className="text-sm text-black/60 mt-3">Đăng nhập bằng email đã xác nhận để đăng bán sản phẩm.</p>
          <button
            type="button"
            onClick={onBack}
            className="mt-6 inline-flex items-center justify-center rounded-full bg-black text-white px-7 py-3 text-sm font-medium hover:bg-black/80 transition-colors"
          >
            Về trang đăng nhập
          </button>
        </div>
      </main>
    )
  }

  return (
    <main className="max-w-frame mx-auto px-4 xl:px-0 py-10">
      <button onClick={onBack} className="text-sm text-black/60 hover:text-black mb-6">
        ← Quay lại
      </button>

      <div className="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-10">
        <div>
          <span className="text-xs uppercase tracking-wider text-black/60">{editId ? 'Chỉnh sửa sản phẩm' : 'Đăng bán sản phẩm'}</span>
          <h1 className="font-satoshi font-bold tracking-tight text-2xl md:text-4xl uppercase mt-2">
            {editId ? 'Chỉnh sửa sản phẩm' : 'Đăng sản phẩm'}
          </h1>
          <p className="text-sm text-black/60 mt-2">
            {editId ? 'Sau khi lưu, sản phẩm sẽ được gửi lại để duyệt.' : 'Điền thông tin món đồ. Sau khi gửi, sản phẩm sẽ chờ quản trị viên duyệt.'}
          </p>

          {notice && (
            <div className="mt-4 rounded-lg border border-[#2f7049]/30 bg-[#2f7049]/10 text-[#2f7049] px-4 py-3 text-sm">{notice}</div>
          )}
          {error && (
            <div className="mt-4 rounded-lg border border-[#FF3333]/30 bg-[#FF3333]/10 text-[#FF3333] px-4 py-3 text-sm">{error}</div>
          )}

          <form onSubmit={submit} encType="multipart/form-data" className="mt-6 flex flex-col gap-5">
            <div>
              <label className="block text-sm font-medium mb-1.5">Tên sản phẩm</label>
              <input
                value={title}
                onChange={(event) => setTitle(event.target.value)}
                required minLength={3} maxLength={200}
                className="w-full rounded-full border border-black/20 px-5 py-3 text-sm outline-none focus:border-black"
              />
              {errors.title && <p className="mt-1 text-xs text-[#FF3333]">{errors.title[0]}</p>}
            </div>

            <div>
              <label className="block text-sm font-medium mb-1.5">Mô tả</label>
              <textarea
                value={description}
                onChange={(event) => setDescription(event.target.value)}
                required minLength={10} maxLength={5000} rows={5}
                placeholder="Mô tả món đồ và tình trạng hiện tại."
                className="w-full rounded-2xl border border-black/20 px-5 py-3 text-sm outline-none focus:border-black resize-y"
              />
              {errors.description && <p className="mt-1 text-xs text-[#FF3333]">{errors.description[0]}</p>}
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div>
                <label className="block text-sm font-medium mb-1.5">Giá bán (VND)</label>
                <input
                  type="number" min="1" max="999999999999" step="1"
                  value={price} onChange={(event) => setPrice(event.target.value)} required
                  className="w-full rounded-full border border-black/20 px-5 py-3 text-sm outline-none focus:border-black"
                />
                {errors.price && <p className="mt-1 text-xs text-[#FF3333]">{errors.price[0]}</p>}
              </div>
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

            <div>
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

            <div className="rounded-2xl border border-black/10 bg-muted/50 p-5">
              <label className="flex items-center gap-2 text-sm font-medium">
                <input
                  type="checkbox"
                  checked={negotiable}
                  onChange={(event) => setNegotiable(event.target.checked)}
                  className="accent-black w-4 h-4"
                />
                Cho phép người mua trả giá
              </label>
              {negotiable && (
                <div className="mt-4">
                  <label className="block text-sm font-medium mb-1.5">Mức giá thấp nhất bạn có thể nhận (VND)</label>
                  <input
                    type="number" min="1" max={price || undefined} step="1"
                    value={minPrice} onChange={(event) => setMinPrice(event.target.value)} required
                    className="w-full rounded-full border border-black/20 px-5 py-3 text-sm outline-none focus:border-black bg-white"
                  />
                  {price && (
                    <p className="text-xs text-black/60 mt-1">
                      Không vượt quá giá bán ({new Intl.NumberFormat('vi-VN').format(Number(price))} VND).
                    </p>
                  )}
                  {errors.min_price && <p className="mt-1 text-xs text-[#FF3333]">{errors.min_price[0]}</p>}
                </div>
              )}
            </div>

            {editId && existingImages.length > 0 && (
              <div>
                <strong className="text-sm uppercase tracking-wider">Ảnh hiện tại</strong>
                <div className="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-3">
                  {existingImages.map((image, index) => (
                    <div
                      key={image.id}
                      className={`rounded-2xl overflow-hidden border bg-white p-2 ${removedIds.includes(image.id) ? 'opacity-40' : 'border-black/10'}`}
                    >
                      <img src={image.url} alt={`Ảnh ${index + 1}`} className="w-full aspect-square object-cover rounded-xl" />
                      <label className="flex items-center gap-2 text-xs mt-2">
                        <input
                          type="checkbox"
                          checked={removedIds.includes(image.id)}
                          onChange={(event) => setRemovedIds(event.target.checked ? [...removedIds, image.id] : removedIds.filter((id) => id !== image.id))}
                          className="accent-black"
                        />
                        Xóa
                      </label>
                      <div className="flex gap-1 mt-2">
                        <button
                          type="button"
                          disabled={index === 0}
                          onClick={() => setExistingImages((current) => { const next = [...current]; [next[index - 1], next[index]] = [next[index], next[index - 1]]; return next })}
                          className="flex-1 text-xs py-1 rounded-md border border-black/20 disabled:opacity-40"
                        >
                          ↑
                        </button>
                        <button
                          type="button"
                          disabled={index === existingImages.length - 1}
                          onClick={() => setExistingImages((current) => { const next = [...current]; [next[index], next[index + 1]] = [next[index + 1], next[index]]; return next })}
                          className="flex-1 text-xs py-1 rounded-md border border-black/20 disabled:opacity-40"
                        >
                          ↓
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            <div>
              <label className="block text-sm font-medium mb-1.5">Ảnh sản phẩm <span className="text-black/60 font-normal">(bắt buộc, tối đa 5 ảnh)</span></label>
              <input
                ref={fileInput} type="file" accept="image/jpeg,image/png,image/webp" multiple
                onChange={(event) => {
                  const selected = Array.from(event.target.files ?? [])
                  const retainedCount = existingImages.filter((image) => !removedIds.includes(image.id)).length
                  if (retainedCount + images.length + selected.length > 5) {
                    setErrors((current) => ({ ...current, images: ['Tối đa 5 ảnh cho một sản phẩm. Hãy bỏ bớt ảnh trước khi thêm.'] }))
                    event.target.value = ''
                    return
                  }
                  setErrors((current) => ({ ...current, images: [] }))
                  setImages((current) => [...current, ...selected])
                  event.target.value = ''
                }}
                className="w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-black file:text-white file:cursor-pointer"
              />
              <p className="text-xs text-black/60 mt-1">JPEG, PNG hoặc WebP, mỗi ảnh tối đa 10 MB.</p>
              {imagePreviews.length > 0 && <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">{imagePreviews.map((src, index) => <div key={`${src}-${index}`} className="overflow-hidden rounded-xl border border-black/10 bg-white"><img src={src} alt={`Ảnh chi tiết đã chọn ${index + 1}`} className="aspect-square w-full object-cover" /><button type="button" onClick={() => setImages((current) => current.filter((_, itemIndex) => itemIndex !== index))} className="w-full px-3 py-2 text-left text-xs text-red-700">Bỏ ảnh {index + 1}</button></div>)}</div>}
              {errors.images && <p className="mt-1 text-xs text-[#FF3333]">{errors.images[0]}</p>}
            </div>

            <button
              type="submit"
              disabled={loading}
              className="mt-2 self-start rounded-full bg-black text-white px-7 py-3 text-sm font-medium hover:bg-black/80 transition-colors disabled:opacity-50"
            >
              {loading ? 'Đang gửi…' : editId ? 'Lưu và gửi duyệt lại' : 'Gửi sản phẩm để duyệt'}
            </button>
          </form>
        </div>

        <aside className="hidden lg:block">
          <div className="rounded-2xl bg-tile p-6">
            <h3 className="font-satoshi font-bold tracking-tight text-lg uppercase">Để tin đăng dễ bán</h3>
            <ul className="mt-4 space-y-3 text-sm text-black/70">
              <li>Chụp ảnh rõ nét ở nơi đủ sáng.</li>
              <li>Mô tả đúng tình trạng và các lỗi (nếu có).</li>
              <li>Tham khảo giá những món tương tự.</li>
              <li>Bật tính năng trả giá nếu bạn sẵn sàng thương lượng.</li>
            </ul>
          </div>
        </aside>
      </div>
    </main>
  )
}
