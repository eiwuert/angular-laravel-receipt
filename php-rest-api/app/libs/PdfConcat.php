<?php

class PdfConcat extends FPDI {

    var $files = array();
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
        
            $this->writeHTMLCell(0, 0, '', '', $this->htmlHeader, 0, 1, 0, true, 'top', true);
            $this->writeHTMLCell(0, 0, '', '', $this->htmlExtraHeader, 0, 1, 0, true, 'top', true);
        
    }

    public function setHtmlFooter($htmlFooter) {
        $this->htmlFooter = $htmlFooter;
    }

    public function Footer() {
        
            $this->writeHTMLCell(0, 0, '', '', $this->htmlFooter, 0, 1, 0, true, 'top', true);
        
    }
    
    function setFiles($files) {
        $this->files = $files;
    }

    function concat() {        
        $countFile = 0;
        foreach ($this->files AS $file) {            
            $countFile++;
            if ($countFile <= 1) {
                $pagecount = $this->setSourceFile($file);
                for ($i = 1; $i <= $pagecount; $i++) {
                    $tplidx = $this->ImportPage($i);
                    $s = $this->getTemplatesize($tplidx);
                    $this->AddPage('L', array($s['w'], $s['h']));
                    $this->useTemplate($tplidx);                                        
                }
            } else {
                $pagecount = $this->setSourceFile($file);
                for ($i = 1; $i <= $pagecount; $i++) {
                    $tplidx = $this->ImportPage($i);
                    $s = $this->getTemplatesize($tplidx);                    
                    $this->AddPage('P');         
                    $fileBasePath = Config::get('app.fileBasePath').'/tmpDirPdf/';
                    $this->writeHTML('<p style="font-size:8px;line-height:50px;">Receipt Name: '.substr($file,strlen($fileBasePath)-1).'</p>');
                    $this->useTemplate($tplidx, 80, 10,70);                                        
                }
            }            
        }
    }

}

?>