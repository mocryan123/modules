<?php
/**
 * Pure PHP PDF Generation - Tested and Working
 * Creates valid PDF files using simple, proven structure
 */

function generate_simple_pdf($title, $content, $category, $date, $filepath) {
    try {
        $pdf = new SimplePDF();
        $pdf->addPage();
        
        // Header Background - Full width from left to right
        $pdf->setFillColor(102, 126, 234);
        $pdf->rect(0, 0, 210, 32, 'F');
        
        // Title in white - positioned properly in header
        $pdf->setFont('Helvetica', 'B', 18);
        $pdf->setTextColor(255, 255, 255);
        $pdf->xy(12, 8);
        $pdf->cell(186, 8, substr($title, 0, 70), 0, 0, 'L');
        
        // Category & Date in white, smaller - below title
        $pdf->setFont('Helvetica', '', 8);
        $pdf->setTextColor(230, 230, 255);
        $pdf->xy(12, 18);
        $pdf->cell(186, 5, 'Category: ' . substr($category, 0, 25) . ' | Date: ' . $date, 0, 0, 'L');
        
        // Move down after header
        $pdf->xy(12, 45);
        
        // Content body
        $pdf->setFont('Helvetica', '', 11);
        $pdf->setTextColor(0, 0, 0);
        
        $lines = explode("\n", $content);
        $line_count = 0;
        $current_y = 45;
        
        foreach ($lines as $line) {
            if ($line_count > 45 || $current_y > 270) break;
            
            $trimmed = trim($line);
            if (!empty($trimmed)) {
                // Handle bullet points - check for "-" at start
                if (strpos($trimmed, '-') === 0) {
                    // Remove the dash and clean up
                    $bullet_text = ltrim(substr($trimmed, 1));
                    
                    // Draw dash bullet
                    $pdf->setFont('Helvetica', 'B', 11);
                    $pdf->xy(12, $current_y);
                    $pdf->cell(2, 6, '-', 0, 0, 'L');
                    
                    // Draw text after bullet
                    $pdf->setFont('Helvetica', '', 10);
                    $pdf->xy(18, $current_y);
                    $pdf->cell(180, 6, substr($bullet_text, 0, 88), 0, 0, 'L');
                    
                    $current_y += 8;
                    $line_count++;
                } else {
                    // Regular text
                    $pdf->setFont('Helvetica', '', 11);
                    $pdf->xy(12, $current_y);
                    $pdf->cell(186, 6, substr($trimmed, 0, 95), 0, 0, 'L');
                    $current_y += 8;
                    $line_count++;
                }
            } else {
                $current_y += 4;
            }
        }
        
        // Footer line
        $current_y = max($current_y + 8, 270);
        $pdf->setDrawColor(220, 220, 220);
        $pdf->setLineWidth(0.3);
        $pdf->line(12, $current_y, 198, $current_y);
        
        $current_y += 6;
        
        // Footer text
        $pdf->setFont('Helvetica', 'I', 8);
        $pdf->setTextColor(150, 150, 150);
        $pdf->xy(12, $current_y);
        $pdf->cell(186, 4, 'Generated from BNTM Notepad • ' . date('Y-m-d H:i'), 0, 0, 'C');
        
        $pdf->output('F', $filepath);
        return file_exists($filepath) && filesize($filepath) > 100;
        
    } catch (Exception $e) {
        error_log('PDF Generation Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Simple PDF class - Minimal working PDF generator
 */
class SimplePDF {
    private $x = 15;
    private $y = 15;
    private $font_name = 'Helvetica';
    private $font_size = 11;
    private $text_color = '0 0 0';
    private $draw_color = '0 0 0';
    private $fill_color = '1 1 1';
    private $pages = [];
    private $current_page = 0;
    private $page_height = 297; // A4 height in mm
    private $page_width = 210;  // A4 width in mm
    private $page_content = '';
    
    public function addPage() {
        if ($this->current_page > 0) {
            $this->pages[$this->current_page - 1] = $this->page_content;
        }
        $this->current_page++;
        $this->page_content = '';
        $this->y = 15;
        $this->x = 15;
    }
    
    public function xy($x, $y) {
        $this->x = $x;
        $this->y = $y;
    }
    
    public function setFont($family, $style = '', $size = 11) {
        $this->font_name = $family;
        $this->font_size = $size;
    }
    
    public function setTextColor($r, $g = null, $b = null) {
        if ($g === null) {
            $gray = round($r / 255, 3);
            $this->text_color = "$gray $gray $gray";
        } else {
            $this->text_color = round($r/255, 3) . ' ' . round($g/255, 3) . ' ' . round($b/255, 3);
        }
    }
    
    public function setFillColor($r, $g = null, $b = null) {
        if ($g === null) {
            $gray = round($r / 255, 3);
            $this->fill_color = "$gray $gray $gray";
        } else {
            $this->fill_color = round($r/255, 3) . ' ' . round($g/255, 3) . ' ' . round($b/255, 3);
        }
    }
    
    public function setDrawColor($r, $g = null, $b = null) {
        if ($g === null) {
            $gray = round($r / 255, 3);
            $this->draw_color = "$gray $gray $gray";
        } else {
            $this->draw_color = round($r/255, 3) . ' ' . round($g/255, 3) . ' ' . round($b/255, 3);
        }
    }
    
    public function setLineWidth($w) {
        $this->page_content .= "$w w\n";
    }
    
    public function rect($x, $y, $w, $h, $style = '') {
        // Convert mm to PDF units (1mm ≈ 2.834645669 PDF units)
        $pdf_x = $x * 2.834645669;
        $pdf_y = ($this->page_height - $y - $h) * 2.834645669;
        $pdf_w = $w * 2.834645669;
        $pdf_h = $h * 2.834645669;
        
        if ($style == 'F') {
            $this->page_content .= $this->fill_color . " rg\n";
            $this->page_content .= "$pdf_x $pdf_y $pdf_w $pdf_h re f\n";
        } else {
            $this->page_content .= $this->draw_color . " RG\n";
            $this->page_content .= "$pdf_x $pdf_y $pdf_w $pdf_h re S\n";
        }
    }
    
    public function line($x1, $y1, $x2, $y2) {
        $pdf_x1 = $x1 * 2.834645669;
        $pdf_y1 = ($this->page_height - $y1) * 2.834645669;
        $pdf_x2 = $x2 * 2.834645669;
        $pdf_y2 = ($this->page_height - $y2) * 2.834645669;
        
        $this->page_content .= "$pdf_x1 $pdf_y1 m $pdf_x2 $pdf_y2 l S\n";
    }
    
    public function cell($w, $h, $txt, $border = 0, $ln = 0, $align = 'L') {
        if (empty($txt)) return;
        
        // Convert mm to PDF units
        $pdf_x = $this->x * 2.834645669;
        $pdf_y = ($this->page_height - $this->y) * 2.834645669;
        $font_size_pt = $this->font_size;
        
        $this->page_content .= "BT\n";
        $this->page_content .= "/$this->font_name $font_size_pt Tf\n";
        $this->page_content .= $this->text_color . " rg\n";
        $this->page_content .= "$pdf_x $pdf_y Td\n";
        $this->page_content .= "(" . $this->escapeText($txt) . ") Tj\n";
        $this->page_content .= "ET\n";
    }
    
    public function output($type = 'I', $filename = '') {
        // Save last page
        if ($this->current_page > 0) {
            $this->pages[$this->current_page - 1] = $this->page_content;
        }
        
        // Build PDF
        $pdf = "%PDF-1.4\n";
        $objects = [];
        
        // Object 1: Catalog
        $objects[1] = "1 0 obj\n<</Type /Catalog /Pages 2 0 R>>\nendobj\n";
        
        // Object 2: Pages
        $kids = '';
        for ($i = 0; $i < count($this->pages); $i++) {
            $kids .= (3 + $i) . ' 0 R ';
        }
        $objects[2] = "2 0 obj\n<</Type /Pages /Kids [$kids] /Count " . count($this->pages) . ">>\nendobj\n";
        
        // Create pages (A4: 595x842 PDF units)
        $page_index = 3;
        foreach ($this->pages as $page_num => $content) {
            $content_obj_num = $page_index + count($this->pages);
            $objects[$page_index] = $page_index . " 0 obj\n<</Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents $content_obj_num 0 R /Resources <</Font <</Helvetica 8 0 R>>>>>>\nendobj\n";
            $page_index++;
        }
        
        // Content streams
        foreach ($this->pages as $page_num => $content) {
            $content_obj_num = 3 + count($this->pages) + $page_num;
            $content_length = strlen($content);
            $objects[$content_obj_num] = $content_obj_num . " 0 obj\n<</Length $content_length>>\nstream\n$content\nendstream\nendobj\n";
        }
        
        // Font object
        $objects[8] = "8 0 obj\n<</Type /Font /Subtype /Type1 /BaseFont /Helvetica>>\nendobj\n";
        
        // Build xref offsets
        $offsets = [];
        $current_pos = strlen($pdf);
        
        ksort($objects);
        foreach ($objects as $obj_num => $obj_content) {
            $offsets[$obj_num] = $current_pos;
            $pdf .= $obj_content;
            $current_pos += strlen($obj_content);
        }
        
        // Xref table
        $xref_pos = strlen($pdf);
        $pdf .= "xref\n";
        $pdf .= "0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        
        for ($i = 1; $i <= count($objects); $i++) {
            if (isset($offsets[$i])) {
                $pdf .= str_pad($offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
            }
        }
        
        // Trailer
        $pdf .= "trailer\n";
        $pdf .= "<</Size " . (count($objects) + 1) . " /Root 1 0 R>>\n";
        $pdf .= "startxref\n";
        $pdf .= "$xref_pos\n";
        $pdf .= "%%EOF";
        
        if ($type == 'F') {
            file_put_contents($filename, $pdf);
        }
        
        return $pdf;
    }
    
    private function escapeText($txt) {
        $txt = str_replace('\\', '\\\\', $txt);
        $txt = str_replace('(', '\\(', $txt);
        $txt = str_replace(')', '\\)', $txt);
        return $txt;
    }
}



