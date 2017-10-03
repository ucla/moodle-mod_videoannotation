<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    mod_videoannotation
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Converts to and from JSON format.
 *
 * JSON (JavaScript Object Notation) is a lightweight data-interchange
 * format. It is easy for humans to read and write. It is easy for machines
 * to parse and generate. It is based on a subset of the JavaScript
 * Programming Language, Standard ECMA-262 3rd Edition - December 1999.
 * This feature can also be found in  Python. JSON is a text format that is
 * completely language independent but uses conventions that are familiar
 * to programmers of the C-family of languages, including C, C++, C#, Java,
 * JavaScript, Perl, TCL, and many others. These properties make JSON an
 * ideal data-interchange language.
 *
 * This package provides a simple encoder and decoder for JSON notation. It
 * is intended for use with client-side Javascript applications that make
 * use of HTTPRequest to perform server communication functions - data can
 * be encoded into JSON notation for use in a client-side javascript, or
 * decoded from incoming Javascript requests. JSON format is native to
 * Javascript, and can be directly eval()'ed with no further parsing
 * overhead
 *
 * All strings should be in ASCII or UTF-8 format!
 *
 * LICENSE: Redistribution and use in source and binary forms, with or
 * without modification, are permitted provided that the following
 * conditions are met: Redistributions of source code must retain the
 * above copyright notice, this list of conditions and the following
 * disclaimer. Redistributions in binary form must reproduce the above
 * copyright notice, this list of conditions and the following disclaimer
 * in the documentation and/or other materials provided with the
 * distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN
 * NO EVENT SHALL CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS
 * OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR
 * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
 * USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * @category
 * @package     Services_JSON
 * @author      Michal Migurski <mike-json@teczno.com>
 * @author      Matt Knapp <mdknapp[at]gmail[dot]com>
 * @author      Brett Stimmerman <brettstimmerman[at]gmail[dot]com>
 * @copyright   2005 Michal Migurski
 * @version     CVS: $Id: JSON.php,v 1.31 2006/06/28 05:54:17 migurski Exp $
 * @license     http://www.opensource.org/licenses/bsd-license.php
 * @link        http://pear.php.net/pepr/pepr-proposal-show.php?id=198
 */

/**
 * Marker constant for Services_JSON::decode(), used to flag stack state
 */
define('SERVICES_JSON_SLICE',   1);

/**
 * Marker constant for Services_JSON::decode(), used to flag stack state
 */
define('SERVICES_JSON_IN_STR',  2);

/**
 * Marker constant for Services_JSON::decode(), used to flag stack state
 */
define('SERVICES_JSON_IN_ARR',  3);

/**
 * Marker constant for Services_JSON::decode(), used to flag stack state
 */
define('SERVICES_JSON_IN_OBJ',  4);

/**
 * Marker constant for Services_JSON::decode(), used to flag stack state
 */
define('SERVICES_JSON_IN_CMT', 5);

/**
 * Behavior switch for Services_JSON::decode()
 */
define('SERVICES_JSON_LOOSE_TYPE', 16);

/**
 * Behavior switch for Services_JSON::decode()
 */
define('SERVICES_JSON_SUPPRESS_ERRORS', 32);

/**
 * Converts to and from JSON format.
 *
 * Brief example of use:
 *
 * <code>
 * // create a new instance of Services_JSON
 * $json = new Services_JSON();
 *
 * // convert a complexe value to JSON notation, and send it to the browser
 * $value = array('foo', 'bar', array(1, 2, 'baz'), array(3, array(4)));
 * $output = $json->encode($value);
 *
 * print($output);
 * // prints: ["foo","bar",[1,2,"baz"],[3,[4]]]
 *
 * // accept incoming POST data, assumed to be in JSON notation
 * $input = file_get_contents('php://input', 1000000);
 * $value = $json->decode($input);
 * </code>
 */

class services_json
{
    /**
     * constructs a new JSON instance
     *
     * @param    int     $use    object behavior flags; combine with boolean-OR
     *
     *                           possible values:
     *                           - SERVICES_JSON_LOOSE_TYPE:  loose typing.
     *                                   "{...}" syntax creates associative arrays
     *                                   instead of objects in decode().
     *                           - SERVICES_JSON_SUPPRESS_ERRORS:  error suppression.
     *                                   Values which can't be encoded (e.g. resources)
     *                                   appear as NULL instead of throwing errors.
     *                                   By default, a deeply-nested resource will
     *                                   bubble up with an error, so all return values
     *                                   from encode() should be checked with isError()
     */
    public function _construct($use = 0) {
        $this->use = $use;
    }

    /**
     * convert a string from one UTF-16 char to one UTF-8 char
     *
     * Normally should be handled by mb_convert_encoding, but
     * provides a slower PHP-only method for installations
     * that lack the multibye string extension.
     *
     * @param    string  $utf16  UTF-16 character
     * @return   string  UTF-8 character
     * @access   private
     */
    public function utf162utf8($utf16) {
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($utf16, 'UTF-8', 'UTF-16');
        }

        $bytes = (ord($utf16{0}) << 8) | ord($utf16{1});

        switch(true) {
            case ((0x7F & $bytes) == $bytes):
                // This case should never be reached, because we are in ASCII range.
                // See: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
                return chr(0x7F & $bytes);

            case (0x07FF & $bytes) == $bytes:
                // Return a 2-byte UTF-8 character.
                // See: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
                return chr(0xC0 | (($bytes >> 6) & 0x1F))
                     . chr(0x80 | ($bytes & 0x3F));

            case (0xFFFF & $bytes) == $bytes:
                // Return a 3-byte UTF-8 character.
                // See: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
                return chr(0xE0 | (($bytes >> 12) & 0x0F))
                     . chr(0x80 | (($bytes >> 6) & 0x3F))
                     . chr(0x80 | ($bytes & 0x3F));
        }

        return '';
    }

    /**
     * convert a string from one UTF-8 char to one UTF-16 char
     *
     * Normally should be handled by mb_convert_encoding, but
     * provides a slower PHP-only method for installations
     * that lack the multibye string extension.
     *
     * @param    string  $utf8   UTF-8 character
     * @return   string  UTF-16 character
     * @access   private
     */
    public function utf82utf16($utf8) {
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($utf8, 'UTF-16', 'UTF-8');
        }

        switch(strlen($utf8)) {
            case 1:
                // This case should never be reached, because we are in ASCII range.
                // See: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
                return $utf8;

            case 2:
                // Return a UTF-16 character from a 2-byte UTF-8 char.
                // See: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
                return chr(0x07 & (ord($utf8{0}) >> 2))
                     . chr((0xC0 & (ord($utf8{0}) << 6)) |
                          (0x3F & ord($utf8{1})));

            case 3:
                // Return a UTF-16 character from a 3-byte UTF-8 char.
                // See: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
                return chr((0xF0 & (ord($utf8{0}) << 4)) |
                          (0x0F & (ord($utf8{1}) >> 2)))
                     . chr((0xC0 & (ord($utf8{1}) << 6)) |
                          (0x7F & ord($utf8{2})));
        }

        return '';
    }

    /**
     * encodes an arbitrary variable into JSON format
     *
     * @param    mixed   $var    any number, boolean, string, array, or object to be encoded.
     *                           see argument 1 to Services_JSON() above for array-parsing behavior.
     *                           if var is a strng, note that encode() always expects it
     *                           to be in ASCII or UTF-8 format!
     *
     * @return   mixed   JSON string representation of input var or an error if a problem occurs
     * @access   public
     */
    public function encode($var) {
        switch (gettype($var)) {
            case 'boolean':
                return $var ? 'true' : 'false';

            case 'NULL':
                return 'null';

            case 'integer':
                return (int) $var;

            case 'double':
            case 'float':
                return (float) $var;

            case 'string':
                // STRINGS ARE EXPECTED TO BE IN ASCII OR UTF-8 FORMAT.
                $ascii = '';
                $strlenvar = strlen($var);

                // Iterate over every character in the string, escaping with a slash or encoding to UTF-8 where necessary.
                for ($c = 0; $c < $strlenvar; ++$c) {

                    $ordvarc = ord($var{$c});

                    switch (true) {
                        case $ordvarc == 0x08:
                            $ascii .= '\b';
                            break;
                        case $ordvarc == 0x09:
                            $ascii .= '\t';
                            break;
                        case $ordvarc == 0x0A:
                            $ascii .= '\n';
                            break;
                        case $ordvarc == 0x0C:
                            $ascii .= '\f';
                            break;
                        case $ordvarc == 0x0D:
                            $ascii .= '\r';
                            break;

                        case $ordvarc == 0x22:
                        case $ordvarc == 0x2F:
                        case $ordvarc == 0x5C:
                            $ascii .= '\\'.$var{$c};
                            break;

                        case (($ordvarc >= 0x20) && ($ordvarc <= 0x7F)):
                            // Characters U-00000000 - U-0000007F (same as ASCII).
                            $ascii .= $var{$c};
                            break;

                        case (($ordvarc & 0xE0) == 0xC0):
                            // Characters U-00000080 - U-000007FF, mask 110XXXXX.
                            // See http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
                            $char = pack('C*', $ordvarc, ord($var{$c + 1}));
                            $c += 1;
                            $utf16 = $this->utf82utf16($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;

                        case (($ordvarc & 0xF0) == 0xE0):
                            // Characters U-00000800 - U-0000FFFF, mask 1110XXXX.
                            // See http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
                            $char = pack('C*', $ordvarc,
                                         ord($var{$c + 1}),
                                         ord($var{$c + 2}));
                            $c += 2;
                            $utf16 = $this->utf82utf16($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;

                        case (($ordvarc & 0xF8) == 0xF0):
                            // Characters U-00010000 - U-001FFFFF, mask 11110XXX.
                            // See http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
                            $char = pack('C*', $ordvarc,
                                         ord($var{$c + 1}),
                                         ord($var{$c + 2}),
                                         ord($var{$c + 3}));
                            $c += 3;
                            $utf16 = $this->utf82utf16($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;

                        case (($ordvarc & 0xFC) == 0xF8):
                            // Characters U-00200000 - U-03FFFFFF, mask 111110XX.
                            // See http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
                            $char = pack('C*', $ordvarc,
                                         ord($var{$c + 1}),
                                         ord($var{$c + 2}),
                                         ord($var{$c + 3}),
                                         ord($var{$c + 4}));
                            $c += 4;
                            $utf16 = $this->utf82utf16($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;

                        case (($ordvarc & 0xFE) == 0xFC):
                            // Characters U-04000000 - U-7FFFFFFF, mask 1111110X.
                            // See http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
                            $char = pack('C*', $ordvarc,
                                         ord($var{$c + 1}),
                                         ord($var{$c + 2}),
                                         ord($var{$c + 3}),
                                         ord($var{$c + 4}),
                                         ord($var{$c + 5}));
                            $c += 5;
                            $utf16 = $this->utf82utf16($char);
                            $ascii .= sprintf('\u%04s', bin2hex($utf16));
                            break;
                    }
                }

                return '"'.$ascii.'"';

            case 'array':
                /*
                 * As per JSON spec if any array key is not an integer
                 * we must treat the the whole array as an object. We
                 * also try to catch a sparsely populated associative
                 * array with numeric keys here because some JS engines
                 * will create an array with empty indexes up to
                 * max_index which can cause memory issues and because
                 * the keys, which may be relevant, will be remapped
                 * otherwise.
                 *
                 * As per the ECMA and JSON specification an object may
                 * have any string as a property. Unfortunately due to
                 * a hole in the ECMA specification if the key is a
                 * ECMA reserved word or starts with a digit the
                 * parameter is only accessible using ECMAScript's
                 * bracket notation.
                 */

                // Treat as a JSON object.
                if (is_array($var) && count($var) && (array_keys($var) !== range(0, count($var) - 1))) {
                    $properties = array_map(array($this, 'name_value'),
                                            array_keys($var),
                                            array_values($var));

                    foreach ($properties as $property) {
                        if (self::isError($property)) {
                            return $property;
                        }
                    }

                    return '{' . join(',', $properties) . '}';
                }

                // Treat it like a regular array.
                $elements = array_map(array($this, 'encode'), $var);

                foreach ($elements as $element) {
                    if (self::isError($element)) {
                        return $element;
                    }
                }

                return '[' . join(',', $elements) . ']';

            case 'object':
                $vars = get_object_vars($var);

                $properties = array_map(array($this, 'name_value'),
                                        array_keys($vars),
                                        array_values($vars));

                foreach ($properties as $property) {
                    if (self::isError($property)) {
                        return $property;
                    }
                }

                return '{' . join(',', $properties) . '}';

            default:
                if ($this->use & SERVICES_JSON_SUPPRESS_ERRORS) {
                    return 'null';
                } else {
                    return new Services_JSON_Error(gettype($var)." can not be encoded as JSON string");
                }
        }
    }

    /**
     * array-walking function for use in generating JSON-formatted name-value pairs
     *
     * @param    string  $name   name of key to use
     * @param    mixed   $value  reference to an array element to be encoded
     *
     * @return   string  JSON-formatted name-value pair, like '"name":value'
     * @access   private
     */
    public function name_value($name, $value) {
        $encodedvalue = $this->encode($value);

        if (self::isError($encodedvalue)) {
            return $encodedvalue;
        }

        return $this->encode(strval($name)) . ':' . $encodedvalue;
    }

    /**
     * reduce a string by removing leading and trailing comments and whitespace
     *
     * @param    $str    string      string value to strip of comments and whitespace
     *
     * @return   string  string value stripped of comments and whitespace
     * @access   private
     */
    public function reduce_string($str) {
        $str = preg_replace(array(

                // Eliminate single line comments in '// ...' form.
                '#^\s*//(.+)$#m',

                // Eliminate multi-line comments in '/* ... */' form, at start of string.
                '#^\s*/\*(.+)\*/#Us',

                // Eliminate multi-line comments in '/* ... */' form, at end of string.
                '#/\*(.+)\*/\s*$#Us'

            ), '', $str);

        // Eliminate extraneous space.
        return trim($str);
    }

    /**
     * decodes a JSON string into appropriate variable
     *
     * @param    string  $str    JSON-formatted string
     *
     * @return   mixed   number, boolean, string, array, or object
     *                   corresponding to given JSON input string.
     *                   See argument 1 to Services_JSON() above for object-output behavior.
     *                   Note that decode() always returns strings
     *                   in ASCII or UTF-8 format!
     * @access   public
     */
    public function decode($str) {
        $str = $this->reduce_string($str);

        switch (strtolower($str)) {
            case 'true':
                return true;

            case 'false':
                return false;

            case 'null':
                return null;

            default:
                $m = array();

                if (is_numeric($str)) {
                    // Return float or int, as appropriate.
                    return ((float)$str == (integer)$str) ? (integer)$str : (float)$str;

                } else if (preg_match('/^("|\').*(\1)$/s', $str, $m) && $m[1] == $m[2]) {
                    // Strings returned in UTF-8 format.
                    $delim = substr($str, 0, 1);
                    $chrs = substr($str, 1, -1);
                    $utf8 = '';
                    $strlenchrs = strlen($chrs);

                    for ($c = 0; $c < $strlenchrs; ++$c) {

                        $substrchrsc2 = substr($chrs, $c, 2);
                        $ordchrsc = ord($chrs{$c});

                        switch (true) {
                            case $substrchrsc2 == '\b':
                                $utf8 .= chr(0x08);
                                ++$c;
                                break;
                            case $substrchrsc2 == '\t':
                                $utf8 .= chr(0x09);
                                ++$c;
                                break;
                            case $substrchrsc2 == '\n':
                                $utf8 .= chr(0x0A);
                                ++$c;
                                break;
                            case $substrchrsc2 == '\f':
                                $utf8 .= chr(0x0C);
                                ++$c;
                                break;
                            case $substrchrsc2 == '\r':
                                $utf8 .= chr(0x0D);
                                ++$c;
                                break;

                            case $substrchrsc2 == '\\"':
                            case $substrchrsc2 == '\\\'':
                            case $substrchrsc2 == '\\\\':
                            case $substrchrsc2 == '\\/':
                                if (($delim == '"' && $substrchrsc2 != '\\\'') ||
                                   ($delim == "'" && $substrchrsc2 != '\\"')) {
                                    $utf8 .= $chrs{++$c};
                                }
                                break;

                            case preg_match('/\\\u[0-9A-F]{4}/i', substr($chrs, $c, 6)):
                                // Single, escaped unicode character.
                                $utf16 = chr(hexdec(substr($chrs, ($c + 2), 2)))
                                       . chr(hexdec(substr($chrs, ($c + 4), 2)));
                                $utf8 .= $this->utf162utf8($utf16);
                                $c += 5;
                                break;

                            case ($ordchrsc >= 0x20) && ($orchrsc <= 0x7F):
                                $utf8 .= $chrs{$c};
                                break;

                            case ($ordchrsc & 0xE0) == 0xC0:
                                // Characters U-00000080 - U-000007FF, mask 110XXXXX.
                                // See http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
                                $utf8 .= substr($chrs, $c, 2);
                                ++$c;
                                break;

                            case ($ordchrsc & 0xF0) == 0xE0:
                                // Characters U-00000800 - U-0000FFFF, mask 1110XXXX.
                                // See http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
                                $utf8 .= substr($chrs, $c, 3);
                                $c += 2;
                                break;

                            case ($ordchrsc & 0xF8) == 0xF0:
                                // Characters U-00010000 - U-001FFFFF, mask 11110XXX.
                                // See http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
                                $utf8 .= substr($chrs, $c, 4);
                                $c += 3;
                                break;

                            case ($ordchrsc & 0xFC) == 0xF8:
                                // Characters U-00200000 - U-03FFFFFF, mask 111110XX.
                                // See http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
                                $utf8 .= substr($chrs, $c, 5);
                                $c += 4;
                                break;

                            case ($ordchrsc & 0xFE) == 0xFC:
                                // Characters U-04000000 - U-7FFFFFFF, mask 1111110X.
                                // See http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8.
                                $utf8 .= substr($chrs, $c, 6);
                                $c += 5;
                                break;
                        }
                    }
                    return $utf8;

                } else if (preg_match('/^\[.*\]$/s', $str) || preg_match('/^\{.*\}$/s', $str)) {
                    // Array, or object notation.

                    if ($str{0} == '[') {
                        $stk = array(SERVICES_JSON_IN_ARR);
                        $arr = array();
                    } else {
                        if ($this->use & SERVICES_JSON_LOOSE_TYPE) {
                            $stk = array(SERVICES_JSON_IN_OBJ);
                            $obj = array();
                        } else {
                            $stk = array(SERVICES_JSON_IN_OBJ);
                            $obj = new stdClass();
                        }
                    }

                    array_push($stk, array('what'  => SERVICES_JSON_SLICE,
                                           'where' => 0,
                                           'delim' => false));

                    $chrs = substr($str, 1, -1);
                    $chrs = $this->reduce_string($chrs);

                    if ($chrs == '') {
                        if (reset($stk) == SERVICES_JSON_IN_ARR) {
                            return $arr;

                        } else {
                            return $obj;

                        }
                    }

                    $strlenchrs = strlen($chrs);

                    for ($c = 0; $c <= $strlenchrs; ++$c) {

                        $top = end($stk);
                        $substrchrsc2 = substr($chrs, $c, 2);

                        if (($c == $strlenchrs) || (($chrs{$c} == ',') && ($top['what'] == SERVICES_JSON_SLICE))) {
                            // Found a comma that is not inside a string, array, etc.
                            // ... or we've reached the end of the character list.
                            $slice = substr($chrs, $top['where'], ($c - $top['where']));
                            array_push($stk, array('what' => SERVICES_JSON_SLICE, 'where' => ($c + 1), 'delim' => false));

                            if (reset($stk) == SERVICES_JSON_IN_ARR) {
                                // We are in an array, so just push an element onto the stack.
                                array_push($arr, $this->decode($slice));

                            } else if (reset($stk) == SERVICES_JSON_IN_OBJ) {
                                // We are in an object, so figure out the property name.
                                // ... and set an element in an associative array, for now.
                                $parts = array();

                                if (preg_match('/^\s*(["\'].*[^\\\]["\'])\s*:\s*(\S.*),?$/Uis', $slice, $parts)) {
                                    // Name:value pair, where "name" is in quotations.
                                    $key = $this->decode($parts[1]);
                                    $val = $this->decode($parts[2]);

                                    if ($this->use & SERVICES_JSON_LOOSE_TYPE) {
                                        $obj[$key] = $val;
                                    } else {
                                        $obj->$key = $val;
                                    }
                                } else if (preg_match('/^\s*(\w+)\s*:\s*(\S.*),?$/Uis', $slice, $parts)) {
                                    // Name:value pair, where name is unquoted.
                                    $key = $parts[1];
                                    $val = $this->decode($parts[2]);

                                    if ($this->use & SERVICES_JSON_LOOSE_TYPE) {
                                        $obj[$key] = $val;
                                    } else {
                                        $obj->$key = $val;
                                    }
                                }
                            }

                        } else if ((($chrs{$c} == '"') || ($chrs{$c} == "'")) && ($top['what'] != SERVICES_JSON_IN_STR)) {
                            // Found a quote, and we are not inside a string.
                            array_push($stk, array('what' => SERVICES_JSON_IN_STR, 'where' => $c, 'delim' => $chrs{$c}));

                        } else if (($chrs{$c} == $top['delim']) &&
                                 ($top['what'] == SERVICES_JSON_IN_STR) &&
                                 ((strlen(substr($chrs, 0, $c)) - strlen(rtrim(substr($chrs, 0, $c), '\\'))) % 2 != 1)) {
                            // Found a quote, we're in a string, and it's not escaped.
                            // Not escaped becase there is NOT an odd number of backslashes at the end of the string so far.
                            array_pop($stk);

                        } else if (($chrs{$c} == '[') &&
                                 in_array($top['what'], array(SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ))) {
                            // Found a left-bracket, and we are in an array, object, or slice.
                            array_push($stk, array('what' => SERVICES_JSON_IN_ARR, 'where' => $c, 'delim' => false));

                        } else if (($chrs{$c} == ']') && ($top['what'] == SERVICES_JSON_IN_ARR)) {
                            // Found a right-bracket, and we're in an array.
                            array_pop($stk);

                        } else if (($chrs{$c} == '{') &&
                                 in_array($top['what'], array(SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ))) {
                            // Found a left-brace, and we are in an array, object, or slice.
                            array_push($stk, array('what' => SERVICES_JSON_IN_OBJ, 'where' => $c, 'delim' => false));

                        } else if (($chrs{$c} == '}') && ($top['what'] == SERVICES_JSON_IN_OBJ)) {
                            // Found a right-brace, and we're in an object.
                            array_pop($stk);

                        } else if (($substrchrsc2 == '/*') &&
                                 in_array($top['what'], array(SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ))) {
                            // Found a comment start, and we are in an array, object, or slice.
                            array_push($stk, array('what' => SERVICES_JSON_IN_CMT, 'where' => $c, 'delim' => false));
                            $c++;

                        } else if (($substrchrsc2 == '*/') && ($top['what'] == SERVICES_JSON_IN_CMT)) {
                            // Found a comment end, and we're in one now.
                            array_pop($stk);
                            $c++;

                            for ($i = $top['where']; $i <= $c; ++$i) {
                                $chrs = substr_replace($chrs, ' ', $i, 1);
                            }
                        }
                    }

                    if (reset($stk) == SERVICES_JSON_IN_ARR) {
                        return $arr;

                    } else if (reset($stk) == SERVICES_JSON_IN_OBJ) {
                        return $obj;
                    }
                }
        }
    }

    /**
     * @todo Ultimately, this should just call PEAR::isError()
     */
    public function is_error($data, $code = null) {
        if (class_exists('pear')) {
            return PEAR::is_error($data, $code);
        } else if (is_object($data) && (get_class($data) == 'services_json_error' ||
                                 is_subclass_of($data, 'services_json_error'))) {
            return true;
        }
        return false;
    }
}

if (class_exists('PEAR_Error')) {
    class services_json_error extends PEAR_Error
    {
        public function _construct($message = 'unknown error', $code = null, $mode = null, $options = null, $userinfo = null) {
            parent::PEAR_Error($message, $code, $mode, $options, $userinfo);
        }
    }
}
