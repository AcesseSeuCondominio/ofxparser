<?php

namespace OfxParser;

/**
 * An OFX parser library
 *
 * Heavily refactored from Guillaume Bailleul's grimfor/ofxparser
 *
 * @author Guillaume BAILLEUL <contact@guillaume-bailleul.fr>
 * @author James Titcumb <hello@jamestitcumb.com>
 * @author Oliver Lowe <mrtriangle@gmail.com>
 */
class Parser
{

    /**
     * Load an OFX file into this parser by way of a filename
     *
     * @param string $ofxFile A path that can be loaded with file_get_contents
     * @return  Ofx
     * @throws \InvalidArgumentException
     */
    public function loadFromFile($ofxFile)
    {
        if (file_exists($ofxFile)) {
            return $this->loadFromString(file_get_contents($ofxFile));
        } else {
            throw new \InvalidArgumentException("File '{$ofxFile}' could not be found");
        }
    }

    /**
     * Load an OFX by directly using the text content
     *
     * @param string $ofxContent
     * @return  Ofx
     * @throws \Exception
     */
    public function loadFromString($ofxContent)
    {
        $ofxContent = mb_convert_encoding($ofxContent, 'UTF-8', $this->detectEncodingFromHeader($ofxContent));

        $sgmlStart = stripos($ofxContent, '<OFX>');
        $ofxHeader = trim(substr($ofxContent, 0, $sgmlStart));
        $ofxSgml = trim(substr($ofxContent, $sgmlStart));

        // IF THERE IS A CHARACTER & WHICH CAUSES THE FILE TO BE INVALID, IT REPLACES
        $ofxSgml = str_replace('&', '', $ofxSgml);

        // WHEN TYPE IS EMPTY Wiil BE FILL WITH OTHER
        $enR = str_replace("<TRNTYPE> ", "<TRNTYPE>OTHER", $ofxSgml);
        
        $ofxXml = $this->convertSgmlToXml($enR);
        $xml = $this->xmlLoadString($ofxXml);

        return new \OfxParser\Ofx($xml);
    }

    /**
     * Load an XML string without PHP errors - throws exception instead
     *
     * @param string $xmlString
     * @throws \Exception
     * @return \SimpleXMLElement
     */
    private function xmlLoadString($xmlString)
    {
        libxml_clear_errors();
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);

        if ($errors = libxml_get_errors()) {
            throw new \Exception("Failed to parse OFX: " . var_export($errors, true));
        }

        return $xml;
    }

    /**
     * Detect any unclosed XML tags - if they exist, close them
     *
     * @param string $line
     * @return $line
     */
    private function closeUnclosedXmlTags($line)
    {
        $trimmed = trim($line);

        // Empty opening tag (e.g. <MEMO> with no content) - close it so XML is valid (e.g. Santander OFX)
        if (preg_match('/^<([A-Za-z0-9.]+)>\s*$/', $trimmed, $emptyMatches)) {
            return "<{$emptyMatches[1]}></{$emptyMatches[1]}>";
        }

        // Matches: <SOMETHING>blah
        // Does not match: <SOMETHING>
        // Does not match: <SOMETHING>blah</SOMETHING>
        if (preg_match("/<([A-Za-z0-9.]+)>([\wà-úÀ-Ú0-9\.\-\_\+\, ;:\[\]\'\&\/\\\*\(\)\+\{\}\!\£\$\?=@€£#%±§~`]+)$/", $trimmed, $matches)) {
            return "<{$matches[1]}>{$matches[2]}</{$matches[1]}>";
        }
        return $line;
    }

    /**
     * Convert an SGML to an XML string
     *
     * @param string $sgml
     * @return string
     */
    private function convertSgmlToXml($sgml)
    {
        $sgml = str_replace("\r\n", "\n", $sgml);
        $sgml = str_replace("\r", "\n", $sgml);

        $lines = explode("\n", $sgml);

        $xml = "";
        foreach ($lines as $line) {
            $xml .= trim($this->closeUnclosedXmlTags($line)) . "\n";
        }

        return trim($xml);
    }

    /**
    * Detect encoding from header CHARSET from OFX file
    * 
    * @param string $header Header of the OFX file
    * @return string Encoding detected (default: Windows-1252)
    */
    private function detectEncodingFromHeader($header)
    {
        if (preg_match('/CHARSET:(\d+)/i', $header, $matches)) {
            $charset = trim($matches[1]);
            
            $charsetMap = [
                '65001' => 'UTF-8',
                '28591' => 'UTF-8',
            ];
            
            if (isset($charsetMap[$charset])) {
                return $charsetMap[$charset];
            }
        }
        
        return 'Windows-1252';
    }
}
