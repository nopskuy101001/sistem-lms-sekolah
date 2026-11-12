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
    const yearFilter = document.querySelector('[data-class-year-filter]');
    const cards = [...document.querySelectorAll('[data-class-card]')];
    const noResults = document.querySelector('[data-no-results]');

    if (cards.length && (search || yearFilter)) {
        const filterCards = () => {
            const query = search?.value.toLocaleLowerCase('id').trim() ?? '';
            const selectedYear = yearFilter?.value ?? '';
            let visible = 0;

            cards.forEach((card) => {
                const matchesQuery = card.textContent.toLocaleLowerCase('id').includes(query);
                const matchesYear = !selectedYear || card.dataset.academicYear === selectedYear;
                const match = matchesQuery && matchesYear;
                card.style.display = match ? '' : 'none';
                if (match) visible += 1;
            });

            if (noResults) noResults.style.display = visible ? 'none' : 'block';
        };

        search?.addEventListener('input', filterCards);
        yearFilter?.addEventListener('change', filterCards);
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

    const questionBankExcelWarning = document.querySelector('[data-question-bank-excel-warning]');
    if (questionBankExcelWarning) {
        const previewAction = questionBankExcelWarning.querySelector('[data-question-bank-preview-action]');
        const packageAction = questionBankExcelWarning.querySelector('[data-question-bank-package-action]');
        const excelAction = questionBankExcelWarning.querySelector('[data-question-bank-excel-action]');
        const cancelAction = questionBankExcelWarning.querySelector('[data-question-bank-excel-cancel]');
        let activeExcelLink = null;

        const closeExcelWarning = () => {
            questionBankExcelWarning.classList.remove('show');
            questionBankExcelWarning.setAttribute('aria-hidden', 'true');
            questionBankExcelWarning.inert = true;
            activeExcelLink?.focus({preventScroll: true});
            activeExcelLink = null;
        };

        document.querySelectorAll('[data-question-bank-excel-download]').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                activeExcelLink = link;
                previewAction.href = link.dataset.previewUrl;
                packageAction.href = link.dataset.packageUrl;
                excelAction.href = link.href;
                questionBankExcelWarning.inert = false;
                questionBankExcelWarning.setAttribute('aria-hidden', 'false');
                questionBankExcelWarning.classList.add('show');
                previewAction.focus({preventScroll: true});
            });
        });

        cancelAction.addEventListener('click', closeExcelWarning);
        questionBankExcelWarning.addEventListener('click', (event) => {
            if (event.target === questionBankExcelWarning) closeExcelWarning();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && questionBankExcelWarning.classList.contains('show')) closeExcelWarning();
        });
    }

    const discussionBadge = document.querySelector('[data-discussion-unread-badge]');
    if (discussionBadge) {
        const renderDiscussionUnread = (data) => {
            const total = Number(data.total) || 0;
            discussionBadge.textContent = total > 99 ? '99+' : String(total);
            discussionBadge.dataset.unreadCount = String(total);
            discussionBadge.classList.toggle('hidden', total === 0);
            discussionBadge.setAttribute('aria-label', `${total} pesan diskusi belum dibaca`);

            document.querySelectorAll('[data-discussion-room-unread]').forEach((badge) => {
                const count = Number(data.rooms?.[badge.dataset.discussionRoomUnread]) || 0;
                badge.textContent = `${count > 99 ? '99+' : count} baru`;
                badge.classList.toggle('hidden', count === 0);
            });
        };
        const refreshDiscussionUnread = async () => {
            if (document.hidden) return;
            try {
                const response = await fetch(discussionBadge.dataset.unreadUrl, {
                    credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store',
                });
                if (response.ok) renderDiscussionUnread(await response.json());
            } catch (_) {}
        };

        window.setInterval(refreshDiscussionUnread, 30000);
        document.addEventListener('visibilitychange', refreshDiscussionUnread);
    }
});
