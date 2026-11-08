document.addEventListener('DOMContentLoaded', () => {
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
    const resultsContainer = sidebar.querySelector('#like-winisis-results');

    // Helper to shift main content smoothly when sidebar opens/closes
    const adjustMainLayout = (isOpen) => {
        if (!window.top) return;
        const mainIframe = window.top.document.getElementById('main');
        if (mainIframe) {
            mainIframe.style.transition = 'margin-left 0.25s ease-in-out, width 0.25s ease-in-out';
            if (isOpen) {
                mainIframe.style.marginLeft = '340px';
                mainIframe.style.width = 'calc(100% - 340px)';
            } else {
                mainIframe.style.marginLeft = '0px';
                mainIframe.style.width = '100%';
            }
        }
    };

    if (toggleBtn) {
        toggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const isOpen = sidebar.classList.toggle('active');
            adjustMainLayout(isOpen);
            if (isOpen) {
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

    const executeSearch = async () => {
        const query = input.value.trim();
        if (!query) return;

        resultsContainer.innerHTML = '<div style="padding:10px; color:#555;"><i class="fas fa-spinner fa-spin"></i> ...</div>';
        const base = (window.top && window.top.base) ? window.top.base : new URLSearchParams(window.location.search).get('base');

        try {
            const response = await fetch('../../content/plugins/like-winisis/index.php?action=ajax', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ query, base })
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

            if (!res.data || res.data.length === 0) {
                resultsContainer.innerHTML = `<div class="winisis-empty">${winisisLang.empty}</div>`;
                return;
            }

            resultsContainer.innerHTML = res.data.map(item => `
                <div class="winisis-result-card" data-mfn="${item.mfn}">
                    <span class="winisis-mfn">${String(item.mfn).padStart(6, '0')}</span>
                    <span class="winisis-content">${item.content}</span>
                </div>
            `).join('');

            sidebar.querySelectorAll('.winisis-result-card').forEach(card => {
                card.addEventListener('click', function () {
                    sidebar.querySelectorAll('.winisis-result-card').forEach(c => c.classList.remove('selected'));
                    this.classList.add('selected');

                    const mfn = parseInt(this.getAttribute('data-mfn'), 10);
                    if (window.top) {
                        const maxMfn = window.top.maxmfn || mfn;
                        window.top.browseby = 'mfn';
                        window.top.mfn = mfn;
                        window.top.tope = maxMfn;

                        if (window.top.menu?.document?.forma1?.ir_a) {
                            window.top.menu.document.forma1.ir_a.value = `${mfn}/${maxMfn}`;
                        }

                        window.top.Menu('ver');
                    }
                });
            });
        } catch (err) {
            resultsContainer.innerHTML = `<div class="winisis-error">${winisisLang.error} (${err.message})</div>`;
        }
    };

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            executeSearch();
        }
    });
});