<?php
/**
 * Output Encoding Library  
 * Prevents XSS by safely encoding output
 */

class Encoder {
    /**
     * Encode string for safe HTML output
     * @param string $str The string to encode
     * @return string The encoded string
     */
    public static function html(string $str): string {
        return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Encode string for safe JavaScript output
     * @param mixed $data The data to encode
     * @return string The JSON encoded string
     */
    public static function js($data): string {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_SLASH);
    }
    
    /**
     * Encode string for safe HTML attribute output
     * @param string $str The string to encode
     * @return string The encoded string
     */
    public static function attr(string $str): string {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Encode for URL parameter
     * @param string $str The string to encode
     * @return string The URL encoded string
     */
    public static function url(string $str): string {
        return urlencode($str);
    }
    
    /**
     * Safely encode array for JSON response
     * @param array $data The data to encode
     * @return string The JSON string
     */
    public static function jsonResponse(array $data): string {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

