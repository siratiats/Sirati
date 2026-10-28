<!doctype html>
<html lang="@yield('html_lang', 'en')" dir="@yield('html_dir', 'ltr')">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Siratie Admin')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap">
    @php($adminCss = public_path('css/admin.css'))
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ file_exists($adminCss) ? filemtime($adminCss) : '1' }}">
</head>
<body class="@yield('body_class', 'admin-body')">
    @yield('content')

    <script>
        (() => {
            const body = document.body;
            const toggle = document.querySelector('[data-admin-menu-toggle]');
            const setMenu = (open) => {
                body.classList.toggle('admin-menu-open', open);
                toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
            };
            toggle?.addEventListener('click', () => setMenu(!body.classList.contains('admin-menu-open')));
            document.querySelectorAll('[data-admin-menu-close], .admin-nav a').forEach((el) => el.addEventListener('click', () => setMenu(false)));
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape') setMenu(false); });

            window.adminToast = (message, type = 'success') => {
                let stack = document.querySelector('.toast-stack');
                if (!stack) {
                    stack = document.createElement('div');
                    stack.className = 'toast-stack';
                    stack.setAttribute('aria-live', 'polite');
                    body.appendChild(stack);
                }
                const toast = document.createElement('section');
                toast.className = 'toast toast-' + type;
                const text = document.createElement('p');
                text.textContent = message;
                toast.appendChild(text);
                stack.appendChild(toast);
                setTimeout(() => toast.remove(), 6000);
            };
            document.querySelectorAll('[data-toast]').forEach((toast) => setTimeout(() => toast.remove(), 6000));

            document.querySelectorAll('[data-loading-form]').forEach((form) => {
                form.addEventListener('submit', () => {
                    const button = form.querySelector('[data-loading-button]');
                    const label = form.querySelector('[data-loading-label]');
                    if (!button || !label) return;
                    let seconds = 0;
                    button.disabled = true;
                    button.classList.add('button-loading');
                    const render = () => {
                        label.textContent = `Working… ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
                        seconds += 1;
                    };
                    render();
                    setInterval(render, 1000);
                });
            });

            document.querySelectorAll('[data-ajax-form]').forEach((form) => form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const button = form.querySelector('button[type="submit"]');
                if (button) button.disabled = true;
                try {
                    const response = await fetch(form.action, {
                        method: form.method,
                        body: new FormData(form),
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        adminToast(payload.message || 'Request failed', 'error');
                        return;
                    }
                    document.querySelector(form.dataset.ajaxTarget)?.insertAdjacentHTML(form.dataset.ajaxPrepend === 'false' ? 'beforeend' : 'afterbegin', payload.row_html || '');
                    document.querySelector(form.dataset.ajaxDialogTarget)?.insertAdjacentHTML('beforeend', payload.dialog_html || '');
                    document.querySelector(form.dataset.ajaxEmptyTarget)?.remove();
                    adminToast(payload.message || 'Saved');
                    form.reset();
                    form.closest('dialog')?.close();
                } catch (error) {
                    adminToast('Request failed', 'error');
                } finally {
                    if (button) button.disabled = false;
                }
            }));

            document.querySelectorAll('[data-check-all]').forEach((checkbox) => {
                checkbox.addEventListener('change', () => {
                    document.querySelectorAll(checkbox.dataset.checkAll).forEach((item) => { item.checked = checkbox.checked; });
                });
            });

            document.querySelectorAll('[data-confirm]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
                });
            });

            // Activity chart hover: reads the per-bucket values from data attributes.
            document.querySelectorAll('[data-chart]').forEach((wrap) => {
                const svg = wrap.querySelector('svg');
                const tip = wrap.querySelector('.chart-tooltip');
                const line = wrap.querySelector('.hover-line');
                const points = JSON.parse(wrap.dataset.chart || '[]');
                if (!svg || !tip || !points.length) return;
                const hide = () => { tip.hidden = true; line?.setAttribute('visibility', 'hidden'); };
                svg.addEventListener('mouseleave', hide);
                svg.addEventListener('mousemove', (event) => {
                    const box = svg.getBoundingClientRect();
                    const viewWidth = svg.viewBox.baseVal.width;
                    const x = (event.clientX - box.left) / box.width * viewWidth;
                    let nearest = points[0];
                    points.forEach((p) => { if (Math.abs(p.x - x) < Math.abs(nearest.x - x)) nearest = p; });
                    line?.setAttribute('x1', nearest.x);
                    line?.setAttribute('x2', nearest.x);
                    line?.setAttribute('visibility', 'visible');
                    tip.replaceChildren();
                    const title = document.createElement('strong');
                    title.textContent = nearest.label;
                    tip.appendChild(title);
                    nearest.rows.forEach(([name, value], index) => {
                        const row = document.createElement('div');
                        if (index === 0) row.className = 't-teal';
                        const a = document.createElement('span');
                        a.textContent = name;
                        const b = document.createElement('span');
                        b.textContent = value;
                        row.append(a, b);
                        tip.appendChild(row);
                    });
                    tip.hidden = false;
                    const left = (nearest.x / viewWidth) * box.width + (wrap.clientWidth - box.width) / 2;
                    tip.style.left = Math.min(Math.max(8, left - tip.offsetWidth - 12), wrap.clientWidth - tip.offsetWidth - 8) + 'px';
                    tip.style.top = '24px';
                });
            });
        })();
    </script>
    @stack('admin_scripts')
</body>
</html>
