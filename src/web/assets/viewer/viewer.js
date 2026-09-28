/**
 * The preview workspace (§6 Screens, BR-37 to BR-40): settings and examples update the preview in
 * place, the preview shows a desktop, or a tablet or phone at real size, turned either way, and
 * the address keeps the whole view, device included, so a copied link reopens it (AC-2, AC-15).
 *
 * Plain ES module with no build step and no CP globals: the share page has no CP script, so this
 * also drives the details drawer and its tabs, and the tree toggle, in both viewers. The server gives the state
 * in data-cl-viewer: the component, the current story, each story's file values and each prop's
 * type, the settings changed so far (`overrides`), the device and orientation, and the preview
 * URL, which carries a token with no usage limit, so only its `story` and `props` change here.
 * The device never reaches the preview URL (BR-39).
 */

const DEBOUNCE_MS = 250;

/** Below this the tree stacks (BR-37), and the drawer's height and the tree's remembered state don't apply. */
const NARROW = window.matchMedia('(max-width: 767.98px)');

/** BR-40: an open drawer's default share of the height, its minimum, and the preview's. */
const DEFAULT_SHARE = 0.4;
const MIN_DRAWER = 120;
const MIN_PREVIEW = 200;
const KEY_STEP = 16;
const KEY_STEP_LARGE = 64;

const STORAGE = {
    treeHidden: 'cl.treeHidden',
    drawerOpen: 'cl.drawerOpen',
    drawerHeight: 'cl.drawerHeight',
};

/** Craft's device sizes (Preview.js): the screen, and the bezel around it. */
const DEVICES = {
    tablet: {screen: [768, 1024], mask: [768, 1110]},
    phone: {screen: [375, 667], mask: [375, 753]},
};

/** The screen sits this far above the bezel's centre. */
const SCREEN_SHIFT = 12;

/** BR-40: browser storage may be blocked or hold anything, and the page works without it. */
const store = {
    get(key) {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    set(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch {
            // Not remembered, then.
        }
    },
};

const rtl = () => document.documentElement.dir === 'rtl' || document.body.classList.contains('rtl');

function initSearch() {
    const input = document.querySelector('[data-cl-search]');
    if (!input) {
        return;
    }

    const status = document.querySelector('[data-cl-search-status]');
    const noMatch = document.querySelector('[data-cl-no-match]');
    const links = [...document.querySelectorAll('[data-cl-component]')];

    input.addEventListener('input', () => {
        const words = input.value.toLowerCase().split(/\s+/).filter(Boolean);
        let shown = 0;

        for (const link of links) {
            const match = words.every((word) => link.dataset.clSearchText.includes(word));
            link.closest('li').hidden = !match;
            shown += match ? 1 : 0;
        }

        // A category heading hides when none of the items after it are shown.
        for (const heading of document.querySelectorAll('[data-cl-category]')) {
            let item = heading.nextElementSibling;
            let any = false;
            while (item && !item.hasAttribute('data-cl-category')) {
                any ||= !item.hidden;
                item = item.nextElementSibling;
            }
            heading.hidden = !any;
        }

        noMatch.classList.toggle('hidden', shown > 0);
        status.textContent = words.length ? `${shown} of ${links.length}` : '';
    });
}


/**
 * The tree toggle. On a wide screen the tree shows unless hidden, and that's remembered. On a
 * narrow one it starts behind the toggle, and nothing is remembered (BR-37, BR-40).
 */
function initTree(workspace) {
    const toggle = workspace.querySelector('[data-cl-tree-toggle]');
    if (!toggle) {
        return;
    }

    const label = toggle.querySelector('[data-cl-tree-toggle-label]');
    let hidden = store.get(STORAGE.treeHidden) === '1';
    let open = false;

    function apply() {
        const shown = NARROW.matches ? open : !hidden;
        const text = shown ? toggle.dataset.hideLabel : toggle.dataset.showLabel;
        workspace.classList.toggle('cl-tree-hidden', !shown);
        toggle.setAttribute('aria-expanded', shown ? 'true' : 'false');
        toggle.title = text;
        label.textContent = text;
    }

    toggle.addEventListener('click', () => {
        if (NARROW.matches) {
            open = !open;
        } else {
            hidden = !hidden;
            store.set(STORAGE.treeHidden, hidden ? '1' : '0');
        }
        apply();
    });

    NARROW.addEventListener('change', apply);
    apply();
}

/**
 * The details drawer below the preview, as in v1 (BR-37, BR-40). Its row of tabs stays in view: a
 * tab opens the drawer on that tab, the open tab closes it, and the toggle does either (click,
 * arrow keys, Home and End, which Craft's CP script never sees). Open, the divider on top of it
 * sets its height: drag it, or focus it and use the arrow keys (Shift for bigger steps), Home and
 * End. A double-click resets it. Open or closed, and the height as a share of the page, are
 * remembered.
 */
function initDrawer(root) {
    const drawer = root.querySelector('[data-cl-drawer]');
    const bar = drawer.querySelector('.cl-drawer-bar');
    const content = drawer.querySelector('#cl-drawer-content');
    const toggle = drawer.querySelector('[data-cl-drawer-toggle]');
    const toggleLabel = toggle.querySelector('[data-cl-drawer-toggle-label]');
    const toolbar = root.querySelector('.cl-preview-toolbar');
    const divider = root.querySelector('[data-cl-divider]');
    const tabs = [...drawer.querySelectorAll('#cl-tabs [role=tab]')];
    const stored = Number.parseFloat(store.get(STORAGE.drawerHeight) ?? '');
    let share = stored > 0 && stored < 1 ? stored : DEFAULT_SHARE;
    let open = store.get(STORAGE.drawerOpen) === '1';

    /** [min, max, total] in px for the drawer's content. When both minimums don't fit, they halve it. */
    function bounds() {
        const total = root.clientHeight;
        const room = total - bar.offsetHeight - (divider.offsetHeight || 5) - toolbar.offsetHeight;
        const max = room - MIN_PREVIEW;

        return max < MIN_DRAWER ? [room / 2, room / 2, total] : [MIN_DRAWER, max, total];
    }

    function apply() {
        if (NARROW.matches || !open) {
            root.style.removeProperty('--cl-drawer-height');
            return;
        }
        const [min, max, total] = bounds();
        const px = Math.round(Math.min(max, Math.max(min, share * total)));
        root.style.setProperty('--cl-drawer-height', `${px}px`);
        divider.setAttribute('aria-valuenow', String(total > 0 ? Math.round((px / total) * 100) : 0));
    }

    function resize(px, remember = true) {
        const [min, max, total] = bounds();
        if (total <= 0) {
            return;
        }
        share = Math.min(max, Math.max(min, px)) / total;
        if (remember) {
            store.set(STORAGE.drawerHeight, share.toFixed(4));
        }
        apply();
    }

    function setOpen(next) {
        open = next;
        store.set(STORAGE.drawerOpen, open ? '1' : '0');
        const text = open ? toggle.dataset.hideLabel : toggle.dataset.showLabel;
        drawer.classList.toggle('cl-drawer-closed', !open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.title = text;
        toggleLabel.textContent = text;
        apply();
    }

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
            if (open && tab.classList.contains('sel')) {
                setOpen(false);
                return;
            }
            select(tab);
            setOpen(true);
        });

        tab.addEventListener('keydown', (event) => {
            const next = {
                ArrowRight: index + (rtl() ? -1 : 1),
                ArrowLeft: index + (rtl() ? 1 : -1),
                Home: 0,
                End: tabs.length - 1,
            }[event.key];
            if (next === undefined) {
                return;
            }
            event.preventDefault();
            const target = tabs[(next + tabs.length) % tabs.length];
            select(target);
            setOpen(true);
            target.focus();
        });
    });

    toggle.addEventListener('click', () => setOpen(!open));

    divider.addEventListener('pointerdown', (event) => {
        if (event.button !== 0) {
            return;
        }
        event.preventDefault();
        divider.setPointerCapture(event.pointerId);
        const startY = event.clientY;
        const startHeight = content.offsetHeight;
        document.body.classList.add('cl-dragging');

        // Dragging up grows the drawer.
        const move = (moved) => resize(startHeight - (moved.clientY - startY), false);
        const end = () => {
            divider.removeEventListener('pointermove', move);
            divider.removeEventListener('pointerup', end);
            divider.removeEventListener('pointercancel', end);
            document.body.classList.remove('cl-dragging');
            resize(content.offsetHeight);
        };
        divider.addEventListener('pointermove', move);
        divider.addEventListener('pointerup', end);
        divider.addEventListener('pointercancel', end);
    });

    divider.addEventListener('keydown', (event) => {
        const step = event.shiftKey ? KEY_STEP_LARGE : KEY_STEP;
        const [min, max] = bounds();
        const height = content.offsetHeight;
        const next = {
            ArrowUp: height + step,
            ArrowDown: height - step,
            Home: min,
            End: max,
        }[event.key];
        if (next === undefined) {
            return;
        }
        event.preventDefault();
        resize(next);
    });

    divider.addEventListener('dblclick', () => {
        share = DEFAULT_SHARE;
        store.set(STORAGE.drawerHeight, share.toFixed(4));
        apply();
    });

    NARROW.addEventListener('change', apply);
    new ResizeObserver(apply).observe(root);
    setOpen(open);
}

function initViewer(root) {
    const config = JSON.parse(root.dataset.clViewer);
    const labels = JSON.parse(root.dataset.clLabels);
    const frame = root.querySelector('[data-cl-preview]');
    const fields = [...root.querySelectorAll('[data-cl-prop]')];
    const storyButtons = [...root.querySelectorAll('[data-cl-story]')];
    const deviceButtons = [...root.querySelectorAll('[data-cl-device]')];
    const rotate = root.querySelector('[data-cl-rotate]');
    const open = root.querySelector('[data-cl-open]');
    const stage = root.querySelector('[data-cl-stage]');
    const box = stage.querySelector('[data-cl-device-box]');
    const inner = stage.querySelector('.cl-device-inner');
    const mask = stage.querySelector('.cl-device-mask');
    const screen = stage.querySelector('.cl-device-screen');
    const site = {
        story: root.querySelector('[data-cl-site-story]'),
        props: root.querySelector('[data-cl-site-props]'),
        device: root.querySelector('[data-cl-site-device]'),
        orientation: root.querySelector('[data-cl-site-orientation]'),
    };
    let story = config.story;
    let overrides = {...config.overrides};
    let device = config.device;
    let orientation = config.orientation;
    let timer = null;

    const same = (a, b) => JSON.stringify(a ?? null) === JSON.stringify(b ?? null);
    const input = (field) => field.querySelector('input:not([type=hidden]), select, textarea');

    /** A control's value as its prop's type, or `undefined` when it holds no usable value. */
    function read(field) {
        const control = input(field);
        switch (field.dataset.clType) {
            case 'bool':
                return control.checked;
            case 'number':
                return control.value === '' || Number.isNaN(Number(control.value)) ? undefined : Number(control.value);
            case 'select':
                return control.value === '' ? undefined : control.value;
            case 'json':
                if (control.value.trim() === '') {
                    return undefined;
                }
                try {
                    return JSON.parse(control.value);
                } catch {
                    return undefined;
                }
            default:
                return control.value;
        }
    }

    function write(field, value) {
        const control = input(field);
        switch (field.dataset.clType) {
            case 'bool':
                control.checked = value === true || value === 'true' || value === 1 || value === '1';
                break;
            case 'json':
                control.value = value == null ? '' : JSON.stringify(value, null, 4);
                break;
            default:
                control.value = value == null ? '' : String(value);
        }
        field.classList.remove('has-errors');
    }

    function values() {
        return {...config.stories[story], ...overrides};
    }

    /** The preview's address: the story and settings only, never the device (BR-39). */
    function previewUrl() {
        const props = Object.keys(overrides).length ? JSON.stringify(overrides) : null;
        const preview = new URL(config.previewUrl);
        preview.searchParams.set('story', story);
        props ? preview.searchParams.set('props', props) : preview.searchParams.delete('props');

        return preview.href;
    }

    /** `device` and `orientation` on a page address, none for desktop, as the server reads them. */
    function withDevice(href) {
        const url = new URL(href, window.location.href);
        for (const [name, value] of Object.entries({device, orientation})) {
            device === 'desktop' ? url.searchParams.delete(name) : url.searchParams.set(name, value);
        }

        return url.href;
    }

    function update() {
        const hasProps = Object.keys(overrides).length > 0;
        const props = hasProps ? JSON.stringify(overrides) : null;
        const preview = previewUrl();

        if (frame.src !== preview) {
            frame.src = preview;
        }
        open.href = preview;

        const deviceLabel = orientation === 'landscape'
            ? labels.landscape.replace('{device}', labels.devices[device])
            : labels.devices[device];
        frame.title = labels.frameTitle
            .replace('{component}', config.name)
            .replace('{story}', story)
            .replace('{device}', deviceLabel);

        const address = new URL(withDevice(window.location.href));
        address.searchParams.set('story', story);
        props ? address.searchParams.set('props', props) : address.searchParams.delete('props');
        window.history.replaceState(null, '', address.href);

        // The tree keeps the device, and the site switch keeps the whole view.
        for (const link of document.querySelectorAll('a[data-cl-component]')) {
            link.href = withDevice(link.href);
        }
        if (site.story) {
            site.story.value = story;
            site.props.value = props ?? '';
            site.props.disabled = !hasProps;
            site.device.value = device;
            site.orientation.value = orientation;
            site.device.disabled = site.orientation.disabled = device === 'desktop';
        }
    }

    /**
     * BR-38: Desktop fills the stage. Tablet and phone always render at their real size, 100%,
     * in the bezel, landscape swapping the two sizes. A device bigger than the stage scrolls
     * inside it rather than shrinking.
     */
    function fit() {
        stage.dataset.device = device;
        stage.dataset.orientation = orientation;

        if (device === 'desktop') {
            for (const element of [box, inner, mask, screen]) {
                element.removeAttribute('style');
            }
            return;
        }

        const {screen: [screenW, screenH], mask: [maskW, maskH]} = DEVICES[device];
        const turned = orientation === 'landscape';
        const [outerW, outerH] = turned ? [maskH, maskW] : [maskW, maskH];

        box.style.width = `${outerW}px`;
        box.style.height = `${outerH}px`;
        Object.assign(inner.style, {width: `${outerW}px`, height: `${outerH}px`});
        Object.assign(mask.style, {
            width: `${maskW}px`,
            height: `${maskH}px`,
            transform: `translate(-50%, -50%) rotate(${turned ? -90 : 0}deg)`,
        });
        Object.assign(screen.style, {
            width: `${turned ? screenH : screenW}px`,
            height: `${turned ? screenW : screenH}px`,
            transform: turned
                ? `translate(calc(-50% - ${SCREEN_SHIFT}px), -50%)`
                : `translate(-50%, calc(-50% - ${SCREEN_SHIFT}px))`,
        });
    }

    function setDevice(next) {
        device = next;
        // Desktop is never turned (BR-39).
        if (device === 'desktop') {
            orientation = 'portrait';
        }
        for (const button of deviceButtons) {
            const on = button.dataset.clDevice === device;
            button.setAttribute('aria-pressed', on ? 'true' : 'false');
            button.tabIndex = on ? 0 : -1;
        }
        rotate.disabled = device === 'desktop';
        rotate.setAttribute('aria-pressed', orientation === 'landscape' ? 'true' : 'false');
        fit();
        update();
    }

    function changed(field) {
        const name = field.dataset.clProp;
        const value = read(field);

        // An unreadable JSON value is marked and left out, so the preview keeps its last good one.
        const invalid = field.dataset.clType === 'json' && value === undefined && input(field).value.trim() !== '';
        field.classList.toggle('has-errors', invalid);
        if (invalid) {
            return;
        }

        if (value === undefined || same(value, config.stories[story]?.[name])) {
            delete overrides[name];
        } else {
            overrides[name] = value;
        }
        update();
    }

    for (const field of fields) {
        const control = input(field);
        const now = () => changed(field);
        control.addEventListener('change', now);
        if (control.matches('textarea, input[type=text], input[type=number]')) {
            control.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(now, DEBOUNCE_MS);
            });
        }
    }

    for (const button of storyButtons) {
        button.addEventListener('click', () => {
            story = button.dataset.clStory;
            for (const other of storyButtons) {
                other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
            }
            // A setting changed by hand is kept across examples, as the address keeps it.
            const current = values();
            for (const field of fields) {
                write(field, current[field.dataset.clProp]);
            }
            update();
        });
    }

    root.querySelector('[data-cl-reset]')?.addEventListener('click', () => {
        overrides = {};
        for (const field of fields) {
            write(field, config.stories[story][field.dataset.clProp]);
        }
        update();
    });

    // BR-34: one group, the arrow keys moving between devices and choosing as they go.
    deviceButtons.forEach((button, index) => {
        button.addEventListener('click', () => setDevice(button.dataset.clDevice));
        button.addEventListener('keydown', (event) => {
            const forward = rtl() ? -1 : 1;
            const next = {
                ArrowRight: index + forward,
                ArrowDown: index + 1,
                ArrowLeft: index - forward,
                ArrowUp: index - 1,
                Home: 0,
                End: deviceButtons.length - 1,
            }[event.key];
            if (next === undefined) {
                return;
            }
            event.preventDefault();
            const target = deviceButtons[(next + deviceButtons.length) % deviceButtons.length];
            setDevice(target.dataset.clDevice);
            target.focus();
        });
    });

    rotate.addEventListener('click', () => {
        if (device !== 'desktop') {
            orientation = orientation === 'landscape' ? 'portrait' : 'landscape';
            setDevice(device);
        }
    });

    root.querySelector('[data-cl-refresh]').addEventListener('click', () => {
        frame.src = previewUrl();
    });

    // The site switches as soon as it's chosen. The button is for a page without this script.
    const siteForm = root.querySelector('[data-cl-site-form]');
    if (siteForm) {
        siteForm.querySelector('[data-cl-site-submit]').classList.add('hidden');
        siteForm.querySelector('select').addEventListener('change', () => siteForm.requestSubmit());
    }

    initDrawer(root);
    fit();
    // The server has already settled the device, so an unusable one leaves the address too.
    window.history.replaceState(null, '', withDevice(window.location.href));
}

initSearch();
document.querySelectorAll('[data-cl-workspace]').forEach(initTree);
document.querySelectorAll('[data-cl-viewer]').forEach(initViewer);
