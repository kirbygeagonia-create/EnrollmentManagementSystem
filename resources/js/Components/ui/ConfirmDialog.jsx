import Modal from './Modal';

export default function ConfirmDialog({ show = false, onClose, onConfirm, title = 'Confirm Action', message = 'Are you sure you want to proceed?', confirmText = 'Confirm', cancelText = 'Cancel', variant = 'danger', loading = false }) {
    const variantClasses = {
        danger: 'btn-danger',
        primary: 'btn-primary',
        warning: 'btn-accent',
    };

    return (
        <Modal
            show={show}
            onClose={onClose}
            title={title}
            size="sm"
            closeable={!loading}
        >
            <p className="text-brand-600 mb-6">{message}</p>
            <div className="flex justify-end gap-3">
                <button
                    onClick={onClose}
                    disabled={loading}
                    className="btn btn-secondary"
                >
                    {cancelText}
                </button>
                <button
                    onClick={onConfirm}
                    disabled={loading}
                    className={variantClasses[variant] || variantClasses.danger}
                >
                    {loading ? (
                        <span className="flex items-center gap-2">
                            <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                            </svg>
                            Processing...
                        </span>
                    ) : (
                        confirmText
                    )}
                </button>
            </div>
        </Modal>
    );
}