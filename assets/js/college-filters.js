(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.jQuery || !jQuery.fn.select2) return;
        document.querySelectorAll('#collegeUniversityFilter, select[data-styled-filter]').forEach(function (select) {
        const kind = select.dataset.filterKind || 'universities';
        function renderUniversity(option) {
            if (!option.id) return option.text;
            const row = document.createElement('span');
            row.className = 'university-option';
            const icon = document.createElement('span');
            icon.className = 'university-option-icon';
            const glyph = document.createElement('i');
            glyph.className = option.id === 'solo' ? 'bi bi-person' : option.id === 'team' ? 'bi bi-people' : option.id === 'all' ? 'bi bi-grid' : (select.dataset.filterIcon || 'bi bi-bank');
            glyph.setAttribute('aria-hidden', 'true');
            icon.appendChild(glyph);
            const text = document.createElement('span');
            text.className = 'university-option-text';
            text.textContent = option.text;
            const check = document.createElement('i');
            check.className = 'bi bi-check-circle-fill university-option-check';
            check.setAttribute('aria-hidden', 'true');
            row.append(icon, text, check);
            return jQuery(row);
        }
        jQuery(select).select2({
            width: '100%',
            minimumResultsForSearch: kind === 'formats' ? Infinity : 0,
            dropdownCssClass: 'filter-menu-' + kind,
            dropdownCssClass: 'filter-menu-' + kind,
            dropdownParent: jQuery(select.closest('.university-filter-field')),
            templateResult: renderUniversity,
            language: { noResults: function () { return 'No ' + kind + ' found. Try another name.'; } }
        }).on('select2:select', function () {
            // Preserve the existing native change listener and GET-only live filtering.
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }).on('select2:open', function () {
            const search = select.closest('.university-filter-field').querySelector('.select2-search__field');
            if (search && kind !== 'formats') {
                search.placeholder = 'Search ' + kind + '…';
                search.setAttribute('aria-label', 'Search ' + kind);
                search.focus();
            } else {
                select.closest('.university-filter-field').querySelector('.select2-selection').focus();
            }
        });
        });
    });
})();
