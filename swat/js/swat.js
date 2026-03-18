/* ============================================================
   SWAT Plugin – JavaScript
   ============================================================ */

(function (window) {
    'use strict';

    /* ── SWAT namespace ─────────────────────────────────── */
    const SWAT = {};
    window.SWAT = SWAT;

    /* ── Toast notifications ─────────────────────────── */
    SWAT.toast = function (msg, type = 'info', duration = 3500) {
        let container = document.getElementById('swat-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'swat-toast-container';
            document.body.appendChild(container);
        }
        const el = document.createElement('div');
        el.className = `swat-toast swat-toast-${type}`;
        el.textContent = msg;
        container.appendChild(el);
        setTimeout(() => el.remove(), duration);
    };

    /* ── Life Saving Rules – checkbox toggle ─────────── */
    SWAT.initLSR = function () {
        document.querySelectorAll('.swat-lsr-item').forEach(item => {
            item.addEventListener('click', function () {
                const cb = this.querySelector('input[type=checkbox]');
                if (!cb) return;
                cb.checked = !cb.checked;
                this.classList.toggle('checked', cb.checked);
            });
            // Restore state on page load
            const cb = item.querySelector('input[type=checkbox]');
            if (cb && cb.checked) item.classList.add('checked');
        });
    };

    /* ── Participant autocomplete ─────────────────── */
    let _debounceTimer = null;

    SWAT.initParticipants = function () {
        // Participants autocomplete
        document.querySelectorAll('.swat-participant-input input').forEach(input => {
            input.addEventListener('input', function () {
                clearTimeout(_debounceTimer);
                const q = this.value.trim();
                if (q.length < 2) { SWAT._closeDropdown(this); return; }
                const row = this.closest('.swat-participant-row');
                _debounceTimer = setTimeout(() => SWAT._searchPeople(this, q, row), 280);
            });
            input.addEventListener('blur', function () {
                // Save free-text as display_name even if not from list
                const row = this.closest('.swat-participant-row');
                if (row) {
                    const dn = row.querySelector('.hidden-display-name');
                    if (dn && !dn.value) dn.value = this.value;
                }
                setTimeout(() => SWAT._closeDropdown(this), 200);
            });
        });

        // CP Sign-off autocomplete
        const signoffInput = document.getElementById('swat-signoff-input');
        if (signoffInput) {
            signoffInput.addEventListener('input', function () {
                clearTimeout(_debounceTimer);
                const q = this.value.trim();
                if (q.length < 2) { SWAT._closeSignoffDropdown(); return; }
                _debounceTimer = setTimeout(() => SWAT._searchSignoff(this, q), 280);
            });
            signoffInput.addEventListener('blur', function () {
                setTimeout(() => SWAT._closeSignoffDropdown(), 200);
            });
        }
    };

    // Generic autocomplete helper used by _searchSignoff, _searchPeople, _searchCP
    // onSelect(item, dd): called when user picks an item
    // appendDropdown(dd): called to insert the dropdown into the DOM
    // closeDropdown(): called to close any existing dropdown before showing new one
    SWAT._searchAutocomplete = function (inputEl, ddId, q, onSelect, appendDropdown, closeDropdown) {
        const ajaxUrl = window.CFG_GLPI?.root_doc + '/plugins/swat/ajax/getusers.php';
        fetch(`${ajaxUrl}?term=${encodeURIComponent(q)}&type=all`)
            .then(r => r.json())
            .then(data => {
                closeDropdown();
                if (!data.length) return;
                const dd = document.createElement('div');
                dd.className = 'swat-autocomplete-dropdown';
                if (ddId) dd.id = ddId;
                data.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'item';
                    // Safe DOM construction – avoid innerHTML with API data
                    const nameNode = document.createTextNode(item.display_name + ' ');
                    const badge = document.createElement('span');
                    badge.className = 'type-badge ' + item.type;
                    badge.textContent = item.type;
                    div.appendChild(nameNode);
                    div.appendChild(badge);
                    div.addEventListener('mousedown', () => onSelect(item, dd));
                    dd.appendChild(div);
                });
                appendDropdown(dd);
            })
            .catch(err => console.warn('SWAT fetch error:', err));
    };

    SWAT._searchSignoff = function (inputEl, q) {
        SWAT._searchAutocomplete(inputEl, 'swat-signoff-dd', q, function (item, dd) {
            inputEl.value = item.display_name;
            dd.remove();
        }, function (dd) {
            inputEl.parentElement.appendChild(dd);
        }, function () {
            SWAT._closeSignoffDropdown();
        });
    };

    SWAT._closeSignoffDropdown = function () {
        document.getElementById('swat-signoff-dd')?.remove();
    };

    SWAT._searchPeople = function (inputEl, q, row) {
        SWAT._searchAutocomplete(inputEl, null, q, function (item, dd) {
            inputEl.value = item.display_name;
            row.dataset.itemsId    = item.id;
            row.dataset.itemType   = item.type;
            row.dataset.displayName = item.display_name;
            // Update hidden fields
            row.querySelector('.hidden-items-id')    && (row.querySelector('.hidden-items-id').value    = item.id);
            row.querySelector('.hidden-item-type')   && (row.querySelector('.hidden-item-type').value   = item.type);
            row.querySelector('.hidden-display-name')&& (row.querySelector('.hidden-display-name').value = item.display_name);
            row.querySelector('.swat-participant-type').textContent = `(${item.type})`;
            dd.remove();
        }, function (dd) {
            inputEl.closest('.swat-participant-input').appendChild(dd);
        }, function () {
            SWAT._closeDropdown(inputEl);
        });
    };

    SWAT._closeDropdown = function (inputEl) {
        const wrap = inputEl.closest?.('.swat-participant-input');
        wrap?.querySelector('.swat-autocomplete-dropdown')?.remove();
    };

    /* ── CP (Competent Person) override ─────────────── */
    SWAT.initCPSelector = function () {
        const toggle = document.getElementById('swat-cp-toggle');
        const overrideWrap = document.getElementById('swat-cp-override-wrap');
        if (!toggle || !overrideWrap) return;

        toggle.addEventListener('change', function () {
            overrideWrap.style.display = this.checked ? 'block' : 'none';
            if (!this.checked) {
                // Reset to current user
                const cpInput = document.getElementById('swat-cp-input');
                if (cpInput) cpInput.value = '';
                document.getElementById('swat-cp-userid').value  = window.SWAT_CURRENT_USER_ID  || '0';
                document.getElementById('swat-cp-iscontact').value = '0';
            }
        });

        // Autocomplete for CP
        const cpInput = document.getElementById('swat-cp-input');
        if (cpInput) {
            cpInput.addEventListener('input', function () {
                clearTimeout(_debounceTimer);
                const q = this.value.trim();
                if (q.length < 2) { SWAT._closeCPDropdown(); return; }
                _debounceTimer = setTimeout(() => SWAT._searchCP(q), 280);
            });
            cpInput.addEventListener('blur', () => setTimeout(SWAT._closeCPDropdown, 200));
        }
    };

    SWAT._searchCP = function (q) {
        const cpInput = document.getElementById('swat-cp-input');
        if (!cpInput) return;
        SWAT._searchAutocomplete(cpInput, 'swat-cp-dropdown', q, function (item, dd) {
            document.getElementById('swat-cp-input').value       = item.display_name;
            document.getElementById('swat-cp-userid').value      = item.id;
            document.getElementById('swat-cp-iscontact').value   = item.type === 'contact' ? '1' : '0';
            dd.remove();
        }, function (dd) {
            const wrap = document.getElementById('swat-cp-override-wrap');
            if (wrap) wrap.appendChild(dd);
        }, function () {
            SWAT._closeCPDropdown();
        });
    };

    SWAT._closeCPDropdown = function () {
        document.getElementById('swat-cp-dropdown')?.remove();
    };

    /* ── Hazard-Controls table: add/remove rows ──── */
    SWAT.initHCTable = function () {
        const addBtn = document.getElementById('swat-hc-add');
        if (!addBtn) return;
        addBtn.addEventListener('click', () => SWAT._addHCRow());
        document.querySelectorAll('.swat-hc-remove').forEach(btn => {
            btn.addEventListener('click', function () { this.closest('tr').remove(); });
        });
    };

    SWAT._addHCRow = function () {
        const tbody = document.querySelector('#swat-hc-table tbody');
        if (!tbody) return;
        const idx = tbody.querySelectorAll('tr').length;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><textarea name="hazards_controls[${idx}][hazard]" placeholder="Hazard / סיכון"></textarea></td>
            <td><textarea name="hazards_controls[${idx}][control]" placeholder="Control measure / אמצעי בקרה"></textarea></td>
            <td style="text-align:center;vertical-align:middle;">
                <button type="button" class="swat-btn swat-btn-danger swat-hc-remove" style="padding:4px 10px;font-size:0.8rem;">✕</button>
            </td>`;
        tr.querySelector('.swat-hc-remove').addEventListener('click', function () { this.closest('tr').remove(); });
        tbody.appendChild(tr);
    };

    /* ── Permit accordion (Archive page) ──────────── */
    SWAT.initArchive = function () {
        document.querySelectorAll('.swat-permit-header').forEach(header => {
            header.addEventListener('click', function () {
                const forms = this.nextElementSibling;
                forms.classList.toggle('open');
                const icon = this.querySelector('.toggle-icon');
                if (icon) icon.textContent = forms.classList.contains('open') ? '▲' : '▼';
            });
        });
    };

    /* ── Form submission handled by plain HTML submit button – no JS needed ── */
    // SWAT.initForm is intentionally removed - the submit button uses onclick directly

    /* ── Auto-set date/time on load ───────────────── */
    SWAT.setDateTime = function () {
        const dateField = document.getElementById('swat-form-date');
        const timeField = document.getElementById('swat-form-time');
        const now = new Date();
        if (dateField && !dateField.value) {
            dateField.value = now.toISOString().split('T')[0];
        }
        if (timeField && !timeField.value) {
            timeField.value = now.toTimeString().slice(0, 5);
        }
    };

    /* ── Init all on DOM ready ────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        SWAT.initLSR();
        SWAT.initParticipants();
        SWAT.initCPSelector();
        SWAT.initHCTable();
        SWAT.initArchive();
        SWAT.setDateTime();
    });

})(window);
