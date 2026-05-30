export default function Checkbox({ className = '', ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded border-gray-300 bg-white text-brand-600 shadow-sm focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 ' +
                className
            }
        />
    );
}
