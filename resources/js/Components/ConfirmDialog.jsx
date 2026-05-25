import DangerButton from '@/Components/DangerButton';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';

/**
 * Diálogo de confirmación para acciones destructivas o sensibles.
 * Construido sobre el Modal base de Breeze.
 */
export default function ConfirmDialog({
    show,
    title = '¿Confirmar acción?',
    message,
    confirmLabel = 'Confirmar',
    cancelLabel = 'Cancelar',
    processing = false,
    onConfirm,
    onClose,
}) {
    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="p-6">
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {title}
                </h2>

                {message && (
                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">{message}</p>
                )}

                <div className="mt-6 flex justify-end gap-3">
                    <SecondaryButton onClick={onClose} disabled={processing}>
                        {cancelLabel}
                    </SecondaryButton>
                    <DangerButton onClick={onConfirm} disabled={processing}>
                        {confirmLabel}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    );
}
