<?php

namespace TypechoPlugin\YumeEditor;

use DOMDocument;
use DOMXPath;
use Typecho\Cookie;
use Typecho\Db;
use Typecho\Plugin\Exception;
use Utils\Helper;
use Widget\User;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 内容处理
 *
 * Markdown 渲染、围栏块后处理、摘要裁剪与评论可见性校验。
 *
 * @package YumeEditor
 * @author aayorishy
 * @version 1.0.0
 */
class Content
{

    public static function markdown($text): string
    {
        $markdown = new HyperDown();
        return $markdown->makeHtml($text);
    }


    /**
     * 内容处理
     * @param $text
     * @param $archive
     * @param $last
     * @return string
     * @throws \Typecho\Db\Exception
     * @throws \Typecho\Plugin\Exception
     * @throws \Typecho\Widget\Exception
     */
    public static function contentEx($text, $archive, $last): string
    {
        if ($last) $text = $last;

        // 隐藏代码块
        $blocks = [];
        $text = self::protectCodeBlocks($text, $blocks);

        // Markdown 增强
        if (strpos($text, '[x]') !== false || strpos($text, '[ ]') !== false) {
            $text = strtr($text, array(
                "[x]" => '<input type="checkbox" class="ym-checkbox" checked disabled /><label class="ym-checkbox-label"></label>',
                "[ ]" => '<input type="checkbox" class="ym-checkbox" disabled /><label class="ym-checkbox-label"></label>'
            ));
        }

        $text = self::processFencesBlock($text, $archive);

        // 还原代码块
        $text = strtr($text, $blocks);

        return $text;
    }


    /**
     * 摘要处理
     */
    public static function excerptEx($text, $archive, $last): string
    {
        if ($last) $text = $last;
        // 隐藏代码块
        $blocks = [];
        $text = self::protectCodeBlocks($text, $blocks);

        // Markdown 增强
        if (strpos($text, '[x]') !== false || strpos($text, '[ ]') !== false) {
            $text = strtr($text, array(
                "[x]" => '【已完成】',
                "[ ]" => '【未完成】'
            ));
        }

        // Fences 去除默认标题
        $text = self::processFencesBlock($text, $archive, true);

        // 还原代码块
        $text = strtr($text, $blocks);

        if (false !== strpos($text, '[hide')) {
            $pattern = "(?s)<pre[^<]*>.*?<\/pre>(*SKIP)(*F)|\[hide](.*?)\[\/hide]";
            $text = preg_replace("/$pattern/ism", '【内容回复可见】', $text);
        }
        return $text;
    }


    private static function protectCodeBlocks(string $text, array &$blocks): string
    {
        // 随机标识防止原文经 DOM 规范化后伪造占位符，再检查显式碰撞。
        $decodedText = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        do {
            $prefix = '__YUMEEDITOR_CODE_' . bin2hex(random_bytes(16)) . '_';
        } while (strpos($text, $prefix) !== false || strpos($decodedText, $prefix) !== false);
        return preg_replace_callback('/(?:<pre\b[^>]*>.*?<\/pre>|<code\b[^>]*>.*?<\/code>)/ism', function ($match) use (&$blocks, $prefix) {
            $holder = '<pre>' . $prefix . count($blocks) . '__</pre>';
            $blocks[$holder] = $match[0];
            return $holder;
        }, $text);
    }



    public static function processFencesBlock($text, $archive, $isExcerpt = false)
    {
        // 1. 预检查
        $hasGridFence = strpos($text, 'fence-grid') !== false;
        $hasPassFence = strpos($text, 'fence-pass-comment') !== false;
        $hasMasonryFence = strpos($text, 'fence-masonry') !== false;
        if (!$hasGridFence && !$hasPassFence && !$hasMasonryFence) {
            return $text;
        }

        $previousLibxmlErrors = libxml_use_internal_errors(true);
        try {
            // 创建 DOMDocument 对象
            $dom = new DOMDocument();
            if (!@$dom->loadHTML('<?xml encoding="UTF-8">' . $text, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
                libxml_clear_errors();
                return $text;
            }

            // 创建 XPath 对象
            $xpath = new DOMXPath($dom);
            $fencePassElements = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' fence-pass-comment ')]");

            if ($hasGridFence) {
                self::normalizeGridFenceContent($dom, $xpath);
            }

            if ($hasMasonryFence) {
                self::normalizeMasonryFenceContent($dom, $xpath);
            }

            if (!$hasPassFence || $fencePassElements->length === 0) {
                $modifiedHtml = $dom->saveHTML();
                libxml_clear_errors();
                return substr($modifiedHtml, 23);
            }

            if ($fencePassElements->length === 0) {
                return $text;
            }

            // 2. 处理 $isExcerpt = true 的情况
            if ($isExcerpt) {
                foreach ($fencePassElements as $element) {
                    // 摘要始终隐藏整个区块，避免嵌套内容或缺少内容容器时泄露正文。
                    if ($element->parentNode) {
                        $element->parentNode->replaceChild($dom->createTextNode('【内容回复可见】'), $element);
                    }
                }
                $modifiedHtml = $dom->saveHTML();
                libxml_clear_errors();
                return substr($modifiedHtml, 23);
            }

            // 3. 默认情况（$isExcerpt = false）
            $user = User::alloc();
            $db = Db::get();
            $hasPermission = $user->pass('administrator', true);
            if (!$hasPermission) {
                $commentId = $user->hasLogin() ? null : self::getRememberedCommentId((int) $archive->cid);
                if ($user->hasLogin() || $commentId !== null) {
                    $select = $db->select()->from('table.comments')
                        ->where('cid = ?', $archive->cid)
                        ->where('type = ?', 'comment')
                        ->where('status = ?', 'approved')
                        ->limit(1);
                    if ($user->hasLogin()) {
                        $select->where('authorId = ?', $user->uid);
                    } else {
                        $select->where('coid = ?', $commentId);
                    }
                    if ($db->fetch($select)) {
                        $hasPermission = true;
                    }
                }
            }

            foreach ($fencePassElements as $element) {
                $titleNode = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' fence-title ')]", $element)->item(0);
                if ($titleNode) {
                    $title = $titleNode->textContent;
                    if (strtoupper($title) === 'PASS') {
                        while ($titleNode->firstChild) {
                            $titleNode->removeChild($titleNode->firstChild);
                        }
                        $newTitleText = $dom->createTextNode(_t("回复可见"));
                        $titleNode->appendChild($newTitleText);
                    }
                }
                if (!$hasPermission) {
                    $contentNode = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' fence-content ')]", $element)->item(0);
                    if ($contentNode) {
                        $contentNode->setAttribute('class', $contentNode->getAttribute('class') . ' text-center');
                        $replacementHtml = _t('此处内容已隐藏，<a href="%s">回复并审核通过后</a>可见', htmlspecialchars($archive->permalink . '#comments', ENT_QUOTES, 'UTF-8'));
                        while ($contentNode->firstChild) {
                            $contentNode->removeChild($contentNode->firstChild);
                        }
                        $fragment = $dom->createDocumentFragment();
                        @$fragment->appendXML($replacementHtml);
                        $contentNode->appendChild($fragment);
                    }
                }
            }

            // 4. 输出修改后的 HTML
            $modifiedHtml = $dom->saveHTML();
            libxml_clear_errors();
            return substr($modifiedHtml, 23);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxmlErrors);
        }
    }


    public static function rememberCommentPermission($comment): void
    {
        $cid = (int) $comment->cid;
        $coid = (int) $comment->coid;
        if ($cid <= 0 || $coid <= 0) {
            throw new Exception(_t('无法获取评论授权所需的文章或评论编号'));
        }
        $expires = time() + 30 * 86400;
        $payload = $cid . ':' . $coid . ':' . $expires;
        Cookie::set('__yumeeditor_comment_' . $cid, $payload . ':' . self::signCommentPermission($payload), $expires);
    }


    private static function signCommentPermission(string $payload): string
    {
        $secret = Helper::options()->secret;
        if (!is_string($secret) || $secret === '') {
            throw new Exception(_t('缺少 Typecho 站点密钥，无法验证评论授权'));
        }
        return hash_hmac('sha256', 'yumeeditor-pass-comment:' . $payload, $secret);
    }


    private static function getRememberedCommentId(int $cid): ?int
    {
        $token = Cookie::get('__yumeeditor_comment_' . $cid);
        if (!is_string($token) || !preg_match('/^([1-9][0-9]*):([1-9][0-9]*):([1-9][0-9]*):([a-f0-9]{64})$/D', $token, $parts)) {
            return null;
        }
        if ($parts[1] !== (string) $cid || (int) $parts[3] <= time()) {
            return null;
        }
        $payload = $parts[1] . ':' . $parts[2] . ':' . $parts[3];
        return hash_equals(self::signCommentPermission($payload), $parts[4]) ? (int) $parts[2] : null;
    }


    private static function normalizeMasonryFenceContent(DOMDocument $dom, DOMXPath $xpath): void
    {
        $masonryElements = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' fence-masonry ')]");
        foreach ($masonryElements as $element) {
            $contentNode = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' fence-content ')]", $element)->item(0);
            if (!$contentNode || $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' masonry-wrapper ')]", $contentNode)->length > 0) {
                continue;
            }

            $items = self::extractMasonryItems($contentNode);
            if (!$items) {
                continue;
            }

            while ($contentNode->firstChild) {
                $contentNode->removeChild($contentNode->firstChild);
            }

            $wrapper = $dom->createElement('div');
            $wrapper->setAttribute('class', 'masonry-wrapper');

            foreach ($items as $nodes) {
                $item = $dom->createElement('div');
                $item->setAttribute('class', 'masonry-item');

                $inner = $dom->createElement('div');
                $inner->setAttribute('class', 'masonry-item-inner');

                foreach ($nodes as $node) {
                    $inner->appendChild($dom->importNode($node, true));
                }

                $item->appendChild($inner);
                $wrapper->appendChild($item);
            }

            $contentNode->appendChild($wrapper);
        }
    }


    private static function normalizeGridFenceContent(DOMDocument $dom, DOMXPath $xpath): void
    {
        $gridElements = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' fence-grid ')]");
        foreach ($gridElements as $element) {
            $contentNode = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' fence-content ')]", $element)->item(0);
            if (!$contentNode) {
                continue;
            }

            $replacementNodes = [];
            $hasWrappedParagraph = false;

            foreach (iterator_to_array($contentNode->childNodes) as $child) {
                if ($child->nodeType === XML_TEXT_NODE && trim($child->textContent) === '') {
                    continue;
                }

                if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->nodeName) === 'p') {
                    $hasWrappedParagraph = true;
                    foreach (iterator_to_array($child->childNodes) as $paragraphChild) {
                        if ($paragraphChild->nodeType === XML_TEXT_NODE && trim($paragraphChild->textContent) === '') {
                            continue;
                        }
                        if ($paragraphChild->nodeType === XML_ELEMENT_NODE && strtolower($paragraphChild->nodeName) === 'br') {
                            continue;
                        }
                        $replacementNodes[] = $paragraphChild->cloneNode(true);
                    }
                    continue;
                }

                if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->nodeName) === 'br') {
                    continue;
                }

                $replacementNodes[] = $child->cloneNode(true);
            }

            if (!$hasWrappedParagraph) {
                continue;
            }

            while ($contentNode->firstChild) {
                $contentNode->removeChild($contentNode->firstChild);
            }

            foreach ($replacementNodes as $node) {
                $contentNode->appendChild($dom->importNode($node, true));
            }
        }
    }


    private static function extractMasonryItems($contentNode): array
    {
        $items = [];
        $textBuffer = [];

        foreach (iterator_to_array($contentNode->childNodes) as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                if (trim($child->textContent) === '') {
                    if (self::masonryNodesHaveContent($textBuffer)) {
                        $items[] = $textBuffer;
                    }
                    $textBuffer = [];
                    continue;
                }

                $textBuffer[] = $child->cloneNode(true);
                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $tagName = strtolower($child->nodeName);
            if ($tagName === 'br') {
                if (self::masonryNodesHaveContent($textBuffer)) {
                    $items[] = $textBuffer;
                }
                $textBuffer = [];
                continue;
            }

            if ($tagName === 'p') {
                if (self::masonryNodesHaveContent($textBuffer)) {
                    $items[] = $textBuffer;
                    $textBuffer = [];
                }

                $paragraphNodes = [];
                foreach (iterator_to_array($child->childNodes) as $paragraphChild) {
                    $paragraphNodes[] = $paragraphChild->cloneNode(true);
                }

                if (self::masonryNodesHaveContent($paragraphNodes)) {
                    $items[] = $paragraphNodes;
                }
                continue;
            }

            if (self::masonryNodesHaveContent($textBuffer)) {
                $items[] = $textBuffer;
                $textBuffer = [];
            }

            $items[] = [$child->cloneNode(true)];
        }

        if (self::masonryNodesHaveContent($textBuffer)) {
            $items[] = $textBuffer;
        }

        return $items;
    }


    private static function masonryNodesHaveContent(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                return true;
            }

            if ($node->nodeType === XML_TEXT_NODE && trim($node->textContent) !== '') {
                return true;
            }
        }

        return false;
    }
}
