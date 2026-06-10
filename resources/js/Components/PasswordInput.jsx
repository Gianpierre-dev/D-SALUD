import { forwardRef, useState } from 'react';
import { IconEye, IconEyeOff } from '@tabler/icons-react';
import TextInput from '@/Components/TextInput';

/**
 * Campo de contraseña con alternancia de visibilidad (ojo) y aviso de
 * Bloq Mayús. Envuelve a TextInput para reutilizar sus estilos y el manejo
 * de foco/ref. Acepta las mismas props que TextInput (id, value, onChange,
 * isFocused, autoComplete, placeholder, etc.).
 *
 * @param {string} className   Clases de layout aplicadas al contenedor
 *                             (p. ej. "mt-1 block w-full").
 * @param {boolean} mostrarBloqMayus  Mostrar el aviso de Bloq Mayús.
 */
export default forwardRef(function PasswordInput(
    { className = '', mostrarBloqMayus = true, onKeyUp, ...props },
    ref,
) {
    const [visible, setVisible] = useState(false);
    const [bloqMayus, setBloqMayus] = useState(false);

    const manejarTecla = (e) => {
        if (mostrarBloqMayus && typeof e.getModifierState === 'function') {
            setBloqMayus(e.getModifierState('CapsLock'));
        }
        onKeyUp?.(e);
    };

    return (
        <div className={className}>
            <div className="relative">
                <TextInput
                    {...props}
                    ref={ref}
                    type={visible ? 'text' : 'password'}
                    onKeyUp={manejarTecla}
                    className="block w-full pr-10"
                />

                <button
                    type="button"
                    onClick={() => setVisible((v) => !v)}
                    aria-label={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                    title={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                    tabIndex={-1}
                    className="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition hover:text-gray-600 focus:text-brand-600 focus:outline-none dark:text-gray-500 dark:hover:text-gray-300"
                >
                    {visible ? (
                        <IconEyeOff size={18} stroke={1.5} aria-hidden="true" />
                    ) : (
                        <IconEye size={18} stroke={1.5} aria-hidden="true" />
                    )}
                </button>
            </div>

            {mostrarBloqMayus && bloqMayus && (
                <p
                    role="status"
                    className="mt-1 text-xs font-medium text-amber-600 dark:text-amber-500"
                >
                    Bloq Mayús activado
                </p>
            )}
        </div>
    );
});
