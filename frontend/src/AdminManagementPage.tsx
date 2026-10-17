import { useCallback, useEffect, useState, type FormEvent } from 'react'
import * as adminApi from './adminApi'
import { ArrowLeft, Check, ChevronLeft, ChevronRight, EyeOff, FolderTree, Package, Shield, Users } from 'lucide-react'
import { Button } from './components/ui/button'
import { Badge } from './components/ui/badge'
import { Alert } from './components/ui/alert'

type Props = {
  accessToken: string
  onBack: () => void
}

const statusLabel: Record<string, string> = {
  draft: 'Bản nháp',
  pending_review: 'Chờ duyệt',
  active: 'Đang bán',
  reserved: 'Đã giữ chỗ',
  sold: 'Đã bán',
  hidden: 'Đã ẩn',
}

const userStatusLabel: Record<string, string> = {
  active: 'Hoạt động',
  suspended: 'Đã khóa',
  pending_verification: 'Chưa xác nhận email',
}

const roleLabel: Record<string, string> = {
  member: 'Thành viên',
  admin: 'Quản trị viên',
}

export default function AdminManagementPage({ accessToken, onBack }: Props) {
  const [tab, setTab] = useState<'products' | 'users' | 'categories'>('products')
  const [products, setProducts] = useState<adminApi.Product[]>([])
  const [users, setUsers] = useState<adminApi.User[]>([])
  const [productsPage, setProductsPage] = useState(1)
  const [productsTotalPages, setProductsTotalPages] = useState(1)
  const [usersPage, setUsersPage] = useState(1)
  const [usersTotalPages, setUsersTotalPages] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [hideReason, setHideReason] = useState('')
  const [suspendReason, setSuspendReason] = useState('')
  const [actionProductId, setActionProductId] = useState<number | null>(null)
  const [actionUserId, setActionUserId] = useState<number | null>(null)
  const [filterStatus, setFilterStatus] = useState('')
  const [filterRole, setFilterRole] = useState('')
  const [searchQuery, setSearchQuery] = useState('')
  const [categories, setCategories] = useState<adminApi.Category[]>([])
  const [categoryName, setCategoryName] = useState('')
  const [editingCategoryId, setEditingCategoryId] = useState<number | null>(null)

  const loadProducts = useCallback(async () => {
    try {
      const result = await adminApi.getPendingProducts(accessToken, productsPage)
      setProducts(result.data)
      setProductsTotalPages(result.last_page)
    } catch (cause) {
      setError((cause as Error).message)
    } finally {
      setLoading(false)
    }
  }, [accessToken, productsPage])

  const loadUsers = useCallback(async () => {
    try {
      const result = await adminApi.getUsers(accessToken, usersPage, {
        status: filterStatus || undefined,
        role: filterRole || undefined,
        q: searchQuery || undefined,
      })
      setUsers(result.data)
      setUsersTotalPages(result.last_page)
    } catch (cause) {
      setError((cause as Error).message)
    } finally {
      setLoading(false)
    }
  }, [accessToken, filterRole, filterStatus, searchQuery, usersPage])

  const loadCategories = useCallback(async () => {
    try {
      setCategories(await adminApi.getCategories(accessToken))
    } catch (cause) {
      setError((cause as Error).message)
    } finally {
      setLoading(false)
    }
  }, [accessToken])

  useEffect(() => {
    let cancelled = false
    void Promise.resolve().then(() => {
      if (cancelled) return
      return tab === 'products' ? loadProducts() : tab === 'users' ? loadUsers() : loadCategories()
    })

    return () => {
      cancelled = true
    }
  }, [tab, loadProducts, loadUsers, loadCategories])

  async function handleCategorySubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setLoading(true); setError(''); setSuccess('')
    try {
      if (editingCategoryId) await adminApi.updateCategory(accessToken, editingCategoryId, categoryName)
      else await adminApi.createCategory(accessToken, categoryName)
      setSuccess(editingCategoryId ? 'Đã cập nhật danh mục.' : 'Đã thêm danh mục.')
      setCategoryName(''); setEditingCategoryId(null)
      await loadCategories()
    } catch (cause) { setError((cause as Error).message) }
    finally { setLoading(false) }
  }

  async function handleCategoryDelete(category: adminApi.Category) {
    setLoading(true); setError(''); setSuccess('')
    try {
      await adminApi.deleteCategory(accessToken, category.id)
      setSuccess('Đã xóa danh mục.'); await loadCategories()
    } catch (cause) { setError((cause as Error).message) }
    finally { setLoading(false) }
  }

  async function handleApprove(productId: number) {
    setLoading(true)
    setError('')
    setSuccess('')
    try {
      await adminApi.approveProduct(accessToken, productId)
      setSuccess('Đã duyệt sản phẩm.')
      await loadProducts()
    } catch (cause) {
      setError((cause as Error).message)
    } finally {
      setLoading(false)
    }
  }

  async function handleHideSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!actionProductId) return
    setLoading(true)
    setError('')
    setSuccess('')
    try {
      await adminApi.hideProduct(accessToken, actionProductId, hideReason)
      setSuccess('Đã ẩn sản phẩm.')
      setActionProductId(null)
      setHideReason('')
      await loadProducts()
    } catch (cause) {
      setError((cause as Error).message)
    } finally {
      setLoading(false)
    }
  }

  async function handleSuspendSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!actionUserId) return
    setLoading(true)
    setError('')
    setSuccess('')
    try {
      await adminApi.suspendUser(accessToken, actionUserId, suspendReason)
      setSuccess('Đã khóa tài khoản.')
      setActionUserId(null)
      setSuspendReason('')
      await loadUsers()
    } catch (cause) {
      setError((cause as Error).message)
    } finally {
      setLoading(false)
    }
  }

  async function handleRestore(userId: number) {
    setLoading(true)
    setError('')
    setSuccess('')
    try {
      await adminApi.restoreUser(accessToken, userId)
      setSuccess('Đã mở khóa tài khoản.')
      await loadUsers()
    } catch (cause) {
      setError((cause as Error).message)
    } finally {
      setLoading(false)
    }
  }

  const fieldClass = 'w-full rounded-2xl border border-black/15 bg-white px-4 py-3 text-sm outline-none focus:border-black'
  const panelClass = 'rounded-[20px] border border-black/10 bg-white p-5 shadow-sm sm:p-7'
  const tabs = [
    { id: 'products' as const, label: 'Sản phẩm chờ duyệt', Icon: Package },
    { id: 'users' as const, label: 'Người dùng', Icon: Users },
    { id: 'categories' as const, label: 'Danh mục', Icon: FolderTree },
  ]

  return (
    <main className="max-w-frame mx-auto min-h-[55vh] px-4 py-10 xl:px-0">
      <button className="mb-5 inline-flex items-center gap-2 text-sm text-black/55 hover:text-black" onClick={onBack}><ArrowLeft size={16} />Về trang chủ</button>
      <div className="mb-7 flex items-end gap-3"><Shield size={28} /><div><p className="text-xs uppercase tracking-[0.16em] text-black/50">Bảng điều khiển</p><h1 className="mt-1 font-satoshi font-bold tracking-tight text-3xl uppercase">Quản trị viên</h1></div></div>

      <div className="mb-6 flex gap-2 overflow-x-auto rounded-full bg-[#F0F0F0] p-1.5" role="tablist" aria-label="Quản lý">
        {tabs.map(({ id, label, Icon }) => <button key={id} role="tab" aria-selected={tab === id} className={`inline-flex shrink-0 items-center gap-2 rounded-full px-4 py-2.5 text-sm transition ${tab === id ? 'bg-black text-white shadow' : 'text-black/60 hover:text-black'}`} onClick={() => { setLoading(true); setError(''); setTab(id) }}><Icon size={16} />{label}</button>)}
      </div>

      {error && <Alert variant="error" className="mb-4">{error}</Alert>}
      {success && <Alert variant="success" className="mb-4" role="status">{success}</Alert>}

      {tab === 'products' && <section className={panelClass}>
        <div className="mb-5 flex items-center justify-between"><div><p className="text-xs uppercase tracking-wider text-black/50">Kiểm duyệt</p><h2 className="mt-1 text-xl font-bold">Sản phẩm chờ duyệt</h2></div><Badge variant="outline">{products.length} mục</Badge></div>
        {loading && <p className="py-5 text-sm text-black/55">Đang tải…</p>}
        {!loading && products.length === 0 && <p className="rounded-2xl bg-[#F0F0F0] p-6 text-center text-sm text-black/55">Hiện chưa có sản phẩm cần duyệt.</p>}
        <div className="space-y-3">{products.map((product) => <article key={product.id} className="rounded-2xl border border-black/10 p-4 sm:p-5">
          <div className="flex flex-wrap items-start justify-between gap-3"><div><h3 className="text-lg font-bold">{product.title}</h3><p className="mt-2 line-clamp-3 whitespace-pre-wrap text-sm text-black/60">{product.description}</p></div><Badge variant="warning">{statusLabel[product.status] ?? product.status}</Badge></div>
          <div className="mt-4 flex flex-wrap gap-x-5 gap-y-1 text-xs text-black/55"><span>Giá: {product.price.toLocaleString('vi-VN')} đ</span><span>ID: #{product.id}</span><span>Người bán: #{product.seller_id}</span></div>
          <div className="mt-4 flex flex-wrap gap-2"><Button size="sm" variant="accent-green" onClick={() => handleApprove(product.id)} disabled={loading}><Check size={15} className="mr-1.5" />Duyệt</Button><Button size="sm" variant="outline" onClick={() => setActionProductId(product.id)} disabled={loading}><EyeOff size={15} className="mr-1.5" />Ẩn</Button></div>
        </article>)}</div>
        {productsTotalPages > 1 && <div className="mt-6 flex items-center justify-center gap-4 text-sm"><Button size="sm" variant="outline" onClick={() => { setLoading(true); setError(''); setProductsPage((p) => Math.max(1, p - 1)) }} disabled={productsPage === 1 || loading}><ChevronLeft size={16} />Trước</Button><span className="text-black/55">Trang {productsPage} / {productsTotalPages}</span><Button size="sm" variant="outline" onClick={() => { setLoading(true); setError(''); setProductsPage((p) => Math.min(productsTotalPages, p + 1)) }} disabled={productsPage === productsTotalPages || loading}>Sau<ChevronRight size={16} /></Button></div>}
      </section>}

      {tab === 'users' && <section className={panelClass}>
        <div className="mb-5"><p className="text-xs uppercase tracking-wider text-black/50">Tài khoản</p><h2 className="mt-1 text-xl font-bold">Quản lý người dùng</h2></div>
        <div className="mb-5 grid gap-3 md:grid-cols-3"><input className={fieldClass} type="text" placeholder="Tìm theo email hoặc họ tên…" value={searchQuery} onChange={(e) => { setSearchQuery(e.target.value); setUsersPage(1); setLoading(true); setError('') }} /><select className={fieldClass} value={filterStatus} onChange={(e) => { setFilterStatus(e.target.value); setUsersPage(1); setLoading(true); setError('') }}><option value="">Tất cả trạng thái</option><option value="active">Hoạt động</option><option value="suspended">Đã khóa</option><option value="pending_verification">Chưa xác nhận email</option></select><select className={fieldClass} value={filterRole} onChange={(e) => { setFilterRole(e.target.value); setUsersPage(1); setLoading(true); setError('') }}><option value="">Tất cả vai trò</option><option value="member">Thành viên</option><option value="admin">Quản trị viên</option></select></div>
        {loading && <p className="py-5 text-sm text-black/55">Đang tải…</p>}
        {!loading && users.length === 0 && <p className="rounded-2xl bg-[#F0F0F0] p-6 text-center text-sm text-black/55">Không tìm thấy người dùng</p>}
        <div className="space-y-3">{users.map((user) => <article key={user.id} className="rounded-2xl border border-black/10 p-4 sm:p-5"><div className="flex flex-wrap items-start justify-between gap-3"><div><h3 className="font-bold">{user.full_name || user.username}</h3><p className="mt-1 text-sm text-black/55">{user.email}</p></div><div className="flex flex-wrap gap-2"><Badge variant="outline">{roleLabel[user.role]}</Badge><Badge variant={user.status === 'active' ? 'success' : user.status === 'suspended' ? 'destructive' : 'warning'}>{userStatusLabel[user.status]}</Badge></div></div><div className="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs text-black/50"><span>ID: #{user.id} · Tạo: {new Date(user.created_at).toLocaleDateString('vi-VN')}</span><div>{user.status === 'active' && <Button size="sm" variant="outline" onClick={() => setActionUserId(user.id)} disabled={loading}>Khóa tài khoản</Button>}{user.status === 'suspended' && <Button size="sm" variant="accent-green" onClick={() => handleRestore(user.id)} disabled={loading}>Mở khóa</Button>}</div></div></article>)}</div>
        {usersTotalPages > 1 && <div className="mt-6 flex items-center justify-center gap-4 text-sm"><Button size="sm" variant="outline" onClick={() => { setLoading(true); setError(''); setUsersPage((p) => Math.max(1, p - 1)) }} disabled={usersPage === 1 || loading}><ChevronLeft size={16} />Trước</Button><span className="text-black/55">Trang {usersPage} / {usersTotalPages}</span><Button size="sm" variant="outline" onClick={() => { setLoading(true); setError(''); setUsersPage((p) => Math.min(usersTotalPages, p + 1)) }} disabled={usersPage === usersTotalPages || loading}>Sau<ChevronRight size={16} /></Button></div>}
      </section>}

      {tab === 'categories' && <section className={panelClass}>
        <div className="mb-5"><p className="text-xs uppercase tracking-wider text-black/50">Danh mục</p><h2 className="mt-1 text-xl font-bold">Quản lý danh mục sản phẩm</h2></div>
        <form className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end" onSubmit={handleCategorySubmit}><label className="flex-1 text-sm font-medium">{editingCategoryId ? 'Sửa tên danh mục' : 'Tên danh mục'}<input className={`${fieldClass} mt-1.5`} value={categoryName} onChange={(event) => setCategoryName(event.target.value)} minLength={2} maxLength={150} required /></label><Button type="submit" disabled={loading}>{editingCategoryId ? 'Lưu thay đổi' : 'Thêm danh mục'}</Button>{editingCategoryId && <Button type="button" variant="outline" onClick={() => { setEditingCategoryId(null); setCategoryName('') }}>Hủy sửa</Button>}</form>
        {loading && <p className="py-4 text-sm text-black/55">Đang tải…</p>}{!loading && categories.length === 0 && <p className="rounded-2xl bg-[#F0F0F0] p-6 text-center text-sm text-black/55">Chưa có danh mục nào.</p>}
        <div className="space-y-3">{categories.map((category) => <article className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-black/10 p-4" key={category.id}><div><h3 className="font-bold">{category.name}</h3><p className="mt-1 text-xs text-black/50">{category.slug} · {category.products_count} sản phẩm</p></div><div className="flex gap-2"><Button size="sm" variant="outline" onClick={() => { setEditingCategoryId(category.id); setCategoryName(category.name) }}>Sửa</Button><Button size="sm" variant="outline" disabled={loading || category.products_count > 0} title={category.products_count ? 'Danh mục đang được dùng' : undefined} onClick={() => handleCategoryDelete(category)}>Xóa</Button></div></article>)}</div>
      </section>}

      {(actionProductId || actionUserId) && <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm" onClick={() => { setActionProductId(null); setActionUserId(null) }}><section className="w-full max-w-lg rounded-[20px] bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" onClick={(event) => event.stopPropagation()}><p className="text-xs uppercase tracking-wider text-black/50">Thao tác quản trị</p><h2 className="mt-2 text-2xl font-bold">{actionProductId ? 'Ẩn sản phẩm' : 'Khóa tài khoản'}</h2><form className="mt-5" onSubmit={actionProductId ? handleHideSubmit : handleSuspendSubmit}><label className="text-sm font-medium">Lý do {actionProductId ? 'ẩn sản phẩm' : 'khóa tài khoản'}<textarea className={`${fieldClass} mt-1.5`} value={actionProductId ? hideReason : suspendReason} onChange={(event) => actionProductId ? setHideReason(event.target.value) : setSuspendReason(event.target.value)} minLength={10} maxLength={500} required placeholder="Nhập lý do (10-500 ký tự)" rows={4} /></label><div className="mt-5 flex justify-end gap-2"><Button type="button" variant="outline" onClick={() => { setActionProductId(null); setActionUserId(null); setHideReason(''); setSuspendReason('') }} disabled={loading}>Hủy</Button><Button type="submit" variant="destructive" disabled={loading}>{loading ? 'Đang xử lý…' : actionProductId ? 'Ẩn sản phẩm' : 'Khóa tài khoản'}</Button></div></form></section></div>}
    </main>
  )
}
