import { useState } from 'react';
import { router } from '@inertiajs/react';

/**
 * Maneja el flujo de confirmación y ejecución de un delete via Inertia.
 *
 * @param {string} nombreRuta - Nombre de la ruta Laravel (ej: 'categorias.destroy')
 * @returns {{ pendiente: object|null, procesando: boolean, solicitar: Function, cancelar: Function, confirmar: Function }}
 */
export function useDelete(nombreRuta) {
    const [entidad, setEntidad] = useState(null);
    const [procesando, setProcesando] = useState(false);

    const confirmar = () => {
        if (!entidad) return;
        setProcesando(true);
        router.delete(route(nombreRuta, entidad.id), {
            preserveScroll: true,
            onFinish: () => { setProcesando(false); setEntidad(null); },
        });
    };

    return {
        pendiente: entidad,
        procesando,
        solicitar: (item) => setEntidad(item),
        cancelar: () => setEntidad(null),
        confirmar,
    };
}
