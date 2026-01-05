/**
 * Global sorting utilities for table columns
 */

/**
 * Update sort icons in table headers based on current sort state
 * @param {string} tableId - The ID of the table (e.g., 'orders-table', 'exports-table')
 * @param {string} sortBy - The column name currently being sorted
 * @param {string} sortDir - The sort direction ('asc' or 'desc')
 */
window.updateSortIcons = function(tableId, sortBy, sortDir) {
    // Remove active classes from all sort icons and reset to neutral
    document.querySelectorAll(`#${tableId} th button[data-column]`).forEach(button => {
        const svg = button.querySelector('svg');
        if (svg) {
            svg.classList.remove('text-indigo-600');
            svg.classList.add('text-gray-400');

            // Reset to neutral icon (double arrows) for non-active columns
            const path = svg.querySelector('path');
            if (path && button.getAttribute('data-column') !== sortBy) {
                path.setAttribute('d', 'M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4');
            }
        }
    });

    // Update icon for the active sort column
    if (sortBy && sortDir) {
        const activeHeader = document.querySelector(`#${tableId} th button[data-column="${sortBy}"]`);
        if (activeHeader) {
            const svg = activeHeader.querySelector('svg');
            if (svg) {
                svg.classList.remove('text-gray-400');
                svg.classList.add('text-indigo-600');

                const path = svg.querySelector('path');
                if (path) {
                    if (sortDir === 'asc') {
                        path.setAttribute('d', 'M5 15l7-7 7 7');
                    } else {
                        path.setAttribute('d', 'M19 9l-7 7-7-7');
                    }
                }
            }
        }
    }
};

