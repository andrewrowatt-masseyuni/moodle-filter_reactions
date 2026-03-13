/**
 * AMD module for filter_reactions - handles thumbs and stars interactions.
 *
 * @module     filter_reactions/main
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

/**
 * Initialise event delegation for all reaction widgets on the page.
 */
export const init = () => {
    window.console.log('filter_reactions/main: init called, adding click listener');
    document.addEventListener('click', handleClick);
};

/**
 * Handle click events on reaction buttons.
 *
 * @param {Event} e The click event.
 */
const handleClick = async(e) => {
    const btn = e.target.closest('[data-action="react"]');
    if (!btn) {
        return;
    }
    window.console.log('filter_reactions: button clicked', btn);

    const widget = btn.closest('[data-region="filter-reactions"]');
    if (!widget) {
        window.console.log('filter_reactions: no widget found');
        return;
    }

    const contextid = parseInt(widget.dataset.contextid);
    const itemid = widget.dataset.itemid;
    const type = widget.dataset.type;
    const response = btn.dataset.response;
    window.console.log('filter_reactions: params', {contextid, itemid, type, response});

    if (!itemid) {
        window.console.log('filter_reactions: no itemid, aborting');
        return;
    }

    // Disable buttons during request.
    const buttons = widget.querySelectorAll('button');
    buttons.forEach(b => {
        b.disabled = true;
    });

    try {
        const result = await Ajax.call([{
            methodname: 'filter_reactions_save_reaction',
            args: {contextid, itemid, type, response},
        }])[0];

        updateWidget(widget, type, result);
    } catch (err) {
        Notification.exception(err);
    } finally {
        buttons.forEach(b => {
            b.disabled = false;
        });
    }
};

/**
 * Update the widget DOM after a reaction is saved.
 *
 * @param {HTMLElement} widget The widget container element.
 * @param {string} type The reaction type (thumbs or stars).
 * @param {Object} result The server response with userresponse and counts.
 */
const updateWidget = (widget, type, result) => {
    // Build a counts map.
    const countsMap = {};
    result.counts.forEach(c => {
        countsMap[c.response] = c.count;
    });

    if (type === 'thumbs') {
        updateThumbs(widget, result.userresponse, countsMap);
    } else {
        updateStars(widget, result.userresponse, countsMap);
    }
};

/**
 * Update thumbs widget state.
 *
 * @param {HTMLElement} widget The widget container.
 * @param {string} userresponse The user's current response.
 * @param {Object} counts Response counts map.
 */
const updateThumbs = (widget, userresponse, counts) => {
    widget.querySelectorAll('[data-action="react"]').forEach(btn => {
        const resp = btn.dataset.response;
        const isSelected = resp === userresponse;

        btn.classList.toggle('active', isSelected);
        btn.setAttribute('aria-pressed', isSelected ? 'true' : 'false');

        // Toggle FA icon between solid (selected) and regular (outline).
        const icon = btn.querySelector('[class*="fa-thumbs"]');
        if (icon) {
            icon.classList.toggle('fa-solid', isSelected);
            icon.classList.toggle('fa-regular', !isSelected);
        }

        // Swap title between on/off states.
        btn.title = isSelected ? (btn.dataset.titleOn || '') : (btn.dataset.titleOff || '');

        const countEl = btn.querySelector('[data-region="count"]');
        if (countEl) {
            countEl.textContent = counts[resp] || 0;
        }
    });
};

/**
 * Update stars widget state.
 *
 * @param {HTMLElement} widget The widget container.
 * @param {string} userresponse The user's current response.
 * @param {Object} counts Response counts map.
 */
const updateStars = (widget, userresponse, counts) => {
    // Parse user's star value.
    let userValue = 0;
    const match = userresponse.match(/^(\d)stars?$/);
    if (match) {
        userValue = parseInt(match[1]);
    }

    widget.querySelectorAll('[data-action="react"]').forEach(btn => {
        const value = parseInt(btn.dataset.value);
        const isFilled = value <= userValue;
        btn.classList.toggle('filled', isFilled);
        btn.classList.toggle('selected', value === userValue);

        // Toggle FA icon between solid (filled) and regular (outline).
        const icon = btn.querySelector('.fa-star');
        if (icon) {
            icon.classList.toggle('fa-solid', isFilled);
            icon.classList.toggle('fa-regular', !isFilled);
        }
    });

    // Update summary.
    let totalCount = 0;
    let totalValue = 0;
    Object.entries(counts).forEach(([resp, count]) => {
        const m = resp.match(/^(\d)stars?$/);
        if (m) {
            totalCount += count;
            totalValue += parseInt(m[1]) * count;
        }
    });

    const summary = widget.querySelector('.filter-reactions-summary');
    if (totalCount > 0) {
        const avg = (totalValue / totalCount).toFixed(1);
        if (summary) {
            const avgEl = summary.querySelector('[data-field="average"]');
            const countEl = summary.querySelector('[data-field="totalcount"]');
            if (avgEl) {
                avgEl.textContent = avg;
            }
            if (countEl) {
                countEl.textContent = totalCount;
            }
            summary.style.display = '';
        }
    } else if (summary) {
        summary.style.display = 'none';
    }
};
