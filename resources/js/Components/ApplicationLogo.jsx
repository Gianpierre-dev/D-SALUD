export default function ApplicationLogo({ className = '', ...props }) {
    return (
        <img
            {...props}
            src="/logo.png"
            alt="Botica D'Salud"
            className={className}
        />
    );
}
