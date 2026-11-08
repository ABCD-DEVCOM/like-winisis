document.addEventListener('DOMContentLoaded', () => {
    const STORAGE_KEY_SAVED = 'abcd_like_winisis_saved_expressions';
    const toggleBtn = document.getElementById('like-winisis-toggle');
    let sidebar = document.getElementById('like-winisis-sidebar');

    const targetDoc = (window.top && window.top.document) ? window.top.document : document;

    if (sidebar && targetDoc !== document) {
        const existingSidebar = targetDoc.getElementById('like-winisis-sidebar');
        if (existingSidebar) {
            existingSidebar.remove();
        }
        targetDoc.body.appendChild(sidebar);

        if (!targetDoc.getElementById('like-winisis-css')) {
            const link = targetDoc.createElement('link');
            link.id = 'like-winisis-css';
            link.rel = 'stylesheet';
            link.href = '../../content/plugins/like-winisis/assets/css/like-winisis.css';
            targetDoc.head.appendChild(link);
        }
        sidebar = targetDoc.getElementById('like-winisis-sidebar');
    }

    if (!sidebar) return;

    const closeBtn = sidebar.querySelector('#like-winisis-close');
    const input = sidebar.querySelector('#like-winisis-input');
    const execBtn = sidebar.querySelector('#like-winisis-exec-btn');
    const saveBtn = sidebar.querySelector('#like-winisis-save-btn');
    const resetBtn = sidebar.querySelector('#like-winisis-reset-btn');
    const clearHistoryBtn = sidebar.querySelector('#like-winisis-clear-history');
    const historyListEl = sidebar.querySelector('#like-winisis-history-list');
    const savedListEl = sidebar.querySelector('#like-winisis-saved-list');
    const sessionBar = sidebar.querySelector('#like-winisis-session-bar');
    const countBadge = sidebar.querySelector('#like-winisis-count-badge');
    const resultsContainer = sidebar.querySelector('#like-winisis-results');

    // Store initial help HTML on load so the reset button can restore it
    const initialHelpHTML = resultsContainer ? resultsContainer.innerHTML : '';

    const adjustMainLayout = (isOpen) => {
        if (!window.top) return;
        const mainIframe = window.top.document.getElementById('main');
        if (mainIframe) {
            mainIframe.style.transition = 'margin-left 0.25s ease-in-out, width 0.25s ease-in-out';
            if (isOpen) {
                mainIframe.style.marginLeft = '360px';
                mainIframe.style.width = 'calc(100% - 360px)';
            } else {
                mainIframe.style.marginLeft = '0px';
                mainIframe.style.width = '100%';
            }
        }
    };

    const getSavedExpressions = () => {
        try {
            const raw = localStorage.getItem(STORAGE_KEY_SAVED);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    };

    const setSavedExpressions = (list) => {
        try {
            localStorage.setItem(STORAGE_KEY_SAVED, JSON.stringify(list));
        } catch (e) { }
    };

    const renderSavedExpressions = () => {
        const list = getSavedExpressions();
        if (list.length === 0) {
            savedListEl.innerHTML = '<div class="winisis-expr-empty">-</div>';
            return;
        }
        savedListEl.innerHTML = list.map((expr, idx) => `
            <div class="winisis-expr-item">
                <span class="winisis-expr-run" data-expr="${expr.replace(/"/g, '&quot;')}">${expr}</span>
                <button type="button" class="winisis-expr-del" data-idx="${idx}" title="Remove">&times;</button>
            </div>
        `).join('');

        savedListEl.querySelectorAll('.winisis-expr-run').forEach(el => {
            el.addEventListener('click', () => {
                input.value = el.getAttribute('data-expr');
                executeSearch();
            });
        });

        savedListEl.querySelectorAll('.winisis-expr-del').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const idx = parseInt(btn.getAttribute('data-idx'), 10);
                const updated = getSavedExpressions().filter((_, i) => i !== idx);
                setSavedExpressions(updated);
                renderSavedExpressions();
            });
        });
    };

    const renderSessionHistory = (historyArray) => {
        if (!historyArray || historyArray.length === 0) {
            historyListEl.innerHTML = '<div class="winisis-expr-empty">-</div>';
            return;
        }
        historyListEl.innerHTML = historyArray.map(item => `
            <div class="winisis-expr-item">
                <span class="winisis-expr-run" data-expr="${item.expression.replace(/"/g, '&quot;')}">
                    <strong>#${item.id}</strong> (${item.base}) T=${item.total} : <code>${item.expression}</code>
                </span>
            </div>
        `).join('');

        historyListEl.querySelectorAll('.winisis-expr-run').forEach(el => {
            el.addEventListener('click', () => {
                input.value = el.getAttribute('data-expr');
                executeSearch();
            });
        });
    };

    const fetchSessionHistory = async () => {
        try {
            const response = await fetch('../../content/plugins/like-winisis/index.php?action=ajax', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ sub_action: 'get_history' })
            });
            const res = await response.json();
            if (res.status === 'success') {
                renderSessionHistory((res.history || []).slice().reverse());
            }
        } catch (e) { }
    };

    /**
     * Synchronizes the retrieved MFN list with ABCD's active session (RegistrosSeleccionados)
     * so the native toolbar navigation (First, Prev, Next, Last, Print, Global Change) works seamlessly.
     */
    const syncWithAbcdActiveSession = (dataArray) => {
        if (!window.top || !Array.isArray(dataArray) || dataArray.length === 0) return;

        const mfnList = dataArray.map(item => item.mfn);
        const formattedSelection = mfnList.map(m => `_${m}_`).join('');

        window.top.RegistrosSeleccionados = formattedSelection;
        window.top.browseby = 'selected_records';
        window.top.Listar_pos = 0;

        const menuFrame = window.top.menu;
        if (menuFrame && menuFrame.document) {
            const browseSelect = menuFrame.document.getElementById('browseby');
            if (browseSelect) {
                browseSelect.value = 'selected_records';
            }
            if (menuFrame.document.forma1 && menuFrame.document.forma1.ir_a) {
                menuFrame.document.forma1.ir_a.value = `1/${mfnList.length}`;
            }
        }
    };

    if (toggleBtn) {
        toggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const isOpen = sidebar.classList.toggle('active');
            adjustMainLayout(isOpen);
            if (isOpen) {
                renderSavedExpressions();
                fetchSessionHistory();
                input.focus();
            }
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            sidebar.classList.remove('active');
            adjustMainLayout(false);
        });
    }

    if (saveBtn) {
        saveBtn.addEventListener('click', () => {
            const expr = input.value.trim();
            if (!expr) return;
            const list = getSavedExpressions();
            if (!list.includes(expr)) {
                list.unshift(expr);
                setSavedExpressions(list);
                renderSavedExpressions();
                const savedDetails = sidebar.querySelector('#like-winisis-saved-details');
                if (savedDetails) savedDetails.open = true;
            }
        });
    }

    if (clearHistoryBtn) {
        clearHistoryBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            try {
                await fetch('../../content/plugins/like-winisis/index.php?action=ajax', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ sub_action: 'clear_history' })
                });
                renderSessionHistory([]);
            } catch (err) { }
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            // Clear search input
            input.value = '';
            // Restore initial help instructions
            if (resultsContainer) {
                resultsContainer.innerHTML = initialHelpHTML;
            }
            // Hide active session counter bar
            if (sessionBar) {
                sessionBar.style.display = 'none';
            }
            // Clear count badge text
            if (countBadge) {
                countBadge.textContent = '';
            }
            // Return focus to input
            input.focus();
        });
    }

    const executeSearch = async () => {
        const query = input.value.trim();
        if (!query) return;

        resultsContainer.innerHTML = '<div style="padding:10px; color:#555;"><i class="fas fa-spinner fa-spin"></i> ...</div>';
        sessionBar.style.display = 'none';
        const base = (window.top && window.top.base) ? window.top.base : new URLSearchParams(window.location.search).get('base');

        try {
            const response = await fetch('../../content/plugins/like-winisis/index.php?action=ajax', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ sub_action: 'search', query, base })
            });

            const rawText = await response.text();
            let res;

            try {
                const jsonStart = rawText.indexOf('{');
                const cleanJson = jsonStart > 0 ? rawText.slice(jsonStart) : rawText;
                res = JSON.parse(cleanJson);
            } catch (parseErr) {
                resultsContainer.innerHTML = `<div class="winisis-error"><strong>PHP Output:</strong><br><pre style="white-space:pre-wrap;font-size:11px;">${rawText.slice(0, 400)}</pre></div>`;
                return;
            }

            if (res.status === 'error') {
                resultsContainer.innerHTML = `<div class="winisis-error">${res.message}</div>`;
                return;
            }

            if (res.history) {
                renderSessionHistory(res.history);
            }

            if (!res.data || res.data.length === 0) {
                resultsContainer.innerHTML = `<div class="winisis-empty">${winisisLang.empty}</div>`;
                return;
            }

            // Integrate results with ABCD active session
            syncWithAbcdActiveSession(res.data);
            countBadge.textContent = `T = ${res.total}`;
            sessionBar.style.display = 'flex';

            resultsContainer.innerHTML = res.data.map((item, index) => `
                <div class="winisis-result-card" data-mfn="${item.mfn}" data-pos="${index}">
                    <span class="winisis-mfn">${String(item.mfn).padStart(6, '0')}</span>
                    <span class="winisis-content">${item.content}</span>
                </div>
            `).join('');

            sidebar.querySelectorAll('.winisis-result-card').forEach(card => {
                card.addEventListener('click', function () {
                    sidebar.querySelectorAll('.winisis-result-card').forEach(c => c.classList.remove('selected'));
                    this.classList.add('selected');

                    const mfn = parseInt(this.getAttribute('data-mfn'), 10);
                    const pos = parseInt(this.getAttribute('data-pos'), 10);
                    const totalSelected = res.data.length;

                    if (window.top) {
                        window.top.browseby = 'selected_records';
                        window.top.Listar_pos = pos;
                        window.top.mfn = mfn;
                        window.top.tope = totalSelected;

                        if (window.top.menu?.document?.forma1?.ir_a) {
                            window.top.menu.document.forma1.ir_a.value = `${pos + 1}/${totalSelected}`;
                        }

                        window.top.Menu('ver');
                    }
                });
            });

            // Automatically load the first record into the main panel upon search completion
            const firstCard = sidebar.querySelector('.winisis-result-card');
            if (firstCard) {
                firstCard.click();
            }
        } catch (err) {
            resultsContainer.innerHTML = `<div class="winisis-error">${winisisLang.error} (${err.message})</div>`;
        }
    };

    if (execBtn) {
        execBtn.addEventListener('click', executeSearch);
    }

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            executeSearch();
        }
    });

    renderSavedExpressions();
    fetchSessionHistory();
});