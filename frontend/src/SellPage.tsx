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
  const [condition, setCondition] = useState('good')
  const [images, setImages] = useState<File[]>([])
  const [existingImages, setExistingImages] = useState<ProductImage[]>([])
  const [removedIds, setRemovedIds] = useState<number[]>([])
  const [errors, setErrors] = useState<FieldErrors>({})
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    if (!editId || !accessToken) return
    let cancelled = false
    productRequest<Product>(`products/${editId}`, accessToken)
      .then(({ data }) => {
        if (cancelled) return
        setTitle(data.title); setDescription(data.description); setPrice(String(data.price))
        setCondition(data.condition); setExistingImages(data.images); setRemovedIds([])
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
    setLoading(true)

    const body = new FormData()
    body.append('title', title.trim())
    body.append('description', description.trim())
    body.append('price', price)
    body.append('condition', condition)
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
      setCondition('good')
      setImages([])
      if (fileInput.current) fileInput.current.value = ''
    } catch {
      setError('Không thể kết nối máy chủ. Hãy thử lại sau.')
    } finally {
      setLoading(false)
    }
  }

  if (!accessToken) {
    return <section className="sell-page">
      <div className="card sell-card">
        <span className="eyebrow">{editId ? 'CHỈNH SỬA SẢN PHẨM' : 'ĐĂNG BÁN SẢN PHẨM'}</span>
        <h1>Bạn cần đăng nhập</h1>
        <p>Hãy đăng nhập bằng tài khoản đã xác thực email để đăng sản phẩm.</p>
        <button type="button" className="primary-button" onClick={onBack}>Về trang đăng nhập</button>
      </div>
    </section>
  }

  return <section className="sell-page">
    <div className="card sell-card">
      <button type="button" className="sell-back" onClick={onBack}>← Về tài khoản</button>
      <span className="eyebrow">{editId ? 'CHỈNH SỬA SẢN PHẨM' : 'ĐĂNG BÁN SẢN PHẨM'}</span>
      <h1>{editId ? 'Chỉnh sửa sản phẩm' : 'Đăng sản phẩm'}</h1>
      <p className="card-subtitle">{editId ? 'Sau khi cập nhật, sản phẩm sẽ được gửi duyệt lại.' : 'Điền thông tin món đồ. Sản phẩm sẽ ở trạng thái chờ duyệt sau khi gửi.'}</p>
      {notice && <div className="alert success" role="status">{notice}</div>}
      {error && <div className="alert error" role="alert">{error}</div>}
      <form onSubmit={submit} encType="multipart/form-data">
        <label>Tên sản phẩm
          <input value={title} onChange={(event) => setTitle(event.target.value)} required minLength={3} maxLength={200} />
          {errors.title && <small className="field-error">{errors.title[0]}</small>}
        </label>
        <label>Mô tả
          <textarea value={description} onChange={(event) => setDescription(event.target.value)} required minLength={10} maxLength={5000} rows={5} placeholder="Mô tả đặc điểm và tình trạng thực tế của món đồ" />
          {errors.description && <small className="field-error">{errors.description[0]}</small>}
        </label>
        <div className="sell-row">
          <label>Giá bán (VND)
            <input type="number" min="1" max="999999999999" step="1" value={price} onChange={(event) => setPrice(event.target.value)} required />
            {errors.price && <small className="field-error">{errors.price[0]}</small>}
          </label>
          <label>Tình trạng
            <select value={condition} onChange={(event) => setCondition(event.target.value)} required>
              <option value="new">Mới</option>
              <option value="like_new">Như mới</option>
              <option value="good">Còn tốt</option>
              <option value="fair">Đã qua sử dụng</option>
            </select>
            {errors.condition && <small className="field-error">{errors.condition[0]}</small>}
          </label>
        </div>
        {editId && existingImages.length > 0 && <div><strong>Ảnh hiện tại</strong><div className="edit-images">{existingImages.map((image, index) => <div key={image.id} className={removedIds.includes(image.id) ? 'marked-remove' : ''}><img src={image.url} alt={`Ảnh ${index + 1}`} /><label><input type="checkbox" checked={removedIds.includes(image.id)} onChange={(event) => setRemovedIds(event.target.checked ? [...removedIds, image.id] : removedIds.filter((id) => id !== image.id))} /> Xóa ảnh</label><div className="image-order"><button type="button" disabled={index === 0} onClick={() => setExistingImages((current) => { const next = [...current]; [next[index - 1], next[index]] = [next[index], next[index - 1]]; return next })}>↑</button><button type="button" disabled={index === existingImages.length - 1} onClick={() => setExistingImages((current) => { const next = [...current]; [next[index], next[index + 1]] = [next[index + 1], next[index]]; return next })}>↓</button></div></div>)}</div></div>}
        <label>Ảnh sản phẩm <span>(không bắt buộc, tối đa 5 ảnh)</span>
          <input ref={fileInput} type="file" accept="image/jpeg,image/png,image/webp" multiple onChange={(event) => setImages(Array.from(event.target.files ?? []))} />
          <small className="sell-hint">JPEG, PNG hoặc WebP, mỗi ảnh tối đa 10 MB.</small>
          {images.length > 0 && <small className="sell-hint">Đã chọn {images.length} ảnh.</small>}
          {errors.images && <small className="field-error">{errors.images[0]}</small>}
          {errors.remove_image_ids && <small className="field-error">{errors.remove_image_ids[0]}</small>}
          {errors.image_order && <small className="field-error">{errors.image_order[0]}</small>}
          {Object.entries(errors).filter(([key]) => key.startsWith('images.')).map(([key, messages]) =>
            <small className="field-error" key={key}>{messages[0]}</small>)}
        </label>
        <button className="primary-button" type="submit" disabled={loading || (editId !== undefined && !title)}>{loading ? 'Đang gửi…' : editId ? 'Lưu và gửi duyệt lại' : 'Gửi sản phẩm để duyệt'}</button>
      </form>
    </div>
  </section>
}
