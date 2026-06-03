/**
 * Formatea un valor numérico como moneda peruana (S/ PEN).
 *
 * @param {number|string} valor
 * @returns {string}
 */
export function formatearMoneda(valor) {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
        minimumFractionDigits: 2,
    }).format(Number(valor) || 0);
}

/**
 * Convierte un valor a Date manejando correctamente las fechas date-only.
 *
 * Un string "YYYY-MM-DD" lo interpreta el motor JS como medianoche UTC, lo
 * que en Perú (UTC-5) corre la fecha un día hacia atrás. Para esos casos
 * construimos la fecha con componentes LOCALES y evitamos el desfase.
 * Los datetime con hora (ISO completo) se parsean normal: son un instante.
 *
 * @param {string|Date} fecha
 * @returns {Date|null}
 */
function aFechaLocal(fecha) {
    if (fecha instanceof Date) return fecha;
    if (typeof fecha === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(fecha)) {
        const [y, m, d] = fecha.split('-').map(Number);
        return new Date(y, m - 1, d);
    }
    const d = new Date(fecha);
    return Number.isNaN(d.getTime()) ? null : d;
}

/**
 * Formatea una fecha en formato peruano dd/mm/aaaa.
 * Devuelve un guion largo si la fecha es nula o inválida.
 *
 * @param {string|Date|null} fecha
 * @returns {string}
 */
export function formatearFecha(fecha) {
    if (!fecha) return '—';
    const d = aFechaLocal(fecha);
    if (!d || Number.isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('es-PE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

/**
 * Formatea fecha y hora en formato peruano dd/mm/aaaa hh:mm.
 * Devuelve un guion largo si la fecha es nula o inválida.
 *
 * @param {string|Date|null} fecha
 * @returns {string}
 */
export function formatearFechaHora(fecha) {
    if (!fecha) return '—';
    const d = aFechaLocal(fecha);
    if (!d || Number.isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('es-PE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
