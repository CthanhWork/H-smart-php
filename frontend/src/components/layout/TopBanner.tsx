import { useState } from "react"
import { X } from "lucide-react"

const TopBanner = ({ onSignUp }: { onSignUp: () => void }) => {
  const [visible, setVisible] = useState(true)
  if (!visible) return null
  return (
    <div className="bg-black text-white text-center py-2 px-2 sm:px-4 xl:px-0">
      <div className="relative max-w-frame mx-auto">
        <p className="text-xs sm:text-sm">
          Tìm món đồ cần mua hoặc đăng bán món đồ bạn không còn dùng.{" "}
          <button type="button" onClick={onSignUp} className="underline font-medium">
            Tạo tài khoản
          </button>
        </p>
        <button
          type="button"
          aria-label="Đóng thông báo"
          onClick={() => setVisible(false)}
          className="absolute right-0 top-1/2 hidden h-fit w-fit -translate-y-1/2 p-1 text-white/60 hover:text-white sm:flex"
        >
          <X size={13} />
        </button>
      </div>
    </div>
  )
}

export default TopBanner
