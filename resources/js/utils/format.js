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
