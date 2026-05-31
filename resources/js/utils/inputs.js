/**
 * Helpers UX para sanitizar inputs numéricos en tiempo real.
 *
 * Filosofía:
 *   - El frontend SOLO mejora la experiencia (el usuario no puede
 *     escribir caracteres inválidos).
 *   - El backend SIEMPRE valida con FormRequest. Estos helpers no
 *     son la capa de seguridad: son la capa de UX.
 *   - Se usa siempre type="text" + inputMode adecuado en vez de
 *     type="number", que en HTML acepta exponentes, signos y
 *     decimales, y desencadena scroll/spinners no deseados.
 */

/**
 * Deja solo dígitos 0-9, opcionalmente truncado al largo máximo.
 * Útil para RUC, DNI, stock entero, cantidades.
 *
 * @param {string} valor
 * @param {number} [maxLen=Infinity]
 * @returns {string}
 */
export function soloDigitos(valor, maxLen = Infinity) {
    return String(valor ?? '').replace(/\D/g, '').slice(0, maxLen);
}

/**
 * Deja solo un decimal positivo con punto como separador.
 * Limita la parte entera y los decimales.
 *
 * @param {string} valor
 * @param {number} [maxEntero=8]   Máx. dígitos antes del punto.
 * @param {number} [maxDecimal=2]  Máx. dígitos después del punto.
 * @returns {string}
 */
export function soloDecimalPositivo(valor, maxEntero = 8, maxDecimal = 2) {
    let v = String(valor ?? '').replace(/[^\d.]/g, '');

    // Un único separador decimal. Concatenar los grupos extra al primer decimal.
    const partes = v.split('.');
    if (partes.length > 2) {
        v = partes.shift() + '.' + partes.join('');
    }

    const [entero = '', decimal] = v.split('.');
    const enteroSano = entero.slice(0, maxEntero);

    if (decimal === undefined) {
        return enteroSano;
    }

    return enteroSano + '.' + decimal.slice(0, maxDecimal);
}

/**
 * Limpia un teléfono permitiendo dígitos, +, -, espacios y paréntesis.
 * Cubre formatos comunes: "+51 1 1234567", "01-1234567", "(01) 1234567".
 *
 * @param {string} valor
 * @param {number} [maxLen=20]
 * @returns {string}
 */
export function telefonoLimpio(valor, maxLen = 20) {
    return String(valor ?? '').replace(/[^\d+\-\s()]/g, '').slice(0, maxLen);
}
