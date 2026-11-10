document.addEventListener('DOMContentLoaded', () => {
    const themeRoot = document.documentElement;
    const themeToggle = document.querySelector('[data-theme-toggle]');
    const themeColor = document.querySelector('#lms-theme-color');

    const applyTheme = (theme, persist = false) => {
        const normalizedTheme = theme === 'dark' ? 'dark' : 'light';
        themeRoot.dataset.theme = normalizedTheme;
        themeRoot.style.colorScheme = normalizedTheme;

        if (themeToggle) {
            const darkThemeActive = normalizedTheme === 'dark';
            const actionLabel = darkThemeActive ? 'Aktifkan mode terang' : 'Aktifkan mode gelap';
            themeToggle.setAttribute('aria-label', actionLabel);
            themeToggle.setAttribute('title', actionLabel);
            themeToggle.setAttribute('aria-pressed', String(darkThemeActive));
        }

        themeColor?.setAttribute('content', normalizedTheme === 'dark' ? '#0d1426' : '#f5f7fb');

        if (persist) {
            try {
                localStorage.setItem('lms-theme', normalizedTheme);
            } catch (_) {}
        }
    };

    if (themeToggle) {
        applyTheme(themeRoot.dataset.theme || 'light');
        themeToggle.addEventListener('click', () => {
            applyTheme(themeRoot.dataset.theme === 'dark' ? 'light' : 'dark', true);
        });
    }

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const field = button.closest('.lms-password-field');
        const input = field?.querySelector('input');
        const showIcon = button.querySelector('[data-password-icon="show"]');
        const hideIcon = button.querySelector('[data-password-icon="hide"]');

        if (!input) return;

        button.addEventListener('click', () => {
            const passwordWillBeVisible = input.type === 'password';
            input.type = passwordWillBeVisible ? 'text' : 'password';
            showIcon?.toggleAttribute('hidden', !passwordWillBeVisible);
            hideIcon?.toggleAttribute('hidden', passwordWillBeVisible);

            const actionLabel = passwordWillBeVisible ? 'Sembunyikan password' : 'Tampilkan password';
            button.setAttribute('aria-label', actionLabel);
            button.setAttribute('title', actionLabel);
            button.setAttribute('aria-pressed', String(passwordWillBeVisible));
            input.focus({preventScroll: true});

            const cursorPosition = input.value.length;
            input.setSelectionRange?.(cursorPosition, cursorPosition);
        });
    });

    const sidebar = document.querySelector('[data-sidebar]');
    const overlay = document.querySelector('[data-sidebar-overlay]');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const mobileSidebar = window.matchMedia('(max-width: 820px)');

    const setSidebar = (open) => {
        const drawerOpen = mobileSidebar.matches && open;
        sidebar?.classList.toggle('open', drawerOpen);
        overlay?.classList.toggle('open', drawerOpen);
        document.body.style.overflow = drawerOpen ? 'hidden' : '';
        toggle?.setAttribute('aria-expanded', String(drawerOpen));

        if (sidebar) {
            sidebar.inert = mobileSidebar.matches && !drawerOpen;
            if (mobileSidebar.matches) sidebar.setAttribute('aria-hidden', String(!drawerOpen));
            else sidebar.removeAttribute('aria-hidden');
        }
    };

    toggle?.addEventListener('click', () => setSidebar(!sidebar?.classList.contains('open')));
    overlay?.addEventListener('click', () => setSidebar(false));
    sidebar?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setSidebar(false)));
    mobileSidebar.addEventListener('change', () => setSidebar(false));
    setSidebar(false);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setSidebar(false);
    });

    const topbarMenus = [...document.querySelectorAll('[data-topbar-menu]')];
    topbarMenus.forEach((menu) => {
        menu.addEventListener('toggle', () => {
            if (!menu.open) return;
            topbarMenus.forEach(other => {
                if (other !== menu) other.open = false;
            });
        });
    });
    document.addEventListener('click', (event) => {
        topbarMenus.forEach(menu => {
            if (menu.open && !menu.contains(event.target)) menu.open = false;
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') topbarMenus.forEach(menu => { menu.open = false; });
    });

    const search = document.querySelector('[data-class-search]');
    const cards = [...document.querySelectorAll('[data-class-card]')];
    const noResults = document.querySelector('[data-no-results]');

    if (search && cards.length) {
        search.addEventListener('input', () => {
            const query = search.value.toLocaleLowerCase('id').trim();
            let visible = 0;

            cards.forEach((card) => {
                const match = card.textContent.toLocaleLowerCase('id').includes(query);
                card.style.display = match ? '' : 'none';
                if (match) visible += 1;
            });

            if (noResults) noResults.style.display = visible ? 'none' : 'block';
        });
    }

    document.querySelectorAll('[data-copy-code]').forEach((button) => {
        button.addEventListener('click', async () => {
            const code = button.dataset.copyCode;
            try {
                await navigator.clipboard.writeText(code);
                const previous = button.textContent;
                button.textContent = 'Tersalin!';
                setTimeout(() => { button.textContent = previous; }, 1500);
            } catch (_) {
                window.prompt('Salin kode kelas:', code);
            }
        });
    });
});
