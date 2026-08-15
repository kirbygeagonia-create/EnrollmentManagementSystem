import { Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    // If a custom className is provided (e.g. from sidebar), use it directly
    const classes = className
        ? className
        : 'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none ' +
          (active
              ? 'border-seait-500 text-brand-900 font-semibold'
              : 'border-transparent text-brand-500 hover:border-brand-300 hover:text-brand-700');

    return (
        <Link {...props} className={classes}>
            {children}
        </Link>
    );
}
