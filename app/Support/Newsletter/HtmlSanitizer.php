<?php

namespace App\Support\Newsletter;

use DOMDocument;
use DOMElement;
use DOMNameSpaceNode;
use DOMNode;
use DOMNodeList;
use DOMXPath;

/**
 * Säubert das im Browser aus MJML erzeugte Newsletter-HTML, bevor es
 * gespeichert und verschickt wird. Der Browser ist nicht vertrauenswürdig:
 * Skripte, eingebettete Fremdinhalte, Event-Handler und unsichere Links
 * fliegen raus. Struktur, Inline-Styles, <style> und Outlook-Kommentare
 * (<!--[if mso]>) bleiben erhalten.
 */
final class HtmlSanitizer
{
    /**
     * Elemente, die samt Inhalt entfernt werden.
     */
    private const array FORBIDDEN_ELEMENTS = ['script', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'frame', 'frameset', 'base', 'svg', 'math'];

    /**
     * Erlaubte Schemata für Links; Bilder nur https (kein Mixed Content).
     */
    private const array LINK_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);

        foreach (self::FORBIDDEN_ELEMENTS as $tag) {
            $this->removeAll($xpath->query("//{$tag}"));
        }

        // meta refresh leitet beim Öffnen im Browser weiter.
        $this->removeAll($xpath->query("//meta[translate(@http-equiv, 'REFSH', 'refsh')='refresh']"));

        /** @var DOMElement $element */
        foreach (iterator_to_array($xpath->query('//*[@*]') ?: []) as $element) {
            $this->sanitizeAttributes($element);
        }

        $output = '';

        foreach ($document->childNodes as $child) {
            // Die Encoding-Hilfe nicht mit ausgeben.
            if ($child->nodeType === XML_PI_NODE) {
                continue;
            }

            $output .= $document->saveHTML($child);
        }

        return "<!doctype html>\n".$output;
    }

    /**
     * @param  DOMNodeList<DOMNameSpaceNode|DOMNode>|false  $nodes
     */
    private function removeAll(DOMNodeList|false $nodes): void
    {
        foreach (iterator_to_array($nodes ?: []) as $node) {
            if ($node instanceof DOMNode) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    private function sanitizeAttributes(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);

            if (str_starts_with($name, 'on') || in_array($name, ['srcdoc', 'formaction', 'xlink:href'], true)) {
                $element->removeAttribute($attribute->name);

                continue;
            }

            if (in_array($name, ['href', 'action'], true) && ! $this->isAllowedUrl($value, self::LINK_SCHEMES)) {
                $element->removeAttribute($attribute->name);

                continue;
            }

            if (in_array($name, ['src', 'background', 'poster'], true) && ! $this->isAllowedUrl($value, ['https'])) {
                $element->removeAttribute($attribute->name);

                continue;
            }

            if ($name === 'style' && preg_match('/expression\s*\(|javascript:|url\s*\(\s*[\'"]?\s*(?!https:)[a-z]+:/i', $value)) {
                $element->removeAttribute($attribute->name);
            }
        }
    }

    /**
     * @param  list<string>  $schemes
     */
    private function isAllowedUrl(string $url, array $schemes): bool
    {
        // Platzhalter und Anker ohne Schema (z. B. "#") sind harmlos.
        if ($url === '' || str_starts_with($url, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url(preg_replace('/\s+/', '', $url) ?? $url, PHP_URL_SCHEME));

        return in_array($scheme, $schemes, true);
    }
}
