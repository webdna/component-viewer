/**
 * The component viewer (§6 Screens): settings and examples update the preview in place, and the
 * address keeps the whole view, so a copied link reopens it (AC-2).
 *
 * Plain ES module with no build step and no CP globals, so the share viewer can use it as well.
 * The server gives the state in data-cl-viewer: the component, the current story, each story's
 * file values and each prop's type, the settings changed so far (`overrides`) and the preview URL,
 * which carries a token with no usage limit, so only its `story` and `props` change here.
 */

const DEBOUNCE_MS = 250;

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

function initViewer(root) {
    const config = JSON.parse(root.dataset.clViewer);
    const frame = root.querySelector('[data-cl-preview]');
    const fields = [...root.querySelectorAll('[data-cl-prop]')];
    const storyButtons = [...root.querySelectorAll('[data-cl-story]')];
    const siteStory = root.querySelector('[data-cl-site-story]');
    const siteProps = root.querySelector('[data-cl-site-props]');
    let story = config.story;
    let overrides = {...config.overrides};
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

    function update() {
        const hasProps = Object.keys(overrides).length > 0;
        const props = hasProps ? JSON.stringify(overrides) : null;

        const preview = new URL(config.previewUrl);
        preview.searchParams.set('story', story);
        props ? preview.searchParams.set('props', props) : preview.searchParams.delete('props');
        if (frame.src !== preview.href) {
            frame.src = preview.href;
        }
        frame.title = root.dataset.clFrameTitle.replace('{component}', config.name).replace('{story}', story);

        const address = new URL(window.location.href);
        address.searchParams.set('story', story);
        props ? address.searchParams.set('props', props) : address.searchParams.delete('props');
        window.history.replaceState(null, '', address.href);

        if (siteStory) {
            siteStory.value = story;
            siteProps.value = props ?? '';
            siteProps.disabled = !hasProps;
        }
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

    root.querySelector('[data-cl-width]')?.addEventListener('change', (event) => {
        const width = event.target.value;
        frame.style.width = width ? `${width}px` : '';
    });
}

initSearch();
document.querySelectorAll('[data-cl-viewer]').forEach(initViewer);
