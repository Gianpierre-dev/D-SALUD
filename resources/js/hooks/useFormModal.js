import { useState } from 'react';

/**
 * Maneja el estado de apertura/cierre de un modal de formulario
 * y la entidad que se está creando o editando.
 *
 * @returns {{ abierto: boolean, entidad: object|null, abrirCrear: Function, abrirEditar: Function, cerrar: Function }}
 */
export function useFormModal() {
    const [abierto, setAbierto] = useState(false);
    const [entidad, setEntidad] = useState(null);

    return {
        abierto,
        entidad,
        abrirCrear: () => { setEntidad(null); setAbierto(true); },
        abrirEditar: (item) => { setEntidad(item); setAbierto(true); },
        cerrar: () => setAbierto(false),
    };
}
