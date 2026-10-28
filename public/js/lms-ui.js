document.addEventListener('DOMContentLoaded', () => {
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
