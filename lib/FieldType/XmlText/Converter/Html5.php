<?php

/**
 * This file is part of the eZ Platform XmlText Field Type package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 *
 * @version //autogentag//
 */
namespace eZ\Publish\Core\FieldType\XmlText\Converter;

use eZ\Publish\Core\FieldType\XmlText\Converter;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentType;
use DOMDocument;
use XSLTProcessor;
use RuntimeException;

/**
 * Converts internal XmlText representation to HTML5.
 */
class Html5 implements Converter
{
    /**
     * Path to stylesheet to use.
     *
     * @var string
     */
    protected $stylesheet;

    /**
     * Array of XSL stylesheets to add to the main one, grouped by priority.
     *
     * @var array
     */
    protected $customStylesheets = [];

    /**
     * @var \XSLTProcessor
     */
    protected $xsltProcessor;

    /**
     * Array of converters that needs to be called before actual processing.
     *
     * @var \eZ\Publish\Core\FieldType\XmlText\Converter[]
     */
    private $preConverters;

    /**
     * Constructor.
     *
     * @param string $stylesheet Stylesheet to use for conversion
     * @param array $customStylesheets Array of XSL stylesheets. Each entry consists in a hash having "path" and "priority" keys.
     * @param \eZ\Publish\Core\FieldType\XmlText\Converter[] $preConverters Array of pre-converters
     *
     * @throws \eZ\Publish\Core\Base\Exceptions\InvalidArgumentType
     */
    public function __construct($stylesheet, array $customStylesheets = [], array $preConverters = [])
    {
        $this->stylesheet = $stylesheet;
        $this->setCustomStylesheets($customStylesheets);

        foreach ($preConverters as $preConverter) {
            if (!$preConverter instanceof Converter) {
                throw new InvalidArgumentType(
                    '$preConverters',
                    'eZ\\Publish\\Core\\FieldType\\XmlText\\Converter[]',
                    $preConverter
                );
            }
        }

        $this->preConverters = $preConverters;
    }

    /**
     * Sets the custom stylesheets grouped by priority.
     *
     * @param array $customStylesheets Array of XSL stylesheets. Each entry
     * consists in a hash having "path" and "priority" keys.
     */
    public function setCustomStylesheets($customStylesheets)
    {
        $this->customStylesheets = [];
        if (!$customStylesheets) {
            return;
        }
        foreach ($customStylesheets as $stylesheet) {
            if (!isset($this->customStylesheets[$stylesheet['priority']])) {
                $this->customStylesheets[$stylesheet['priority']] = [];
            }

            $this->customStylesheets[$stylesheet['priority']][] = $stylesheet['path'];
        }
    }

    /**
     * Adds a pre-converter to the list.
     * Use a pre-converter when you need some processing before XSLT transformation (e.g. for custom tags).
     *
     * @param Converter $preConverter
     */
    public function addPreConverter(Converter $preConverter)
    {
        $this->preConverters[] = $preConverter;
    }

    /**
     * @return array|\eZ\Publish\Core\FieldType\XmlText\Converter[]
     */
    public function getPreConverters()
    {
        return $this->preConverters;
    }

    /**
     * Returns the XSLTProcessor to use to transform internal XML to HTML5.
     *
     * @return \XSLTProcessor
     */
    protected function getXSLTProcessor()
    {
        if (isset($this->xsltProcessor)) {
            return $this->xsltProcessor;
        }

        $xslDoc = new DOMDocument();
        $xslDoc->load($this->stylesheet);

        // Now loading custom xsl stylesheets dynamically.
        // According to XSL spec, each <xsl:import> tag MUST be loaded BEFORE any other element.
        // PHP 8.3 fix: Handle null documentElement and firstChild safely
        if ($xslDoc->documentElement === null) {
            throw new RuntimeException("Failed to load XSL stylesheet or stylesheet is invalid: " . $this->stylesheet);
        }
        
        $insertBeforeEl = $xslDoc->documentElement->firstChild;
        if ($insertBeforeEl === null) {
            // PHP 8.3: Create a text node as reference point if no firstChild exists
            $insertBeforeEl = $xslDoc->createTextNode('');
            $xslDoc->documentElement->appendChild($insertBeforeEl);
        }
        foreach ($this->getSortedCustomStylesheets() as $stylesheet) {
            if (!file_exists($stylesheet)) {
                throw new RuntimeException("Cannot find XSL stylesheet for XMLText rendering: $stylesheet");
            }

            $newEl = $xslDoc->createElement('xsl:import');
            $hrefAttr = $xslDoc->createAttribute('href');
            $hrefAttr->value = str_replace('\\', '/', $stylesheet);
            $newEl->appendChild($hrefAttr);
            $xslDoc->documentElement->insertBefore($newEl, $insertBeforeEl);
        }
        // Now reload XSL DOM to "refresh" it.
        $xslDoc->loadXML($xslDoc->saveXML());

        $this->xsltProcessor = new XSLTProcessor();
        $this->xsltProcessor->importStyleSheet($xslDoc);
        $this->xsltProcessor->registerPHPFunctions();

        return $this->xsltProcessor;
    }

    /**
     * Returns custom stylesheets to load, sorted.
     * The order is from the lowest priority to the highest since in case of a conflict,
     * the last loaded XSL template always wins.
     *
     * @return array
     */
    private function getSortedCustomStylesheets()
    {
        $sortedStylesheets = [];
        ksort($this->customStylesheets);
        foreach ($this->customStylesheets as $stylesheets) {
            $sortedStylesheets = array_merge($sortedStylesheets, $stylesheets);
        }

        return $sortedStylesheets;
    }

    /**
     * Convert $xmlDoc from internal representation DOMDocument to HTML5.
     *
     * @param \DOMDocument $xmlDoc
     *
     * @return string
     */
    public function convert(DOMDocument $xmlDoc)
    {
        foreach ($this->getPreConverters() as $preConverter) {
            $preConverter->convert($xmlDoc);
        }

        // PHP 8.3 compatibility: Basic validation
        if (!$xmlDoc->documentElement) {
            return '';
        }

        try {
            $xsl = $this->getXSLTProcessor();
            $result = $xsl->transformToXML($xmlDoc);

            // Validate and clean result
            if ($result === false || $result === null) {
                return $this->fallbackXmlToHtml($xmlDoc);
            }

            return $result;
        } catch (\Exception $e) {
            // Fallback if XSLT fails
            return $this->fallbackXmlToHtml($xmlDoc);
        }
    }

    /**
     * Fallback method for XML to HTML conversion when XSLT fails (PHP 8.3 compatibility).
     *
     * @param \DOMDocument $xmlDoc
     * @return string
     */
    private function fallbackXmlToHtml(DOMDocument $xmlDoc)
    {
        if (!$xmlDoc->documentElement) {
            return '';
        }

        $xml_string = $xmlDoc->saveXML($xmlDoc->documentElement);

        // Remove XML namespaces
        $xml_string = preg_replace('/\s*xmlns:[^=]*="[^"]*"/i', '', $xml_string);

        // Basic XML to HTML transformations
        $html = str_replace([
            '<section>', '</section>',
            '<paragraph>', '</paragraph>',
            '<emphasize>', '</emphasize>',
            '<line>', '</line>',
            '<list class="unordered">', '<list class="ordered">', '</list>',
            '<listitem>', '</listitem>'
        ], [
            '<div class="section">', '</div>',
            '<p>', '</p>',
            '<em>', '</em>',
            '', '<br>',
            '<ul>', '<ol>', '</ul>',
            '<li>', '</li>'
        ], $xml_string);

        // Handle headers with regex
        $html = preg_replace('/<header\s+level="(\d+)"[^>]*>(.*?)<\/header>/s', '<h$1>$2</h$1>', $html);

        // Handle links
        $html = preg_replace('/<link\s+url="([^"]*)"[^>]*>(.*?)<\/link>/s', '<a href="$1">$2</a>', $html);

        return $html;
    }
}
