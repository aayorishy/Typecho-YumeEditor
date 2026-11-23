/**
 * YumeEditor · admin preview bridge
 *
 * Exposes the editor-wide code-block renderer and the preview HTML pipeline.
 * Everything is registered at module load (instead of behind `init()`) so the
 * editor works even when a third-party script never calls `init()`.
 */
import { enhanceCodeBlocks, DEFAULT_CODE_BLOCK_MESSAGES } from "./utils/codeBlocks";

// highlight.js ships inside the admin bundle so the preview can highlight code
// without depending on the front-end "/theme" setting.
if (!window.hljs) {
    window.hljs = require("highlight.js/lib/common");
}

window.YumeEditorCodeBlocks = Object.assign(
    { DEFAULT_CODE_BLOCK_MESSAGES, enhanceCodeBlocks },
    window.YumeEditorCodeBlocks || {}
);

const htmlProcessors = [];

/**
 * Register a preview HTML processor.
 *
 * @param {Function} processor
 * @param {number} [priority]
 */
function addProcessor(processor, priority = 99) {
    if (typeof processor !== "function") {
        return;
    }

    htmlProcessors.push({ priority, processor });
    htmlProcessors.sort((left, right) => left.priority - right.priority);
}

window.YumePreviewUtils = {
    htmlProcessors,

    /** Kept for backwards compatibility with older integrations. */
    init() {
    },

    /**
     * @returns {HTMLElement|null}
     */
    getPreviewArea() {
        return document.getElementById("wmd-preview-yumeeditor")
            || document.getElementById("wmd-preview")
            || null;
    },

    addProcessor,

    /**
     * Turn rendered Markdown into the final preview HTML.
     *
     * @param {string} html
     * @returns {string}
     */
    processHtml(html) {
        let output = String(html ?? "");

        output = output.replace(
            /\[x]/g,
            '<input type="checkbox" class="ym-checkbox" checked disabled/>'
        );
        output = output.replace(
            /\[ ]/g,
            '<input type="checkbox" class="ym-checkbox" disabled/>'
        );

        htmlProcessors.forEach(({ processor }) => {
            const next = processor.call(this, output);
            if (typeof next === "string") {
                output = next;
            }
        });

        return output;
    },
};

if (window.jQuery) {
    window.jQuery("body").on("YumeEditorAddParser", function (event, processor, priority) {
        addProcessor(processor, priority);
    });
}
