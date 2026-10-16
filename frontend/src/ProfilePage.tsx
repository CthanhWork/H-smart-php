import { useEffect, useState, type FormEvent } from 'react'
import { ArrowLeft, MapPin, UserRound } from 'lucide-react'
import { Alert } from './components/ui/alert'
import { Button } from './components/ui/button'
import { productRequest } from './productApi'

export type Profile = {
  id: number
  email: string
  full_name: string | null
  phone_number: string | null
  shipping_address: string | null
}

type Props = {
  accessToken: string
  navigate: (path: string) => void
  onSaved: (fullName: string | null) => void
}

const fieldClass = 'mt-1.5 w-full rounded-2xl border border-black/15 bg-white px-4 py-3 text-sm outline-none transition focus:border-black'

export default function ProfilePage({ accessToken, navigate, onSaved }: Props) {
  const [profile, setProfile] = useState<Profile | null>(null)
  const [fullName, setFullName] = useState('')
  const [phone, setPhone] = useState('')
  const [address, setAddress] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  useEffect(() => {
    let cancelled = false
    productRequest<Profile>('me/profile', accessToken)
      .then(({ data }) => {
        if (cancelled) return
        setProfile(data)
        setFullName(data.full_name ?? '')
        setPhone(data.phone_number ?? '')
        setAddress(data.shipping_address ?? '')
      })
      .catch((cause: Error) => { if (!cancelled) setError(cause.message) })
      .finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true }
  }, [accessToken])

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSaving(true); setError(''); setNotice('')
    try {
      const result = await productRequest<Profile>('me/profile', accessToken, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          full_name: fullName.trim() || null,
          phone_number: phone.trim() || null,
          shipping_address: address.trim() || null,
        }),
      })
      setProfile(result.data)
      setFullName(result.data.full_name ?? '')
      setPhone(result.data.phone_number ?? '')
      setAddress(result.data.shipping_address ?? '')
      onSaved(result.data.full_name)
      setNotice(result.message)
    } catch (cause) { setError((cause as Error).message) }
    finally { setSaving(false) }
  }

  return <main className="max-w-frame mx-auto min-h-[55vh] px-4 py-10 xl:px-0">
    <button className="mb-6 inline-flex items-center gap-2 text-sm text-black/55 transition hover:text-black" onClick={() => navigate('/')}><ArrowLeft size={16} />Về trang chủ</button>
    <div className="mb-6"><p className="text-xs font-medium uppercase tracking-[0.16em] text-black/50">Tài khoản của tôi</p><h1 className="mt-2 font-satoshi text-2xl font-bold uppercase tracking-tight sm:text-4xl">Hồ sơ và địa chỉ</h1><p className="mt-2 text-sm text-black/55">Lưu thông tin liên hệ và địa chỉ để điền sẵn khi tạo đơn hàng.</p></div>
    {notice && <Alert variant="success" className="mb-4" role="status">{notice}</Alert>}
    {error && <Alert variant="error" className="mb-4">{error}</Alert>}
    {loading ? <p className="py-8 text-sm text-black/55">Đang tải hồ sơ…</p> : profile && <form className="max-w-2xl space-y-6 rounded-[20px] border border-black/10 bg-white p-5 shadow-sm sm:p-8" onSubmit={save}>
      <section>
        <h2 className="flex items-center gap-2 font-bold"><UserRound size={18} />Thông tin cá nhân</h2>
        <label className="mt-4 block text-sm font-medium">Email<input className={`${fieldClass} bg-[#f6f7f8] text-black/55`} value={profile.email} readOnly /></label>
        <label className="mt-4 block text-sm font-medium">Họ và tên<input className={fieldClass} autoComplete="name" maxLength={150} value={fullName} onChange={(event) => setFullName(event.target.value)} /></label>
        <label className="mt-4 block text-sm font-medium">Số điện thoại<input className={fieldClass} type="tel" autoComplete="tel" minLength={8} maxLength={30} value={phone} onChange={(event) => setPhone(event.target.value)} /></label>
      </section>
      <section className="border-t border-black/10 pt-6">
        <h2 className="flex items-center gap-2 font-bold"><MapPin size={18} />Địa chỉ nhận hàng mặc định</h2>
        <label className="mt-4 block text-sm font-medium">Địa chỉ<textarea className={fieldClass} autoComplete="street-address" minLength={5} maxLength={500} rows={3} placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành phố" value={address} onChange={(event) => setAddress(event.target.value)} /></label>
        <p className="mt-2 text-xs text-black/50">Thông tin này chỉ được dùng để điền sẵn khi bạn tạo đơn. Mỗi đơn hàng vẫn lưu riêng địa chỉ tại thời điểm đặt.</p>
      </section>
      <Button type="submit" disabled={saving}>{saving ? 'Đang lưu…' : 'Lưu thay đổi'}</Button>
    </form>}
  </main>
}
