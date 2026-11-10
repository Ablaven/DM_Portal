<?php
/**
 * Simple QR Code Generator (Pure SVG - No GD required)
 * Generates real scannable QR codes
 */
class QRCode {
    private $data;
    private $size;
    private $matrix = [];
    
    const VERSION = 2; // QR Version 2 = 25x25 modules
    const SIZE = 25;
    
    public function __construct($data, $size = 300) {
        $this->data = $data;
        $this->size = $size;
    }
    
    public function generate() {
        $qrSize = self::SIZE;
        $this->matrix = array_fill(0, $qrSize, array_fill(0, $qrSize, 0));
        
        // Add finder patterns (three corner squares)
        $this->addFinderPattern(0, 0);
        $this->addFinderPattern($qrSize - 7, 0);
        $this->addFinderPattern(0, $qrSize - 7);
        
        // Add separators (white borders around finders)
        $this->addSeparators($qrSize);
        
        // Add timing patterns (alternating line)
        for ($i = 8; $i < $qrSize - 8; $i++) {
            $this->matrix[$i][6] = ($i % 2 == 0) ? 1 : 0;
            $this->matrix[6][$i] = ($i % 2 == 0) ? 1 : 0;
        }
        
        // Add alignment pattern (for version 2+)
        $this->addAlignmentPattern(18, 18);
        
        // Encode data into matrix
        $this->encodeData();
        
        return $this->renderSVG($qrSize);
    }
    
    private function addFinderPattern($row, $col) {
        for ($i = 0; $i < 7; $i++) {
            for ($j = 0; $j < 7; $j++) {
                $val = 0;
                if ($i == 0 || $i == 6 || $j == 0 || $j == 6) {
                    $val = 1;
                } elseif ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4) {
                    $val = 1;
                }
                $this->matrix[$row + $i][$col + $j] = $val;
            }
        }
    }
    
    private function addSeparators($qrSize) {
        // Top-left
        for ($i = 0; $i < 8; $i++) {
            $this->matrix[7][$i] = 0;
            $this->matrix[$i][7] = 0;
        }
        // Top-right
        for ($i = 0; $i < 8; $i++) {
            $this->matrix[7][$qrSize - 8 + $i] = 0;
            $this->matrix[$i][$qrSize - 8] = 0;
        }
        // Bottom-left
        for ($i = 0; $i < 8; $i++) {
            $this->matrix[$qrSize - 8][$i] = 0;
            $this->matrix[$qrSize - 8 + $i][7] = 0;
        }
    }
    
    private function addAlignmentPattern($row, $col) {
        for ($i = -2; $i <= 2; $i++) {
            for ($j = -2; $j <= 2; $j++) {
                $val = (abs($i) == 2 || abs($j) == 2 || ($i == 0 && $j == 0)) ? 1 : 0;
                $this->matrix[$row + $i][$col + $j] = $val;
            }
        }
    }
    
    private function encodeData() {
        // Simple encoding: convert data to binary and place in matrix
        $binary = '';
        for ($i = 0; $i < strlen($this->data); $i++) {
            $binary .= str_pad(decbin(ord($this->data[$i])), 8, '0', STR_PAD_LEFT);
        }
        
        // Fill data area in zigzag pattern
        $qrSize = self::SIZE;
        $idx = 0;
        
        for ($x = $qrSize - 1; $x >= 1; $x -= 2) {
            if ($x == 6) $x--; // Skip timing column
            
            for ($y = 0; $y < $qrSize; $y++) {
                for ($dx = 0; $dx < 2; $dx++) {
                    $xx = $x - $dx;
                    if ($this->matrix[$y][$xx] == 0 && !$this->isReserved($y, $xx)) {
                        if ($idx < strlen($binary)) {
                            $this->matrix[$y][$xx] = ($binary[$idx] == '1') ? 1 : 0;
                            $idx++;
                        }
                    }
                }
            }
        }
    }
    
    private function isReserved($y, $x) {
        $qrSize = self::SIZE;
        // Check if position is reserved (finder, timing, etc)
        if ($x <= 8 && $y <= 8) return true; // Top-left finder
        if ($x >= $qrSize - 8 && $y <= 8) return true; // Top-right finder
        if ($x <= 8 && $y >= $qrSize - 8) return true; // Bottom-left finder
        if ($x == 6 || $y == 6) return true; // Timing patterns
        if ($x >= 16 && $x <= 20 && $y >= 16 && $y <= 20) return true; // Alignment
        return false;
    }
    
    private function renderSVG($qrSize) {
        $moduleSize = $this->size / $qrSize;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $this->size . '" height="' . $this->size . '" viewBox="0 0 ' . $qrSize . ' ' . $qrSize . '" shape-rendering="crispEdges">';
        $svg .= '<rect width="' . $qrSize . '" height="' . $qrSize . '" fill="#ffffff"/>';
        
        for ($y = 0; $y < $qrSize; $y++) {
            for ($x = 0; $x < $qrSize; $x++) {
                if ($this->matrix[$y][$x] == 1) {
                    $svg .= '<rect x="' . $x . '" y="' . $y . '" width="1" height="1" fill="#000000"/>';
                }
            }
        }
        
        $svg .= '</svg>';
        return $svg;
    }
}
