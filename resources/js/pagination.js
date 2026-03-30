// Global pagination component for Alpine.js
if (typeof window.paginationComponent === 'undefined') {
    window.paginationComponent = function(currentPage, lastPage, hasPages, id) {
        const componentId = id || 'pagination-' + Math.random().toString(36).substr(2, 9);

        const toInt = (v, fallback) => {
            const n = Number.parseInt(String(v ?? ''), 10);
            return Number.isFinite(n) && !Number.isNaN(n) ? n : fallback;
        };

        return {
            currentPage: toInt(currentPage, 1),
            lastPage: toInt(lastPage, 1),
            hasPages: !!hasPages,
            id: componentId,

            goToPage(page) {
                page = toInt(page, this.currentPage);
                if (page < 1 || page > this.lastPage || page === this.currentPage) {
                    return;
                }
                this.currentPage = page;

                // Dispatch custom event for parent component to handle
                window.dispatchEvent(new CustomEvent('pagination-change', {
                    detail: { 
                        page: page, 
                        componentId: this.id,
                        currentPage: this.currentPage,
                        lastPage: this.lastPage
                    }
                }));
            },

            getPageNumbers() {
                const pages = [];
                const total = toInt(this.lastPage, 1);
                const current = toInt(this.currentPage, 1);

                if (total <= 7) {
                    for (let i = 1; i <= total; i++) {
                        pages.push(i);
                    }
                } else {
                    if (current <= 3) {
                        for (let i = 1; i <= 4; i++) pages.push(i);
                        pages.push('...');
                        pages.push(total);
                    } else if (current >= total - 2) {
                        pages.push(1);
                        pages.push('...');
                        for (let i = total - 3; i <= total; i++) pages.push(i);
                    } else {
                        pages.push(1);
                        pages.push('...');
                        for (let i = current - 1; i <= current + 1; i++) pages.push(i);
                        pages.push('...');
                        pages.push(total);
                    }
                }
                return pages;
            },

            updatePagination(newCurrentPage, newLastPage, newHasPages) {
                this.currentPage = toInt(newCurrentPage, 1);
                this.lastPage = toInt(newLastPage, 1);
                this.hasPages = !!newHasPages;
            },
        }
    }
}

