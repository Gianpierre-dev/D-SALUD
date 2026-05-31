import { Head } from '@inertiajs/react';
import CompraForm from './Partials/CompraForm';

export default function Edit({ compra, proveedores, productos }) {
    return (
        <>
            <Head title={`Editar compra ${compra.numero_formateado}`} />
            <CompraForm compra={compra} proveedores={proveedores} productos={productos} accion="editar" />
        </>
    );
}
