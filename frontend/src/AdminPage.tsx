import { useEffect, useState, type FormEvent } from 'react'
import * as adminApi from './adminApi'

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
  pending_verification: 'Chưa xác thực',
}

const roleLabel: Record<string, string> = {
  member: 'Thành viên',
  admin: 'Quản trị viên',
}

export default function AdminPage({ accessToken, onBack }: Props) {
  const [tab, setTab] = useState<'products' | 'users'>('products')
  const [products, setProducts] = useState<adminApi.Product[]>([])
  const [users, setUsers] = useState<adminApi.User[]>([])
  const [productsPage, setProductsPage] = useState(1)
  const [productsTotalPages, setProductsTotalPages] = useState(1)
  const [usersPage, setUsersPage] = useState(1)
  const [usersTotalPages, setUsersTotalPages] = useState(1)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [hideReason, setHideReason] = useState('')
  const [suspendReason, setSuspendReason] = useState('')
  const [actionProductId, setActionProductId] = useState<number | null>(null)
  const [actionUserId, setActionUserId] = useState<number | null>(null)
  const [filterStatus, setFilterStatus] = useState('')
  const [filterRole, setFilterRole] = useState('')
  const [searchQuery, setSearchQuery] = useState('')

  useEffect(() => {
    if (tab === 'products') loadProducts()
    else loadUsers()
  }, [tab, productsPage, usersPage, filterStatus, filterRole, searchQuery])

  async function loadProducts() {
    setLoading(true)
    setError('')
    try {
      const result = await adminApi.getPendingProducts(accessToken, productsPage)
      setProducts(result.data)
      setProductsTotalPages(result.last_page)
    } catch (cause) {
      setError((cause as Error).message)
    } finally {
      setLoading(false)
    }
  }

  async function loadUsers() {
    setLoading(true)
    setError('')
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
  }

  async function handleApprove(productId: number) {
    setLoading(true)
    setError('')
    setSuccess('')
    try {
      await adminApi.approveProduct(accessToken, productId)
      setSuccess('Đã duyệt sản phẩm thành công')
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
      setSuccess('Đã ẩn sản phẩm thành công')
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
      setSuccess('Đã khóa tài khoản thành công')
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
      setSuccess('Đã mở khóa tài khoản thành công')
      await loadUsers()
    } catch (cause) {
      setError((cause as Error).message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="admin-page">
      <div className="admin-header">
        <button className="back-button" onClick={onBack}>
          ← Về trang chủ
        </button>
        <h1>Quản trị viên</h1>
      </div>

      <div className="admin-tabs">
        <button
          className={tab === 'products' ? 'active' : ''}
          onClick={() => setTab('products')}
        >
          Sản phẩm chờ duyệt
        </button>
        <button
          className={tab === 'users' ? 'active' : ''}
          onClick={() => setTab('users')}
        >
          Quản lý người dùng
        </button>
      </div>

      {error && (
        <div className="alert error" role="alert">
          {error}
        </div>
      )}
      {success && (
        <div className="alert success" role="status">
          {success}
        </div>
      )}

      {tab === 'products' && (
        <div className="admin-section">
          <h2>Sản phẩm chờ duyệt</h2>
          {loading && <p>Đang tải...</p>}
          {!loading && products.length === 0 && (
            <p className="empty-state">Không có sản phẩm nào chờ duyệt</p>
          )}
          <div className="admin-list">
            {products.map((product) => (
              <div key={product.id} className="admin-card">
                <div className="admin-card-header">
                  <h3>{product.title}</h3>
                  <span className="status-badge">{statusLabel[product.status]}</span>
                </div>
                <p className="product-description">{product.description}</p>
                <div className="product-meta">
                  <span>Giá: {product.price.toLocaleString('vi-VN')} đ</span>
                  <span>ID: #{product.id}</span>
                  <span>Người bán: #{product.seller_id}</span>
                </div>
                <div className="admin-actions">
                  <button
                    className="approve-button"
                    onClick={() => handleApprove(product.id)}
                    disabled={loading}
                  >
                    ✓ Duyệt
                  </button>
                  <button
                    className="hide-button"
                    onClick={() => setActionProductId(product.id)}
                    disabled={loading}
                  >
                    ✕ Ẩn
                  </button>
                </div>
              </div>
            ))}
          </div>
          {productsTotalPages > 1 && (
            <div className="pagination">
              <button
                onClick={() => setProductsPage((p) => Math.max(1, p - 1))}
                disabled={productsPage === 1 || loading}
              >
                ← Trước
              </button>
              <span>
                Trang {productsPage} / {productsTotalPages}
              </span>
              <button
                onClick={() => setProductsPage((p) => Math.min(productsTotalPages, p + 1))}
                disabled={productsPage === productsTotalPages || loading}
              >
                Sau →
              </button>
            </div>
          )}
        </div>
      )}

      {tab === 'users' && (
        <div className="admin-section">
          <h2>Quản lý người dùng</h2>
          <div className="filters">
            <input
              type="text"
              placeholder="Tìm theo email hoặc tên..."
              value={searchQuery}
              onChange={(e) => {
                setSearchQuery(e.target.value)
                setUsersPage(1)
              }}
            />
            <select
              value={filterStatus}
              onChange={(e) => {
                setFilterStatus(e.target.value)
                setUsersPage(1)
              }}
            >
              <option value="">Tất cả trạng thái</option>
              <option value="active">Hoạt động</option>
              <option value="suspended">Đã khóa</option>
              <option value="pending_verification">Chưa xác thực</option>
            </select>
            <select
              value={filterRole}
              onChange={(e) => {
                setFilterRole(e.target.value)
                setUsersPage(1)
              }}
            >
              <option value="">Tất cả vai trò</option>
              <option value="member">Thành viên</option>
              <option value="admin">Quản trị viên</option>
            </select>
          </div>
          {loading && <p>Đang tải...</p>}
          {!loading && users.length === 0 && (
            <p className="empty-state">Không tìm thấy người dùng</p>
          )}
          <div className="admin-list">
            {users.map((user) => (
              <div key={user.id} className="admin-card">
                <div className="admin-card-header">
                  <h3>{user.full_name || user.username}</h3>
                  <div className="badges">
                    <span className="role-badge">{roleLabel[user.role]}</span>
                    <span className={`status-badge ${user.status}`}>
                      {userStatusLabel[user.status]}
                    </span>
                  </div>
                </div>
                <p className="user-email">{user.email}</p>
                <div className="user-meta">
                  <span>ID: #{user.id}</span>
                  <span>
                    Tạo: {new Date(user.created_at).toLocaleDateString('vi-VN')}
                  </span>
                </div>
                <div className="admin-actions">
                  {user.status === 'active' && (
                    <button
                      className="suspend-button"
                      onClick={() => setActionUserId(user.id)}
                      disabled={loading}
                    >
                      Khóa tài khoản
                    </button>
                  )}
                  {user.status === 'suspended' && (
                    <button
                      className="restore-button"
                      onClick={() => handleRestore(user.id)}
                      disabled={loading}
                    >
                      Mở khóa
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
          {usersTotalPages > 1 && (
            <div className="pagination">
              <button
                onClick={() => setUsersPage((p) => Math.max(1, p - 1))}
                disabled={usersPage === 1 || loading}
              >
                ← Trước
              </button>
              <span>
                Trang {usersPage} / {usersTotalPages}
              </span>
              <button
                onClick={() => setUsersPage((p) => Math.min(usersTotalPages, p + 1))}
                disabled={usersPage === usersTotalPages || loading}
              >
                Sau →
              </button>
            </div>
          )}
        </div>
      )}

      {actionProductId && (
        <div className="modal-overlay" onClick={() => setActionProductId(null)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <h3>Ẩn sản phẩm</h3>
            <form onSubmit={handleHideSubmit}>
              <label>
                Lý do ẩn sản phẩm
                <textarea
                  value={hideReason}
                  onChange={(e) => setHideReason(e.target.value)}
                  minLength={10}
                  maxLength={500}
                  required
                  placeholder="Nhập lý do (10-500 ký tự)"
                  rows={4}
                />
              </label>
              <div className="modal-actions">
                <button
                  type="button"
                  onClick={() => {
                    setActionProductId(null)
                    setHideReason('')
                  }}
                  disabled={loading}
                >
                  Hủy
                </button>
                <button type="submit" disabled={loading} className="danger-button">
                  {loading ? 'Đang xử lý...' : 'Ẩn sản phẩm'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {actionUserId && (
        <div className="modal-overlay" onClick={() => setActionUserId(null)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <h3>Khóa tài khoản</h3>
            <form onSubmit={handleSuspendSubmit}>
              <label>
                Lý do khóa tài khoản
                <textarea
                  value={suspendReason}
                  onChange={(e) => setSuspendReason(e.target.value)}
                  minLength={10}
                  maxLength={500}
                  required
                  placeholder="Nhập lý do (10-500 ký tự)"
                  rows={4}
                />
              </label>
              <div className="modal-actions">
                <button
                  type="button"
                  onClick={() => {
                    setActionUserId(null)
                    setSuspendReason('')
                  }}
                  disabled={loading}
                >
                  Hủy
                </button>
                <button type="submit" disabled={loading} className="danger-button">
                  {loading ? 'Đang xử lý...' : 'Khóa tài khoản'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
