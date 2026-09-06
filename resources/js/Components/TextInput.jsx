import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';

export default forwardRef(function TextInput(
    { type = 'text', className = '', isFocused = false, ...props },
    ref,
) {
    const localRef = useRef(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <input
            {...props}
            type={type}
            className={
                'w-full rounded-input border border-brand-300 bg-white px-3.5 py-2.5 text-sm font-medium text-brand-900 shadow-sm placeholder:text-brand-400 transition-all duration-150 focus:border-seait-600 focus:ring-2 focus:ring-seait-500/25 focus:outline-none disabled:bg-brand-50 disabled:text-brand-500 hover:border-brand-400 ' +
                className
            }
            ref={localRef}
        />
    );
});
