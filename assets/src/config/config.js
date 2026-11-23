/**
 * YumeEditor · settings screen
 *
 * Progressive enhancement for the server-rendered Typecho form:
 *   · moves the native <form> into the MoePanel-styled shell;
 *   · turns on/off selects into switches and enum selects into segmented pills;
 *   · groups the fields into the sidebar sections.
 *
 * Without JavaScript the plain form still renders and saves correctly.
 */
(function () {
    "use strict";

    const STORAGE_KEY = "yume-editor-active-section";

    /**
     * @param {string} selector
     * @param {ParentNode} [scope]
     * @returns {Element|null}
     */
    function $(selector, scope) {
        return (scope || document).querySelector(selector);
    }

    /**
     * @param {string} selector
     * @param {ParentNode} [scope]
     * @returns {Element[]}
     */
    function $$(selector, scope) {
        return Array.from((scope || document).querySelectorAll(selector));
    }

    /**
     * Create an element with optional class / attributes / text.
     *
     * @param {string} tag
     * @param {Object} [options]
     * @returns {HTMLElement}
     */
    function create(tag, options) {
        const element = document.createElement(tag);
        const opts = options || {};
        if (opts.className) {
            element.className = opts.className;
        }
        if (opts.text !== undefined) {
            element.textContent = opts.text;
        }
        Object.keys(opts.attrs || {}).forEach(function (name) {
            element.setAttribute(name, opts.attrs[name]);
        });
        return element;
    }

    /**
     * Dispatch the events Typecho / other plugins may listen for.
     *
     * @param {HTMLElement} input
     */
    function notifyChange(input) {
        input.dispatchEvent(new Event("input", { bubbles: true }));
        input.dispatchEvent(new Event("change", { bubbles: true }));
    }

    /**
     * Upgrade one `.ym-field` into the two-column row used by the design.
     *
     * @param {HTMLElement} field
     */
    function buildField(field) {
        const inner = field.querySelector(":scope > li");
        if (!inner || inner.classList.contains("ym-field__inner")) {
            return;
        }

        inner.classList.add("ym-field__inner");

        const label = inner.querySelector(".typecho-label");
        const description = inner.querySelector(".description");
        const control = inner.querySelector("select, input, textarea");

        const meta = create("div", { className: "ym-field__meta" });
        if (label) {
            const title = create("span", {
                className: "ym-field__label",
                text: label.textContent.trim(),
            });
            meta.appendChild(title);
            label.remove();
        }
        if (description) {
            description.classList.add("ym-field__desc");
            meta.appendChild(description);
        }

        const controlWrap = create("div", { className: "ym-field__control" });
        const kind = field.getAttribute("data-ym-control") || "select";
        const radios = $$('.ym-field input[type="radio"]', field);

        if (kind === "segmented" && radios.length > 1) {
            upgradeSegmented(radios, controlWrap);
        } else if (control) {
            if (kind === "switch") {
                upgradeSwitch(control, controlWrap);
            } else if (kind === "segmented") {
                upgradeSegmented(control, controlWrap);
            } else {
                controlWrap.appendChild(control);
            }
        }

        inner.insertBefore(meta, inner.firstChild);
        inner.appendChild(controlWrap);
    }

    /**
     * Replace an on/off <select> with an accessible switch.
     *
     * @param {HTMLElement} control
     * @param {HTMLElement} wrap
     */
    function upgradeSwitch(control, wrap) {
        if (!(control instanceof HTMLSelectElement) || control.options.length !== 2) {
            wrap.appendChild(control);
            return;
        }

        const options = Array.from(control.options).map(function (option) {
            return { value: option.value, label: option.textContent.trim() };
        });
        const onOption =
            options.find(function (option) {
                return option.value === "on";
            }) || options[1];
        const offOption =
            options.find(function (option) {
                return option.value !== onOption.value;
            }) || options[0];

        control.classList.add("ym-field__hidden");
        control.setAttribute("aria-hidden", "true");
        control.tabIndex = -1;

        const switchButton = create("button", {
            className: "ym-switch",
            attrs: {
                type: "button",
                role: "switch",
                "aria-checked": String(control.value === onOption.value),
            },
        });

        const stateLabel = create("span", {
            className: "ym-switch__label",
            text: control.value === onOption.value ? onOption.label : offOption.label,
        });

        function sync() {
            const isOn = control.value === onOption.value;
            switchButton.setAttribute("aria-checked", String(isOn));
            stateLabel.textContent = isOn ? onOption.label : offOption.label;
        }

        switchButton.addEventListener("click", function () {
            control.value = control.value === onOption.value ? offOption.value : onOption.value;
            sync();
            notifyChange(control);
        });

        control.addEventListener("change", sync);

        wrap.appendChild(control);
        wrap.appendChild(switchButton);
        wrap.appendChild(stateLabel);
    }

    /**
     * Replace an enum <select> or a radio group with segmented pills.
     *
     * @param {HTMLElement} control
     * @param {HTMLElement} wrap
     */
    function upgradeSegmented(control, wrap) {
        const group = create("div", {
            className: "ym-seg",
            attrs: { role: "group" },
        });

        if (control instanceof HTMLSelectElement) {
            const options = Array.from(control.options).map(function (option) {
                return { value: option.value, label: option.textContent.trim() };
            });

            control.classList.add("ym-field__hidden");
            control.setAttribute("aria-hidden", "true");
            control.tabIndex = -1;

            const buttons = options.map(function (option) {
                const button = create("button", {
                    className: "ym-seg__btn",
                    text: option.label,
                    attrs: { type: "button" },
                });
                button.setAttribute("data-value", option.value);
                button.classList.toggle("is-active", control.value === option.value);
                button.setAttribute("aria-pressed", String(control.value === option.value));
                button.addEventListener("click", function () {
                    control.value = option.value;
                    buttons.forEach(function (item) {
                        const active = item === button;
                        item.classList.toggle("is-active", active);
                        item.setAttribute("aria-pressed", String(active));
                    });
                    notifyChange(control);
                });
                group.appendChild(button);
                return button;
            });

            wrap.appendChild(control);
            wrap.appendChild(group);
            return;
        }

        // Radio group: hide the native spans, mirror their labels.
        const radios = Array.isArray(control)
            ? control.filter(function (item) {
                  return item instanceof HTMLInputElement;
              })
            : $$('input[type="radio"]', control.parentNode || control);
        if (!radios.length) {
            if (!Array.isArray(control)) {
                wrap.appendChild(control);
            }
            return;
        }

        const items = radios.map(function (radio) {
            const item = radio.closest("span");
            if (item) {
                item.classList.add("ym-field__hidden");
            }
            const optionLabel =
                (item && item.querySelector("label") && item.querySelector("label").textContent.trim()) ||
                radio.value;
            return { radio: radio, label: optionLabel };
        });

        const buttons = items.map(function (item) {
            const button = create("button", {
                className: "ym-seg__btn",
                text: item.label,
                attrs: { type: "button" },
            });
            button.classList.toggle("is-active", item.radio.checked);
            button.setAttribute("aria-pressed", String(item.radio.checked));
            button.addEventListener("click", function () {
                item.radio.checked = true;
                buttons.forEach(function (other) {
                    const active = other === button;
                    other.classList.toggle("is-active", active);
                    other.setAttribute("aria-pressed", String(active));
                });
                notifyChange(item.radio);
            });
            group.appendChild(button);
            return button;
        });

        wrap.appendChild(group);
    }

    /**
     * Wire the sidebar tabs to the rendered form sections.
     *
     * @param {HTMLElement} config
     * @param {HTMLElement|null} form
     */
    function initNavigation(config, form) {
        const navItems = $$(".ym-nav__item", config);
        const panels = $$(".ym-panel", config);
        const hosts = config.querySelectorAll("#ym-form-host");
        const sections = form
            ? $$("[data-ym-section]", form).filter(function (element) {
                  return element.parentNode === form;
              })
            : [];

        const actions = form ? form.querySelector(".ym-actions") : null;

        function activate(target) {
            navItems.forEach(function (item) {
                const isActive = item.getAttribute("data-target") === target;
                item.classList.toggle("is-active", isActive);
                item.setAttribute("aria-selected", String(isActive));
            });

            panels.forEach(function (panel) {
                panel.classList.toggle("is-active", panel.getAttribute("data-panel") === target);
            });

            sections.forEach(function (section) {
                const sectionName = section.getAttribute("data-ym-section");
                const visible = sectionName === target;
                section.classList.toggle("ym-field__hidden", !visible);
                section.hidden = !visible;
            });

            if (actions) {
                actions.classList.toggle("ym-field__hidden", target === "overview");
            }

            hosts.forEach(function (host) {
                host.classList.toggle("ym-field__hidden", target === "overview");
            });

            try {
                window.sessionStorage.setItem(STORAGE_KEY, target);
            } catch (error) {
                // Private mode / disabled storage: keep the tab in memory only.
            }
        }

        navItems.forEach(function (item) {
            item.addEventListener("click", function () {
                activate(item.getAttribute("data-target"));
            });
        });

        let initial = null;
        try {
            initial = window.sessionStorage.getItem(STORAGE_KEY);
        } catch (error) {
            initial = null;
        }
        if (location.hash) {
            const hashTarget = location.hash.replace(/^#/, "");
            if (navItems.some(function (item) {
                return item.getAttribute("data-target") === hashTarget;
            })) {
                initial = hashTarget;
            }
        }
        if (!navItems.some(function (item) {
            return item.getAttribute("data-target") === initial;
        })) {
            initial = "overview";
        }

        activate(initial);
    }

    /**
     * Move the save row into the sticky action bar.
     *
     * @param {HTMLElement} form
     */
    function buildActionBar(form) {
        const submitRow = form.querySelector(".typecho-option-submit");
        if (!submitRow) {
            return;
        }

        const bar = create("div", { className: "ym-actions" });
        bar.appendChild(
            create("p", {
                className: "ym-actions__hint",
                text: "设置保存后立即生效",
            })
        );

        const button = submitRow.querySelector('button[type="submit"], input[type="submit"]');
        if (button) {
            bar.appendChild(button);
        }
        submitRow.remove();
        form.appendChild(bar);
    }

    /**
     * Ask for confirmation on destructive backup actions.
     *
     * @param {HTMLElement} config
     */
    function initConfirmations(config) {
        $$("[data-confirm]", config).forEach(function (element) {
            const message = element.getAttribute("data-confirm");
            if (!message) {
                return;
            }
            element.addEventListener("click", function (event) {
                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });
    }

    let booted = false;

    function init() {
        if (booted) {
            return;
        }
        booted = true;

        const config = $(".ym-config");
        if (!config) {
            return;
        }

        initConfirmations(config);

        const host = $("#ym-form-host", config);
        const form = config.nextElementSibling;
        if (host && form && form.tagName === "FORM") {
            host.appendChild(form);
        }

        const mountedForm = host ? host.querySelector("form") : null;
        if (mountedForm) {
            mountedForm.classList.add("ym-form");
            $$(".ym-field", mountedForm).forEach(buildField);
            buildActionBar(mountedForm);
        }

        initNavigation(config, mountedForm);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
