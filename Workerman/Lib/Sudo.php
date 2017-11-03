<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Workerman\Lib;

use Exception;

/**
 * 拓展类
 */
class Sudo
{
    public static function log_save($path,$r){
        try {
            if (!file_exists($path)) file_put_contents($path, $r);
            else file_put_contents($path, ','.$r, FILE_APPEND);

        } catch(Exception $e) {
            echo 'logLive: ' . $e->getMessage();
        }
    }

    public static function decrypt($txt) {
        $key = '64f1c16ab5af08f8e87d34971868e1eccd70556ea4c9724472e3521b8a4586c9';
        $txt = urldecode(str_replace('_', '%', $txt));
        return self::xxtea_decrypt(base64_decode($txt), $key);
    }

    protected static function xxtea_long2str($v, $w) {
        $len = count($v);
        $n = ($len - 1) << 2;
        if ($w) {
            $m = $v[$len - 1];
            if (($m < $n - 3) || ($m > $n)) return false;
            $n = $m;
        }
        $s = array();
        for ($i = 0; $i < $len; $i++) { $s[$i] = pack("V", $v[$i]); }
        if ($w) return substr(join('', $s), 0, $n);
        else return join('', $s);
    }

    protected static function xxtea_str2long($s, $w) {
        $v = unpack("V*", $s. str_repeat("\0", (4 - strlen($s) % 4) & 3));
        $v = array_values($v);
        if ($w) $v[count($v)] = strlen($s);
        return $v;
    }

    protected static function xxtea_int32($n) {
        while ($n >= 2147483648) $n -= 4294967296;
        while ($n <= -2147483649) $n += 4294967296;
        return (int)$n;
    }

    protected static function xxtea_decrypt($str, $key) {
        if ($str == "") return "";
        $v = self::xxtea_str2long($str, false);
        $k = self::xxtea_str2long($key, false);
        if (count($k) < 4) {
            for ($i = count($k); $i < 4; $i++) {
                $k[$i] = 0;
            }
        }
        $n = count($v) - 1;
        $z = $v[$n];
        $y = $v[0];
        $delta = 0x9E3779B9;
        $q = floor(6 + 52 / ($n + 1));
        $sum = self::xxtea_int32($q * $delta);
        while ($sum != 0) {
            $e = $sum >> 2 & 3;
            for ($p = $n; $p > 0; $p--) {
                $z = $v[$p - 1];
                $mx = self::xxtea_int32((($z >> 5 & 0x07ffffff) ^ $y << 2) + (($y >> 3 & 0x1fffffff) ^ $z << 4)) ^ self::xxtea_int32(($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z));
                $y = $v[$p] = self::xxtea_int32($v[$p] - $mx);
            }
            $z = $v[$n];
            $mx = self::xxtea_int32((($z >> 5 & 0x07ffffff) ^ $y << 2) + (($y >> 3 & 0x1fffffff) ^ $z << 4)) ^ self::xxtea_int32(($sum ^ $y) + ($k[$p & 3 ^ $e] ^ $z));
            $y = $v[0] = self::xxtea_int32($v[0] - $mx);
            $sum = self::xxtea_int32($sum - $delta);
        }
        return self::xxtea_long2str($v, true);
    }

    public static function xn_json_decode($json) {
        return json_decode($json, 1);
    }

    public static function xn_json_encode($arg) {
        $r = '';
        switch (gettype($arg)) {
            case 'array':
                $r = self::is_number_array($arg) ?
                    self::xn_json_number_array_to_string($arg) : self::xn_json_assoc_array_to_string($arg);
                break;
            case 'object':
                return self::xn_json_encode(get_object_vars($arg));
                break;
            case 'integer':
            case 'double':
                $r = is_numeric($arg) ? (string)$arg : 'null';
                break;
            case 'string':
                $r = '"' . strtr($arg, array(
                    "\r"   => '\\r',    "\n"   => '\\n',    "\t"   => '\\t',     "\b"   => '\\b',
                    "\f"   => '\\f',    '\\'   => '\\\\',   '"'    => '\"',
                    "\x00" => '\u0000', "\x01" => '\u0001', "\x02" => '\u0002', "\x03" => '\u0003',
                    "\x04" => '\u0004', "\x05" => '\u0005', "\x06" => '\u0006', "\x07" => '\u0007',
                    "\x08" => '\b',     "\x0b" => '\u000b', "\x0c" => '\f',     "\x0e" => '\u000e',
                    "\x0f" => '\u000f', "\x10" => '\u0010', "\x11" => '\u0011', "\x12" => '\u0012',
                    "\x13" => '\u0013', "\x14" => '\u0014', "\x15" => '\u0015', "\x16" => '\u0016',
                    "\x17" => '\u0017', "\x18" => '\u0018', "\x19" => '\u0019', "\x1a" => '\u001a',
                    "\x1b" => '\u001b', "\x1c" => '\u001c', "\x1d" => '\u001d', "\x1e" => '\u001e',
                    "\x1f" => '\u001f'
                )) . '"';
                break;
            case 'boolean':
                $r = $arg ? 1 : 0;
                break;
            default:
                $r = 'null';
        }
        return $r;
    }

    protected static function xn_json_number_array_to_string($arr) {
        $s = '';
        foreach ($arr as $k=>$v) {
            $s .= ','.self::xn_json_encode($v);
        }
        $s = substr($s, 1);
        $r = '['.$s.']';
        return $r;
    }

    protected static function xn_json_assoc_array_to_string($arr) {
        $s = '';
        foreach ($arr as $k=>$v) {
            $s .= ',"'.$k.'":'.self::xn_json_encode($v);
        }
        $s = substr($s, 1);
        $r = '{'.$s.'}';
        return $r;
    }

    protected static function is_number_array($arr) {
        $i = 0;
        foreach ($arr as $k=>$v) {
            if(!is_numeric($k) || $k != $i++) return FALSE;
        }
        return TRUE;
    }

}
