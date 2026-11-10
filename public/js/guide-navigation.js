document.addEventListener('DOMContentLoaded', () => {
    const navigation = document.querySelector('[data-guide-toc]');
    if (!navigation) return;

    const links = [...navigation.querySelectorAll('[data-guide-link]')];
    const entries = links.map((link) => ({
        link,
        section: document.querySelector(link.getAttribute('href')),
    })).filter((entry) => entry.section);

    if (!entries.length) return;

    let clickLockUntil = 0;
    let scrollFrame = null;

    const setActive = (id, centerLink = false) => {
        const activeEntry = entries.find((entry) => entry.section.id === id) ?? entries[0];

        entries.forEach(({link, section}) => {
            const active = section === activeEntry.section;
            link.classList.toggle('is-active', active);
            if (active) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });

        if (centerLink && navigation.scrollWidth > navigation.clientWidth) {
            const left = activeEntry.link.offsetLeft - ((navigation.clientWidth - activeEntry.link.offsetWidth) / 2);
            navigation.scrollTo({left: Math.max(0, left), behavior: 'smooth'});
        }
    };

    const updateFromScroll = () => {
        scrollFrame = null;
        if (performance.now() < clickLockUntil) return;

        const marker = navigation.getBoundingClientRect().bottom + 24;
        let activeEntry = entries[0];

        for (const entry of entries) {
            if (entry.section.getBoundingClientRect().top <= marker) activeEntry = entry;
            else break;
        }

        if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 8) {
            activeEntry = entries[entries.length - 1];
        }

        setActive(activeEntry.section.id, true);
    };

    links.forEach((link) => {
        link.addEventListener('click', () => {
            clickLockUntil = performance.now() + 750;
            setActive(link.getAttribute('href').slice(1), true);
        });
    });

    window.addEventListener('hashchange', () => {
        const id = window.location.hash.slice(1);
        if (id) setActive(id, true);
    });

    window.addEventListener('scroll', () => {
        if (scrollFrame === null) scrollFrame = requestAnimationFrame(updateFromScroll);
    }, {passive: true});

    const initialId = window.location.hash.slice(1);
    if (initialId && entries.some((entry) => entry.section.id === initialId)) setActive(initialId, true);
    else updateFromScroll();
});
