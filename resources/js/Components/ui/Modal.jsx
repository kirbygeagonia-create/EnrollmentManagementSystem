import { Dialog, DialogPanel, Transition, TransitionChild } from '@headlessui/react';

/**
 * Modal
 *
 * Existing API (preserved): show, onClose, title, size (sm/md/lg/xl/full),
 * children, closeable.
 *
 * New optional props:
 *   - icon:        ReactNode  — rendered in a rounded seait-50 chip (seait-600 icon).
 *   - subtitle:    ReactNode  — muted helper text under the title.
 *   - footer:      ReactNode  — structured footer slot (border-top, right-aligned
 *                               by default via caller). Omit to keep the legacy
 *                               "footer inside children" pattern working.
 *
 * Design notes:
 *   - A thin gradient accent bar (seait-500 → seait-550) sits at the very top
 *     of the panel as a branded hairline. This is the signature element.
 *   - When `icon` is provided, it appears in a rounded seait-50 chip to the
 *     left of the title, complementing the gradient bar.
 *   - Backdrop keeps backdrop-blur (via .modal-overlay) and click-outside +
 *     Escape remain functional through headlessui's Dialog onClose.
 */
export default function Modal({
    show = false,
    onClose,
    title,
    subtitle,
    icon,
    size = 'lg',
    children,
    footer,
    closeable = true,
}) {
    const sizeClasses = {
        sm: 'max-w-md',
        md: 'max-w-lg',
        lg: 'max-w-2xl',
        xl: 'max-w-4xl',
        full: 'max-w-[90vw]',
    };

    const close = () => {
        if (closeable) {
            onClose();
        }
    };

    const hasHeader = title || subtitle || icon || closeable;

    return (
        <Transition show={show} leave="duration-200">
            <Dialog as="div" id="modal" className="fixed inset-0 z-50 flex items-center overflow-y-auto px-4 py-6" onClose={close}>
                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="modal-overlay" />
                </TransitionChild>

                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    enterTo="opacity-100 translate-y-0 sm:scale-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                    leaveTo="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                >
                    <DialogPanel className={`modal-panel ${sizeClasses[size]} w-full overflow-hidden`}>
                        {/* Gradient accent hairline — branded top edge */}
                        <div
                            aria-hidden="true"
                            className="h-1 w-full bg-gradient-to-r from-seait-500 to-seait-550"
                        />

                        {hasHeader && (
                            <div className="flex items-start justify-between gap-4 p-6 border-b border-brand-100">
                                <div className="flex items-start gap-3 min-w-0">
                                    {icon && (
                                        <span className="flex-shrink-0 inline-flex items-center justify-center rounded-xl bg-seait-50 text-seait-600 h-10 w-10">
                                            {icon}
                                        </span>
                                    )}
                                    <div className="min-w-0">
                                        {title && (
                                            <h3 className="text-lg font-semibold text-brand-900 leading-tight">
                                                {title}
                                            </h3>
                                        )}
                                        {subtitle && (
                                            <p className="mt-1 text-sm text-brand-500 leading-snug">
                                                {subtitle}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                {closeable && (
                                    <button
                                        onClick={close}
                                        className="flex-shrink-0 p-1 rounded-btn text-brand-400 hover:text-brand-600 hover:bg-brand-100 transition-colors focus:outline-none focus:ring-2 focus:ring-seait-500"
                                        aria-label="Close modal"
                                    >
                                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                )}
                            </div>
                        )}

                        <div className="p-6">
                            {children}
                        </div>

                        {footer && (
                            <div className="px-6 py-4 border-t border-brand-100 bg-brand-50/40">
                                {footer}
                            </div>
                        )}
                    </DialogPanel>
                </TransitionChild>
            </Dialog>
        </Transition>
    );
}
