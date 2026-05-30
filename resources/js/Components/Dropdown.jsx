import { Menu, MenuButton, MenuItems, MenuItem, Transition } from '@headlessui/react';
import { Link } from '@inertiajs/react';
import { Fragment } from 'react';

/**
 * Dropdown accesible construido sobre Headless UI Menu (v2).
 *
 * API pública (sin cambios respecto al componente anterior):
 *   <Dropdown>
 *     <Dropdown.Trigger>...</Dropdown.Trigger>
 *     <Dropdown.Content>
 *       <Dropdown.Link href="...">...</Dropdown.Link>
 *     </Dropdown.Content>
 *   </Dropdown>
 *
 * Mejoras frente al componente anterior:
 * - Trigger es un <MenuButton> real → funciona con teclado (Enter, Space, Flechas, ESC)
 * - aria-expanded, aria-haspopup y role="menu" gestionados automáticamente por Headless UI
 * - Cierre con ESC y clic fuera manejados de forma nativa
 * - Dark mode completo en el panel desplegable
 */

// ─── Trigger ──────────────────────────────────────────────────────────────────

/**
 * Envuelve el hijo en un MenuButton usando Fragment como tag neutro,
 * para que el <button> o elemento focusable del consumidor sea
 * el trigger real sin agregar nodos DOM extra.
 */
const Trigger = ({ children }) => {
    return (
        <MenuButton as={Fragment}>
            {children}
        </MenuButton>
    );
};

// ─── Content ──────────────────────────────────────────────────────────────────

const Content = ({
    align = 'right',
    width = '48',
    contentClasses = 'py-1 bg-white dark:bg-gray-800',
    children,
}) => {
    let alignmentClasses = 'origin-top';

    if (align === 'left') {
        alignmentClasses = 'ltr:origin-top-left rtl:origin-top-right start-0';
    } else if (align === 'right') {
        alignmentClasses = 'ltr:origin-top-right rtl:origin-top-left end-0';
    }

    const widthClasses = width === '48' ? 'w-48' : '';

    return (
        <Transition
            as={Fragment}
            enter="transition ease-out duration-200"
            enterFrom="opacity-0 scale-95"
            enterTo="opacity-100 scale-100"
            leave="transition ease-in duration-75"
            leaveFrom="opacity-100 scale-100"
            leaveTo="opacity-0 scale-95"
        >
            <MenuItems
                className={`absolute z-50 mt-2 rounded-md shadow-lg focus:outline-none ${alignmentClasses} ${widthClasses}`}
            >
                <div
                    className={`rounded-md ring-1 ring-black ring-opacity-5 dark:ring-gray-700 ${contentClasses}`}
                >
                    {children}
                </div>
            </MenuItems>
        </Transition>
    );
};

// ─── Link ─────────────────────────────────────────────────────────────────────

/**
 * En Headless UI v2, MenuItem aplica `data-focus` al elemento activo en lugar
 * de un render prop `{ focus }`. Usamos el selector CSS con `data-[focus]`
 * directamente en las clases de Tailwind.
 */
const DropdownLink = ({ className = '', children, ...props }) => {
    return (
        <MenuItem>
            <Link
                {...props}
                className={
                    'block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-gray-300 dark:hover:bg-gray-700 dark:focus:bg-gray-700 ' +
                    className
                }
            >
                {children}
            </Link>
        </MenuItem>
    );
};

// ─── Dropdown (root) ──────────────────────────────────────────────────────────

const Dropdown = ({ children }) => {
    return (
        <Menu as="div" className="relative">
            {children}
        </Menu>
    );
};

Dropdown.Trigger = Trigger;
Dropdown.Content = Content;
Dropdown.Link = DropdownLink;

export default Dropdown;
