import { Head } from '@inertiajs/react';
import CompraForm from './Partials/CompraForm';

export default function Create({ proveedores, productos }) {
    return (
        <>
            <Head title="Nueva orden de compra" />
            <CompraForm proveedores={proveedores} productos={productos} accion="crear" />
        </>
    );
}
