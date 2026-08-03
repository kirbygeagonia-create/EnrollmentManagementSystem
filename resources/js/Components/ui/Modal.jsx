import { Dialog, DialogPanel, Transition } from '@headlessui/react';

export default function Modal({ show = false, onClose, title, size = 'lg', children, closeable = true }) {
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

    return (
        <Transition show={show} leave="duration-200">
            <Dialog as="div" id="modal" className="fixed inset-0 z-50 flex items-center overflow-y-auto px-4 py-6" onClose={close}>
                <Transition
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="modal-overlay" />
                </Transition>

                <Transition
                    enter="ease-out duration-300"
                    enterFrom="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    enterTo="opacity-100 translate-y-0 sm:scale-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                    leaveTo="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                >
                    <DialogPanel className={`modal-panel ${sizeClasses[size]} w-full`}>
                        {(title || closeable) && (
                            <div className="flex items-start justify-between p-6 border-b border-brand-100">
                                <div>
                                    {title && <h3 className="text-lg font-semibold text-brand-900">{title}</h3>}
                                </div>
                                {closeable && (
                                    <button
                                        onClick={close}
                                        className="p-1 rounded-btn text-brand-400 hover:text-brand-600 hover:bg-brand-100 transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500"
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
                    </DialogPanel>
                </Transition>
            </Dialog>
        </Transition>
    );
}