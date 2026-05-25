import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Muestra los mensajes flash (success / error) compartidos por Inertia
 * como notificaciones temporales (toasts) que se autodescartan.
 */
export default function FlashMessages() {
    const { flash } = usePage().props;
    const [visible, setVisible] = useState(null);

    useEffect(() => {
        if (flash?.success) {
            setVisible({ type: 'success', text: flash.success });
        } else if (flash?.error) {
            setVisible({ type: 'error', text: flash.error });
        }
    }, [flash]);

    useEffect(() => {
        if (!visible) {
            return;
        }
        const timer = setTimeout(() => setVisible(null), 4000);

        return () => clearTimeout(timer);
    }, [visible]);

    if (!visible) {
        return null;
    }

    const styles =
        visible.type === 'success'
            ? 'border-green-500 bg-green-50 text-green-800 dark:bg-green-900/40 dark:text-green-200'
            : 'border-red-500 bg-red-50 text-red-800 dark:bg-red-900/40 dark:text-red-200';

    return (
        <div
            role="status"
            aria-live="polite"
            className={`fixed right-4 top-4 z-50 max-w-sm rounded-md border-l-4 px-4 py-3 shadow-lg ${styles}`}
        >
            <div className="flex items-start justify-between gap-3">
                <p className="text-sm font-medium">{visible.text}</p>
                <button
                    type="button"
                    onClick={() => setVisible(null)}
                    className="text-lg leading-none opacity-60 hover:opacity-100"
                    aria-label="Cerrar"
                >
                    ×
                </button>
            </div>
        </div>
    );
}
