export default function DangerButton({
    className = '',
    disabled,
    children,
    ...props
}) {
    return (
        <button
            {...props}
            className={
                `inline-flex items-center justify-center rounded-btn border border-transparent bg-danger-600 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-white shadow-sm transition duration-150 ease-in-out hover:bg-danger-500 hover:text-white focus:outline-none focus:ring-2 focus:ring-danger-500 focus:ring-offset-2 active:bg-danger-700 active:text-white disabled:opacity-50 disabled:cursor-not-allowed ${
                    disabled && 'opacity-50'
                } ` + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
