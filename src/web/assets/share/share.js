/**
 * The share viewer's page chrome (§6 Screens): what Craft's CP script does for the CP viewer's
 * layout, without the CP script. The tabs, with arrow-key movement between them, and the sidebar
 * toggle a narrow screen shows. viewer.js does everything else, as it does in the CP.
 */

function initTabs() {
    const tabs = [...document.querySelectorAll('#tabs [role=tab]')];

    function select(tab) {
        for (const other of tabs) {
            const on = other === tab;
            other.classList.toggle('sel', on);
            other.setAttribute('aria-selected', on ? 'true' : 'false');
            other.tabIndex = on ? 0 : -1;
            document.getElementById(other.dataset.id)?.classList.toggle('hidden', !on);
        }
    }

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', (event) => {
            event.preventDefault();
            select(tab);
        });

        tab.addEventListener('keydown', (event) => {
            const rtl = document.body.classList.contains('rtl');
            const next = {
                ArrowRight: index + (rtl ? -1 : 1),
                ArrowLeft: index + (rtl ? 1 : -1),
                Home: 0,
                End: tabs.length - 1,
            }[event.key];
            if (next === undefined) {
                return;
            }
            event.preventDefault();
            const target = tabs[(next + tabs.length) % tabs.length];
            select(target);
            target.focus();
        });
    });
}

function initSidebarToggle() {
    const toggle = document.getElementById('sidebar-toggle');
    if (!toggle) {
        return;
    }

    toggle.addEventListener('click', () => {
        const showing = document.body.classList.toggle('showing-sidebar');
        toggle.setAttribute('aria-expanded', showing ? 'true' : 'false');
        toggle.textContent = showing ? toggle.dataset.hideLabel : toggle.dataset.showLabel;
    });
}

initTabs();
initSidebarToggle();
