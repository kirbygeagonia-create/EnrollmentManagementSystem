export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center justify-center rounded-btn bg-gradient-to-r from-seait-600 to-seait-700 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-white shadow-sm transition duration-150 ease-in-out hover:from-seait-500 hover:to-seait-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-seait-500 focus:ring-offset-2 active:from-seait-700 active:to-seait-800 active:text-white disabled:opacity-50 disabled:cursor-not-allowed ${
                    disabled && 'opacity-50'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
