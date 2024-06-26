const leftPanel = {
    selection: {
        id: null,
        variant: null,
        clear: () => {
            const buttons = document.querySelectorAll('button[data-component="left-panel-btn"]');
            for (let i = 0; i < buttons.length; i++) {
                buttons[i].classList.remove('selected');
            }
            leftPanel.selection.id = null;
        },
        update: (el) => {
            leftPanel.selection.clear();
            leftPanel.selection.id = el.dataset.componentId;
            el.classList.add('selected');
        },
    },
    sites: {
        selected: null,
        init: () => {
            const sites = document.getElementById('sites');
            leftPanel.sites.selected = sites.value;
            sites.addEventListener('change', function(e){
                document.location = `?site=${sites.value}&componentId=${leftPanel.selection.id}`;
            })
        }
    },
    details: {
        open: () => {
            const details = document.querySelectorAll('details');
            for (let i = 0; i < details.length; i++) {
                details[i].open = true;
            }
        },
        close: () => {
            const details = document.querySelectorAll('details');
            for (let i = 0; i < details.length; i++) {
                details[i].open = false;
            }
        },
        init: () => {
            leftPanel.details.close();
            const details = document.querySelectorAll('.panel-left > details');
            for (let i = 0; i < details.length; i++) {
                //details[i].open = true;
            }
        },
        openAncestors: (el) => {
            let parent = el.parentNode;
            while (parent.tagName === 'DETAILS' || parent.classList.contains('component-variants')) {
                parent.open = true;
                parent = parent.parentNode;
            }
        },
    },
    tree: {
        buttons: {
            handleClick: (ev) => {
                leftPanel.selection.update(ev.target);
                rightPanel.top.iframe.update.src();
                window.history.pushState(
                    {
                        componentId: leftPanel.selection.id,
                        site: leftPanel.sites.selected,
                    },
                    '',
                    `?site=${leftPanel.sites.selected}&componentId=${leftPanel.selection.id}`,
                );
                //leftPanel.details.openAncestors(ev.target);
                rightPanel.bottom.sections.update();
            },
            init: () => {
                const buttons = document.querySelectorAll('button[data-component="left-panel-btn"]');
                for (let i = 0; i < buttons.length; i++) {
                    buttons[i].addEventListener('click', leftPanel.tree.buttons.handleClick);
                }
            },
        },
    },
    search: {
        init: () => {
            const search = document.getElementById('Search');
            search.closest('form').addEventListener('submit', function(e) {
                e.preventDefault();
                leftPanel.search.execute(search);
            });
            search.addEventListener('keyup', function(e) {
                leftPanel.search.execute(search);
            });

            search.addEventListener('blur', function() {
                console.log('search.value.length', search.value.length);
                if (!search.value.length) {
                    leftPanel.search.clear();
                }
            });
        },
        togglePanelButtons: (searchValue = '') => {
            const buttons = document.querySelectorAll('button[data-component="left-panel-btn"]');
            for (let i = 0; i < buttons.length; i++) {
                buttons[i].classList.toggle('hidden');
                if (searchValue.length) {
                    if (buttons[i].dataset.searchData.indexOf(searchValue) > -1) {
                        buttons[i].classList.remove('hidden');
                    } else {
                        buttons[i].classList.add('hidden');
                    }
                } else {
                    buttons[i].classList.remove('hidden');
                }
            }
        },
        toggleHighlights: (searchValue = '') => {
            const items = document.querySelectorAll('button[data-component="left-panel-btn"], summary');
            for (let i = 0; i < items.length; i++) {
                const item = items[i];
                const text = item.innerText.toLowerCase();
                const index = text.indexOf(searchValue);
                if (index > -1 && searchValue !== '') {
                    const start = text.substr(0, index);
                    const highlighted = text.substr(index, searchValue.length);
                    const end = text.substr(index + searchValue.length);
                    //item.innerHTML = `${start}<span class="highlight">${highlighted}</span>${end}`;
                } else {
                    //item.innerHTML = item.dataset.originalText;
                }
            }
        },
        toggleSections: () => {
            const sections = document.querySelectorAll('details');
            for (let i = 0; i < sections.length; i++) {
                const section = sections[i];
                const components = section.querySelectorAll('button[data-component="left-panel-btn"]');
                let visible = false;
                for (let j = 0; j < components.length; j++) {
                    if (!components[j].classList.contains('hidden')) {
                        visible = true;
                    }
                }
                if (visible) {
                    section.classList.remove('hidden');
                } else {
                    section.classList.add('hidden');
                }
            }
        },
        clear: () => {
            leftPanel.search.toggleSections();
            leftPanel.init();

        },
        execute: (search) => {
            leftPanel.details.open();
            const searchValue = search.value.toLowerCase();
            if (searchValue.length === 0) {
                return leftPanel.search.clear();
            }
            leftPanel.search.togglePanelButtons(searchValue);
            leftPanel.search.toggleHighlights(searchValue);
            leftPanel.search.toggleSections();
        },
    },
    init: () => {
        leftPanel.sites.init();
        leftPanel.details.init();
        leftPanel.search.togglePanelButtons();
        leftPanel.search.toggleHighlights();
    },
};


const rightPanel = {
    top: {
        showOverview: () => {
            document.querySelector('.panel-top #IframeWrapper').classList.add('hidden');
            document.querySelector('.panel-bottom').classList.add('hidden');
        },
        iframe: {
            element: document.querySelector('iframe'),
            update: {
                src: () => {
                    const iframeSrc = `https://${config.domain}/component-library/render/?site=${leftPanel.sites.selected}&componentId=${leftPanel.selection.id}&key=${window.renderKey}`;
                    rightPanel.top.iframe.element.src = iframeSrc;
                    //document.querySelector('[data-component="iframe-link"]').href = iframeSrc;
                    rightPanel.top.iframe.element.parentElement.classList.remove('hidden');
                    //resizing.reportIframeSize();
                },
            },
        },
    },
    bottom: {
        init: () => {
            const bottomPanelButtons = document.querySelectorAll('div[id=bottomPanel] nav button');
            for (let i = 0; i < bottomPanelButtons.length; i++) {
                bottomPanelButtons[i].addEventListener('click', function() {
                    const wasActive = this.classList.contains('selected');
                    rightPanel.bottom.sections.hideAll();
                    if (wasActive === false) {
                        this.classList.add('selected');
                        const section = document.querySelector(`div[id=bottomPanel] section.component-${this.dataset.id}`);
                        section.classList.remove('hidden');
                    }
                });
            }

        },
        sections: {
            hideAll: () => {
                const bottomPanelButtons = document.querySelectorAll('div[id=bottomPanel] nav button');
                for (let i = 0; i < bottomPanelButtons.length; i++) {
                    bottomPanelButtons[i].classList.remove('selected');
                }
                
                const sections = document.querySelectorAll('div[id=bottomPanel] section');
                for (let i = 0; i < sections.length; i++) {
                    sections[i].classList.add('hidden');
                }
            },
            update: () => {
                fetch(`https://${config.domain}/actions/component-library/component-viewer/get-component-info?site=${leftPanel.sites.selected}&componentId=${leftPanel.selection.id}`)
                    .then(response => response.json())
                    .then(data => {
                        document.querySelector('.component-notes .computed').innerHTML = data.readme;
                        document.querySelector('.component-vars .computed').innerHTML = data.vars;
                        document.querySelector('.component-html .computed').innerHTML = data.rendered.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        document.querySelector('.component-twig .computed').innerHTML = data.twig.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        document.querySelector('.component-context .computed').innerHTML = JSON.stringify(data.context, null, 2);
                        document.querySelector('.component-info .computed').innerHTML = data.info;
                        //document.querySelector('[data-component="iframe-title"]').innerHTML = data.config.title;
                        //document.querySelector('[data-component="iframe-status"]').innerHTML = config.statuses[data.config.status].label;
                        //document.querySelector('[data-component="iframe-status"]').style.backgroundColor = config.statuses[data.config.status].color;
                        //document.querySelector('[data-component="iframe-status"]').title = config.statuses[data.config.status].description;
                        window.hljs.highlightAll();
                        //document.querySelector('.panel-bottom').classList.remove('hidden');
                    });
            },
            init: () => {
                
            },

        },

    },
};

const resizing = {
    // The current position of mouse
    x: 0,
    y: 0,
    // The dimension of the element
    w: 0,
    h: 0,
    element: document.getElementById('Resizable'),
    direction: null,

    init: () => {
        [].forEach.call(resizing.element.querySelectorAll('.resizer'), function(resizer) {
            resizer.addEventListener('mousedown', resizing.mouseDownHandler);
        });
    },

    mouseDownHandler: function(e) {
        // Get the current mouse position
        resizing.x = e.clientX;
        resizing.y = e.clientY;

        // Calculate the dimension of element
        const styles = window.getComputedStyle(resizing.element);
        resizing.w = parseInt(styles.width, 10);
        resizing.h = parseInt(styles.height, 10);
        resizing.direction = e.target.dataset.direction;
        // Attach the listeners to `document`
        document.addEventListener('mousemove', resizing.mouseMoveHandler);
        document.addEventListener('mouseup', resizing.mouseUpHandler);
    },
    mouseMoveHandler: function(e) {
        // How far the mouse has been moved
        const dx = e.clientX - resizing.x;
        const dy = e.clientY - resizing.y;

        // Adjust the dimension of element
        if (resizing.direction === 'vertical') {
            resizing.element.style.height = `${resizing.h + dy}px`;
            // set the height of the bottom panel to always be the height of the page minus the height of the top panel and nav bar
            resizing.setBottomPanelHeight();
            resizing.reportIframeSize();
        } else if (resizing.direction === 'horizontal') {
            resizing.element.style.width = `${resizing.w + dx}px`;
            resizing.reportIframeSize();
        }
    },
    mouseUpHandler: function() {
        // Remove the handlers of `mousemove` and `mouseup`
        document.removeEventListener('mousemove', resizing.mouseMoveHandler);
        document.removeEventListener('mouseup', resizing.mouseUpHandler);
    },
    setTopPanelHeight: () => {
        document.querySelector('.panel-top').style.maxWidth = `${document.querySelector('.panel-top').getBoundingClientRect().width}px`;
    },
    setBottomPanelHeight: () => {
        document.querySelector('.panel-bottom').style.height = `${window.innerHeight - document.querySelector('.panel-top').offsetHeight - document.querySelector('nav').offsetHeight - 30}px`;
        document.querySelector('#IframeWrapper iframe').style.height = `${document.getElementById('IframeWrapper').getBoundingClientRect().height - document.getElementById('IframeHeader').getBoundingClientRect().height - document.querySelector('.resizer-h').getBoundingClientRect().height}px`;
    },
    reportIframeSize: () => {
        const iframeSize = document.querySelector('.panel-top iframe').getBoundingClientRect();
        document.querySelector('[data-component="iframe-size"]').innerHTML = `${Math.round(iframeSize.width)} x ${Math.round(iframeSize.height)}`;
    },
    handleWindowResize: () => {
        document.querySelector('.panel-top').style.maxWidth = `${document.querySelector('.panel-right').getBoundingClientRect().width - 25}px`;
        resizing.reportIframeSize();
    },
};



const config = {
    domain: window.location.hostname,
    statuses: {
        reference: {
            label: 'Reference',
            description: 'For reference',
            color: '#333333',
        },
        awaitingApproval: {
            label: 'Awaiting Approval',
            description:
                'Do not implement, not yet approved for development/integration',
            color: '#ff0000',
        },
        prototype: {
            label: 'Prototype',
            description:
                'Do not implement, component is a prototype, for either design testing, UI testing or code testing',
            color: '#cc0000',
        },
        placeholder: {
            label: 'Placeholder',
            description: 'Initial skeleton of future component',
            color: '#990099',
        },
        wip: {
            label: 'In progress',
            description: 'Work in progress, implement with caution',
            color: '#ff7200',
        },
        qa: {
            label: 'Design QA',
            description: 'In design QA',
            color: '#00008b',
        },
        ready: {
            label: 'Ready',
            description: 'Ready to implement',
            color: '#32cd32',
        },
        updated: {
            label: 'Updated',
            description: 'Updates made, ready to implement',
            color: '#ff00f0',
        },
        implemented: {
            label: 'Implemented',
            description: 'Implemented into target environments',
            color: '#006400',
        },
    },
};

const init = () => {

    // init page elements
    leftPanel.tree.buttons.init();
    leftPanel.sites.init();
    //resizing.setBottomPanelHeight();
    //resizing.setTopPanelHeight();
    leftPanel.search.init();
    rightPanel.bottom.init();
    rightPanel.bottom.sections.init();
    //resizing.init();

    // event listeners
    //document.getElementById('OverviewBtn').addEventListener('click', rightPanel.top.showOverview);
    //window.addEventListener('resize', resizing.handleWindowResize);


    // check for componentId and variant in URL
    const urlParams = new URLSearchParams(window.location.search);
    const componentId = urlParams.get('componentId');
    if (componentId) {
        const button = document.querySelector(`button[data-component-id="${componentId}"]`);
        if (button) {
            button.click();
        }
    } else {
        leftPanel.init();
        //rightPanel.top.showOverview();
    }

};

init();

