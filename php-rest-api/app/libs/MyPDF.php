<?php

class MyPDF extends TCPDF {

    var $htmlHeader;
    var $htmlFooter;
    var $htmlExtraHeader;

    public function setHtmlHeader($htmlHeader) {
        $this->htmlHeader = $htmlHeader;
    }

    public function setHtmlExtraHeader($htmlExtraHeader) {
        $this->htmlExtraHeader = $htmlExtraHeader;
    }

    public function Header() {
        if (count($this->pages) != 1) {
            $this->writeHTMLCell(0, 0, '', '', $this->htmlHeader, 0, 1, 0, true, 'top', true);
            $this->writeHTMLCell(0, 0, '', '', $this->htmlExtraHeader, 0, 1, 0, true, 'top', true);
        }
    }

    public function setHtmlFooter($htmlFooter) {
        $this->htmlFooter = $htmlFooter;
    }

    public function Footer() {
        if (count($this->pages) != 1) {
            $this->writeHTMLCell(0, 0, '', '', $this->htmlFooter, 0, 1, 0, true, 'top', true);
        }
    }
}
