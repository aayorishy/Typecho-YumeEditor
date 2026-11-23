import {DEFAULT_MERMAID_MESSAGES, enhanceMermaidBlocks} from "./utils/mermaidBlocks";

window.YumeEditorMermaidBlocks = {
    ...(window.YumeEditorMermaidBlocks || {}),
    DEFAULT_MERMAID_MESSAGES,
    enhanceMermaidBlocks,
};

function initMermaidBlocks(root = document) {
    return enhanceMermaidBlocks(root, window.YumeEditorMermaidBlocksConfig || {});
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
        initMermaidBlocks(document);
    });
} else {
    initMermaidBlocks(document);
}

document.addEventListener("pjax:complete", () => {
    initMermaidBlocks(document);
});
