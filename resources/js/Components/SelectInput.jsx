import { forwardRef } from 'react';

/**
 * Select estilizado, consistente con TextInput de Breeze.
 */
export default forwardRef(function SelectInput(
    { className = '', children, ...props },
    ref,
) {
    return (
        <select
            {...props}
            ref={ref}
            className={
                'rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-brand-600 dark:focus:ring-brand-600 ' +
                className
            }
        >
            {children}
        </select>
    );
});
