import { useEffect, useRef, useCallback } from 'react';

/**
 * Custom React hook that enables enterprise-grade keyboard navigation across forms.
 * 
 * Behavior:
 * - Enter: Moves focus forward to the next enabled, visible form input (instead of premature submit).
 * - Shift + Enter: Moves focus backward to the previous input.
 * - Ctrl + Enter (or Cmd + Enter): Submits the form.
 * - Textarea: Standard Enter inserts a newline; Ctrl+Enter submits.
 * - Buttons: Pressing Enter on a submit button activates it normally.
 * - Auto-focus: Automatically places cursor in the first interactive field on mount.
 */
export function useFormKeyboardNav({ onSubmit, autoFocusFirst = true } = {}) {
    const formRef = useRef(null);

    const getFocusableElements = useCallback(() => {
        if (!formRef.current) return [];
        const selector = [
            'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]):not([disabled])',
            'select:not([disabled])',
            'textarea:not([disabled])',
            'button[type="submit"]:not([disabled])',
        ].join(', ');

        const all = Array.from(formRef.current.querySelectorAll(selector));
        // Filter out hidden elements (e.g., inside collapsed sections or display:none)
        return all.filter((el) => {
            return (
                el.offsetParent !== null &&
                !el.getAttribute('aria-hidden') &&
                window.getComputedStyle(el).visibility !== 'hidden'
            );
        });
    }, []);

    const handleKeyDown = useCallback(
        (e) => {
            const active = document.activeElement;
            if (!active || !formRef.current?.contains(active)) return;

            const isTextarea = active.tagName.toLowerCase() === 'textarea';
            const isSubmitButton =
                active.tagName.toLowerCase() === 'button' &&
                (active.type === 'submit' || active.getAttribute('data-submit-btn') === 'true');

            // 1. Handle Ctrl+Enter or Cmd+Enter -> Submit immediately
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                if (onSubmit) {
                    onSubmit(e);
                } else {
                    const submitBtn = formRef.current.querySelector('button[type="submit"]:not([disabled])');
                    submitBtn?.click();
                }
                return;
            }

            // 2. In textarea, regular Enter creates a new line
            if (isTextarea && !e.ctrlKey && !e.metaKey) {
                return;
            }

            // 3. On submit button, regular Enter triggers the button
            if (isSubmitButton) {
                return;
            }

            // 4. Intercept Enter on inputs and selects for rapid field-to-field traversal
            if (e.key === 'Enter') {
                e.preventDefault();
                const focusables = getFocusableElements();
                const currentIndex = focusables.indexOf(active);

                if (currentIndex === -1) return;

                if (e.shiftKey) {
                    // Shift + Enter: Move backward
                    const prevIndex = currentIndex - 1;
                    if (prevIndex >= 0) {
                        focusables[prevIndex].focus();
                        if (typeof focusables[prevIndex].select === 'function') {
                            focusables[prevIndex].select();
                        }
                    }
                } else {
                    // Enter: Move forward
                    const nextIndex = currentIndex + 1;
                    if (nextIndex < focusables.length) {
                        focusables[nextIndex].focus();
                        if (typeof focusables[nextIndex].select === 'function') {
                            focusables[nextIndex].select();
                        }
                    } else {
                        // Reached the end of inputs -> focus the submit button
                        const submitBtn = formRef.current.querySelector('button[type="submit"]:not([disabled])');
                        submitBtn?.focus();
                    }
                }
            }
        },
        [getFocusableElements, onSubmit]
    );

    // Auto-focus the first input on mount
    useEffect(() => {
        if (!autoFocusFirst || !formRef.current) return;
        const timer = setTimeout(() => {
            const focusables = getFocusableElements();
            if (focusables.length > 0) {
                focusables[0].focus();
            }
        }, 80);
        return () => clearTimeout(timer);
    }, [autoFocusFirst, getFocusableElements]);

    return {
        formRef,
        handleKeyDown,
        formProps: {
            ref: formRef,
            onKeyDown: handleKeyDown,
        },
    };
}

export default useFormKeyboardNav;
