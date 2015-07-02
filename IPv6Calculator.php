<?php

/**
 * Created by PhpStorm.
 * User: adriar
 * Date: 7/2/15
 * Time: 1:38 PM
 */
class IPv6Calculator
{

    const ADDRESS_EMPTY = 128;
    const ADDRESS_INVALID = 129;
    const ADDRESS_NOT_STRING = 130;
    const ADDRESS_LONG_TOO = 131;
    const SUBNET_UNDEFINED = 256;
    const ADDRESS_NOT_SEGMENTED = 132;
    const ADDRESS_SEGMENTS_COUNT = 133;
    const QUERY_EMPTY = 64;
    const QUERY_INVALID = 65;
    const QUERY_TOO_LONG = 66;
    const PREFIX_NOT_NUMERIC = 197;
    const PREFIX_EMPTY = 196;
    const PREFIX_RANGE = 198;
    const PREFIX_INVALID = 199;

    protected $errors = array();
    protected $segments = array();
    protected $ip;
    protected $prefix;
    protected $network_range = array();

    /**
     * @param $query
     * @return bool|int
     * @throws Exception
     */
    public function calc($query)
    {
        $this->reset();

        if (!$this->isValidQuery($query)) {
            return static::QUERY_INVALID;
        }

        if (!$this->isValidAddress()) {
            return $this->errors[] = static::ADDRESS_INVALID;
        }

        if (!$this->isValidPrefix()) {
            return $this->errors[] = static::PREFIX_INVALID;
        }

        return $this->calcSubnet();
    }

    /**
     * Reset all properties for this class
     *
     * @return $this
     */
    public function reset() {
        $this->errors = array();
        $this->segments = array();
        $this->ip = null;
        $this->prefix = null;
        $this->network_range = array();

        return $this;
    }

    /**
     * Returns an string with the logged error messages
     *
     * @return string
     * @throws Exception
     */
    public function getErrorMessages()
    {
        $msg = '';
        foreach ($this->errors as $code) {
            $msg .= $this->getErrorMessage($code) . PHP_EOL;
        }

        return $msg;
    }

    /**
     * Returns an array with the calculated NetworkRange
     * @return array
     */
    public function getNetworkRange() {
        return $this->network_range;
    }

    /**
     * Returns the error message string based on error ID
     *
     * @param $id
     * @return mixed
     * @throws Exception
     */
    protected function getErrorMessage($id)
    {
        $messages = array(
            static::QUERY_EMPTY => 'Query string is empty',
            static::QUERY_INVALID => 'Query string is invalid',
            static::QUERY_TOO_LONG => 'Query string is too long',
            static::ADDRESS_INVALID => 'IP address is invalid',
            static::ADDRESS_EMPTY => 'IP address value is empty',
            static::ADDRESS_NOT_STRING => 'IP address value is not a string',
            static::ADDRESS_LONG_TOO => 'IP address string is too long',
            static::ADDRESS_NOT_SEGMENTED => 'IP address has not segments',
            static::ADDRESS_SEGMENTS_COUNT => 'IP address has too many segments',
            static::PREFIX_EMPTY => 'Prefix is empty',
            static::PREFIX_NOT_NUMERIC => 'Prefix is not numeric',
            static::PREFIX_RANGE => 'Prefix is not a valid range',
            static::PREFIX_INVALID => 'Prefix is invalid'
        );

        if (isset($messages[$id])) {
            return $messages[$id];
        }

        throw new \Exception('Error messages is not defined');
    }

    /**
     * Validates basic format for the submitted query string and sets IP and Prefix property values
     *
     * @param $string
     * @return bool
     */
    protected function isValidQuery($string)
    {
        if (empty($string)) {
            $this->errors[] = static::QUERY_EMPTY;
            return false;
        } elseif (strlen($string) > (4 * 8) + 11) {
            $this->errors[] = static::QUERY_TOO_LONG;
            return false;
        }

        $list = explode('/', $string);
        if (count($list) !== 2) {
            $this->errors[] = static::QUERY_INVALID;
            return false;
        }

        $this->ip = $list[0];
        $this->prefix = $list[1];

        return true;
    }

    /**
     * Validates the IP property value if it is a valid IPv6 string
     *
     * @return bool
     * @throws Exception
     */
    protected function isValidAddress()
    {
        $string = $this->ip;
        if (empty($string)) {
            $this->errors[] = static::ADDRESS_EMPTY;
            return false;
        } elseif (!is_string($string)) {
            $this->errors[] = static::ADDRESS_NOT_STRING;
            return false;
        } elseif (strlen($string) > ((4 * 8) + 7)) {
            $this->errors[] = static::ADDRESS_LONG_TOO;
            return false;
        }

        if ( $this->validateAddressFormat($string) ) {
            $this->ip = implode(':', $this->segments);
            return true;
        }

        return false;
    }

    /**
     * Validates the IP address format and splits in segments into groups
     *
     * @param $address
     * @return bool
     * @throws Exception
     */
    protected function validateAddressFormat($address)
    {
        $segments = $this->splitAddressString($address);
        if ($segments === false) {
            return false;
        } elseif (!is_array($segments)) {
            throw new \Exception('Split address did not return an valid format');
        }

        $this->segments = $segments;

        return true;
    }

    /**
     * Validates the Prefix property value if it is a valid CIDR notation
     *
     * @return bool
     */
    protected function isValidPrefix()
    {
        if (empty($this->prefix)) {
            $this->errors[] = static::PREFIX_EMPTY;
            return false;
        } elseif (!is_numeric($this->prefix)) {
            $this->errors[] = static::PREFIX_NOT_NUMERIC;
            return false;
        } elseif ($this->prefix < 1 || $this->prefix > 128) {
            $this->errors[] = static::PREFIX_RANGE;
            return false;
        }

        return true;
    }

    /**
     * Calculates the network range based on the IP and Prefix
     *
     * @return bool
     * @throws Exception
     */
    protected function calcSubnet() {
        if ( !is_numeric($this->prefix) ) {
            throw new \Exception('Prefix is not available!');
        }

        $group = (int) floor($this->prefix / 16);
        $mod = $this->prefix % 16;
        $segment = $this->segments[$group];

        $seg_bin = str_pad(base_convert($segment, 16, 2), 16, '0', STR_PAD_LEFT);
        $seg_sub = substr($seg_bin, 0, $mod);
        $hex_min = base_convert(str_pad($seg_sub, 16, '0', STR_PAD_RIGHT), 2, 16);
        $hex_max = base_convert(str_pad($seg_sub, 16, '1', STR_PAD_RIGHT), 2, 16);

        $segments_min = $segments_max = $this->segments;

        $segments_min[$group] = str_pad($hex_min, 4, '0', STR_PAD_LEFT);
        $segments_max[$group] = str_pad($hex_max, 4, '0', STR_PAD_LEFT);

        for ($i = ($group+1); $i < 8; $i++) {
            $segments_min[$i] = '0000';
            $segments_max[$i] = 'ffff';
        }

        $this->network_range['min'] = implode(':', $segments_min);
        $this->network_range['max'] = implode(':', $segments_max);
        $this->network_range['hosts'] = number_format(pow(2, (128-$this->prefix)), 0);

        return true;
    }

    /**
     * Splits the Address String in 8 network groups
     * @param $string
     * @return array|bool
     * @throws Exception
     */
    protected function splitAddressString($string)
    {
        $segments = explode(':', $string);
        if (empty($segments)) {
            $this->errors[] = static::ADDRESS_NOT_SEGMENTED;
            return false;
        } elseif (count($segments) > 8) {
            $this->errors[] = static::ADDRESS_SEGMENTS_COUNT;
            return false;
        }

        $this->normalizeSegments($segments);

        return $segments;
    }

    /**
     * Normalizes given array into 8 complete groups with full 4 char hex string
     *
     * @param $segments
     * @return mixed
     */
    protected function normalizeSegments(&$segments)
    {
        $this->expandSegments($segments);

        foreach ($segments as $i => $segment) {
            if (strlen($segment) !== 4) {
                $segments[$i] = str_pad($segment, 4, '0', STR_PAD_LEFT);
            }
        }

        return $segments;
    }

    /**
     * Expands a given array and segments into 8 groups
     *
     * @param $segments
     * @return array|bool
     * @throws Exception
     */
    protected function expandSegments(&$segments)
    {
        $t = 8;
        if (($c = count($segments)) === $t) {
            return true;
        }

        $diff = $t - $c;
        $newSegments = array();
        foreach ($segments as $i => $segment) {
            if (empty($segment)) {
                for ($i = 0; $i <= $diff; $i++) {
                    $newSegments[] = '0000';
                }
                continue;
            }

            $newSegments[] = $segment;
        }

        if (count($newSegments) !== 8) {
            throw new \Exception('Expanding segments failed for unknown reason');
        }

        $segments = $newSegments;
        unset($newSegments);

        return $segments;
    }

}

// lets run this thing
$calc = new IPv6Calculator();
if ( isset($_REQUEST['cidr']) ) {
    if ($calc->calc($_REQUEST['cidr']) === true) {
        var_dump($calc->getNetworkRange());
    } else {
        echo $calc->getErrorMessages();
    }
} else {
    echo "No Request received";
}
