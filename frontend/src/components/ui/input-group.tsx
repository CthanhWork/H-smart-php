import { cn } from "../../lib/utils"
import React, { type InputHTMLAttributes } from "react"

type InputGroupProps = {
  className?: string
  children: React.ReactNode
}

type InputTextProps = InputHTMLAttributes<HTMLInputElement>

const InputGroup = ({ className, children }: InputGroupProps) => {
  return (
    <div
      className={cn(
        "focus-within:shadow-md transition-all relative flex items-center w-full rounded-full overflow-hidden border border-black/20 bg-white focus-within:border-black",
        className ?? ""
      )}
    >
      {children}
    </div>
  )
}

const Input = React.forwardRef<HTMLInputElement, InputTextProps>(
  (props: InputTextProps, ref: any) => {
    const { className, ...rest } = props
    return (
      <input
        className={cn(
          "w-full py-3 px-4 outline-none placeholder:font-normal placeholder:text-sm placeholder:text-black/40 bg-transparent text-sm",
          className ?? ""
        )}
        ref={ref}
        {...rest}
      />
    )
  }
)

const InputGroupText = ({ className, children }: InputGroupProps) => {
  return (
    <div className={cn("flex items-center pl-4 text-black/50", className ?? "")}>
      {children}
    </div>
  )
}

InputGroup.Text = InputGroupText
InputGroup.Input = Input

export default InputGroup
