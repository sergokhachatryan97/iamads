// Global pagination component for Alpine.js
if (typeof window.paginationComponent === 'undefined') {
    window.paginationComponent = function(currentPage, lastPage, hasPages, id) {
        const componentId = id || 'pagination-' + Math.random().toString(36).substr(2, 9);
        
        return {
            currentPage: currentPage,
            lastPage: lastPage,
            hasPages: hasPages,
            id: componentId,

            goToPage(page) {
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
                const total = this.lastPage;
                const current = this.currentPage;

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
                this.currentPage = newCurrentPage;
                this.lastPage = newLastPage;
                this.hasPages = newHasPages;
            },
        }
    }
}

