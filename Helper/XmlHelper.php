<?php
/**
 * PayU MEA PHP SDK
 *
 * @copyright  Copyright (c) 2016 PayU
 * @license    http://opensource.org/licenses/LGPL-3.0  Open Software License (LGPL 3.0)
 * @link       http://www.payu.co.za
 * @link       http://help.payu.co.za/developers
 * @author     Kenneth Onah <kenneth@netcraft-devops.com>
 */

namespace PayU\EasyPlus\Helper;

/**
 * Class XmlHelper
 *
 * Helper Class to parse IPN xml feed to array
 *
 * @package PayU_EasyPlus
 */
class XmlHelper
{
    /**
     * @param string $xml the IPN xm to parse
     *
     * @return array|bool
     */
    public static function parseXMLToArray($xml)
    {
        if (empty($xml))
            return false;

        $data = array();
        $data[$xml['Stage']->getName()] = $xml['Stage']->__toString();
        foreach ($xml as $element) {
            if ($element->children()) {
                foreach ($element as $child) {
                    if ($child->attributes()) {
                        foreach ($child->attributes() as $key => $value) {
                            $data[$element->getName()][$child->getName()][$key] = $value->__toString();
                        }
                    } else {
                        $data[$element->getName()][$child->getName()] = $child->__toString();
                    }
                }
            } else {
                $data[$element->getName()] = $element->__toString();
            }
        }

        return $data;
    }
}
