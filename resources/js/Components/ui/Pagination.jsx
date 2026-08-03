import { usePage, router } from '@inertiajs/react';

export default function Pagination({ paginator, className = '' }) {
    const { url } = usePage();

    if (!paginator || !paginator.links || paginator.last_page <= 1) {
        return null;
    }

    const currentPage = paginator.current_page;
    const lastPage = paginator.last_page;

    const getPageUrl = (page) => {
        const params = new URLSearchParams();
        if (page > 1) params.set('page', page);
        // Preserve existing query params
        const currentParams = new URLSearchParams(url.split('?')[1] || '');
        currentParams.forEach((value, key) => {
            if (key !== 'page') params.set(key, value);
        });
        const baseUrl = url.split('?')[0];
        return `${baseUrl}?${params.toString()}`;
    };

    const pages = [];
    const showPages = 5;
    let start = Math.max(1, currentPage - Math.floor(showPages / 2));
    let end = Math.min(lastPage, start + showPages - 1);

    if (end - start + 1 < showPages) {
        start = Math.max(1, end - showPages + 1);
    }

    for (let i = start; i <= end; i++) {
        pages.push(i);
    }

    const goToPage = (page) => {
        if (page < 1 || page > lastPage || page === currentPage) return;
        router.get(getPageUrl(page), { preserveScroll: true });
    };

    return (
        <nav className={`pagination ${className}`} aria-label="Pagination">
            <button
                onClick={() => goToPage(currentPage - 1)}
                disabled={currentPage === 1}
                className={`pagination-link ${currentPage === 1 ? 'pagination-link-disabled' : 'pagination-link-inactive'}`}
                aria-label="Previous page"
            >
                <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            {start > 1 && (
                <>
                    <button
                        onClick={() => goToPage(1)}
                        className="pagination-link pagination-link-inactive"
                    >
                        1
                    </button>
                    {start > 2 && <span className="pagination-ellipsis">…</span>}
                </>
            )}

            {pages.map((page) => (
                <button
                    key={page}
                    onClick={() => goToPage(page)}
                    className={`pagination-link ${page === currentPage ? 'pagination-link-active' : 'pagination-link-inactive'}`}
                    aria-current={page === currentPage ? 'page' : undefined}
                >
                    {page}
                </button>
            ))}

            {end < lastPage && (
                <>
                    {end < lastPage - 1 && <span className="pagination-ellipsis">…</span>}
                    <button
                        onClick={() => goToPage(lastPage)}
                        className="pagination-link pagination-link-inactive"
                    >
                        {lastPage}
                    </button>
                </>
            )}

            <button
                onClick={() => goToPage(currentPage + 1)}
                disabled={currentPage === lastPage}
                className={`pagination-link ${currentPage === lastPage ? 'pagination-link-disabled' : 'pagination-link-inactive'}`}
                aria-label="Next page"
            >
                <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>

            <span className="text-sm text-brand-500 ml-2 hidden sm:inline">
                Showing {((currentPage - 1) * paginator.per_page) + 1} to {Math.min(currentPage * paginator.per_page, paginator.total)} of {paginator.total} results
            </span>
        </nav>
    );
}