<?php

/**
 * Isotope eCommerce for Contao Open Source CMS
 *
 * Copyright (C) 2009-2014 terminal42 gmbh & Isotope eCommerce Workgroup
 *
 * @package    Isotope
 * @link       http://isotopeecommerce.org
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */

namespace Isotope\Model;

use Haste\Haste;
use Haste\Util\Format;
use Isotope\Isotope;


/**
 * Class Address
 *
 * @property int    id
 * @property int    pid
 * @property string ptable
 * @property string label
 * @property int    store_id
 * @property string gender
 * @property string salutation
 * @property string firstname
 * @property string lastname
 * @property int    dateOfBirth
 * @property string company
 * @property string vat_no
 * @property string street_1
 * @property string street_2
 * @property string street_3
 * @property string postal
 * @property string city
 * @property string subdivision
 * @property string country
 * @property string phone
 * @property string email
 * @property bool   isDefaultShipping
 * @property bool   isDefaultBilling
 */
class Address extends \Model
{

    /**
     * Table
     * @var string
     */
    protected static $strTable = 'tl_iso_address';


    public function __construct(\Database\Result $objResult = null)
    {
        parent::__construct($objResult);

        if (!is_array($GLOBALS['ISO_ADR'])) {
            Haste::getInstance()->call('loadDataContainer', static::$strTable);
            \System::loadLanguageFile('addresses');
        }
    }


    public function __toString()
    {
        return $this->generate();
    }

    /**
     * Return formatted address (hCard)
     * @param array
     * @return string
     */
    public function generate($arrFields = null)
    {
        // We need a country to format the address, use default country if none is available
        $strCountry = $this->country ?: Isotope::getConfig()->country;

        // Use generic format if no country specific format is available
        $strFormat = $GLOBALS['ISO_ADR'][$strCountry] ?: $GLOBALS['ISO_ADR']['generic'];

        $arrTokens  = $this->getTokens($arrFields);
        $strAddress = \String::parseSimpleTokens($strFormat, $arrTokens);

        return $strAddress;
    }

    /**
     * Return this address formatted as text
     * @param array
     * @return string
     * @deprecated
     */
    public function generateText($arrFields = null)
    {
        return strip_tags($this->generate($arrFields));
    }

    /**
     * Return an address formatted with HTML (hCard)
     * @param array
     * @return string
     * @deprecated
     */
    public function generateHtml($arrFields = null)
    {
        return $this->generate($arrFields);
    }

    /**
     * Compile the list of hCard tokens for this address
     * @param   array
     * @return  array
     */
    public function getTokens($arrFields = null)
    {
        global $objPage;

        if (!is_array($arrFields)) {
            $arrFields = Isotope::getConfig()->getBillingFieldsConfig();
        }

        $arrTokens = array('outputFormat' => $objPage->outputFormat);

        foreach ($arrFields as $arrField) {
            $strField = $arrField['value'];

            // Set an empty value for disabled fields, otherwise the token would not be replaced
            if (!$arrField['enabled']) {
                $arrTokens[$strField] = '';
                continue;
            }

            if ($strField == 'subdivision' && $this->subdivision != '') {
                $arrSubdivisions = \Isotope\Backend::getSubdivisions();

                list($country, $subdivion) = explode('-', $this->subdivision);

                $arrTokens['subdivision']      = $arrSubdivisions[strtolower($country)][$this->subdivision];
                $arrTokens['subdivision_abbr'] = $subdivion;

                continue;
            }

            $arrTokens[$strField] = Format::dcaValue(static::$strTable, $strField, $this->$strField);
        }


        /**
         * Generate hCard fields
         * See http://microformats.org/wiki/hcard
         */

        // Set "fn" (full name) to company if no first- and lastname is given
        if ($arrTokens['company'] != '') {
            $fn        = $arrTokens['company'];
            $fnCompany = ' fn';
        } else {
            $fn        = trim($arrTokens['firstname'] . ' ' . $arrTokens['lastname']);
            $fnCompany = '';
        }

        $street = implode(($objPage->outputFormat == 'html' ? '<br>' : '<br />'), array_filter(array($this->street_1, $this->street_2, $this->street_3)));

        $arrTokens += array
        (
            'hcard_fn'               => ($fn ? '<span class="fn">' . $fn . '</span>' : ''),
            'hcard_n'                => (($arrTokens['firstname'] || $arrTokens['lastname']) ? '1' : ''),
            'hcard_honorific_prefix' => ($arrTokens['salutation'] ? '<span class="honorific-prefix">' . $arrTokens['salutation'] . '</span>' : ''),
            'hcard_given_name'       => ($arrTokens['firstname'] ? '<span class="given-name">' . $arrTokens['firstname'] . '</span>' : ''),
            'hcard_family_name'      => ($arrTokens['lastname'] ? '<span class="family-name">' . $arrTokens['lastname'] . '</span>' : ''),
            'hcard_org'              => ($arrTokens['company'] ? '<div class="org' . $fnCompany . '">' . $arrTokens['company'] . '</div>' : ''),
            'hcard_email'            => ($arrTokens['email'] ? '<a href="mailto:' . $arrTokens['email'] . '">' . $arrTokens['email'] . '</a>' : ''),
            'hcard_tel'              => ($arrTokens['phone'] ? '<div class="tel">' . $arrTokens['phone'] . '</div>' : ''),
            'hcard_adr'              => (($street | $arrTokens['city'] || $arrTokens['postal'] || $arrTokens['subdivision'] || $arrTokens['country']) ? '1' : ''),
            'hcard_street_address'   => ($street ? '<div class="street-address">' . $street . '</div>' : ''),
            'hcard_locality'         => ($arrTokens['city'] ? '<span class="locality">' . $arrTokens['city'] . '</span>' : ''),
            'hcard_region'           => ($arrTokens['subdivision'] ? '<span class="region">' . $arrTokens['subdivision'] . '</span>' : ''),
            'hcard_region_abbr'      => ($arrTokens['subdivision_abbr'] ? '<abbr class="region" title="' . $arrTokens['subdivision'] . '">' . $arrTokens['subdivision_abbr'] . '</abbr>' : ''),
            'hcard_postal_code'      => ($arrTokens['postal'] ? '<span class="postal-code">' . $arrTokens['postal'] . '</span>' : ''),
            'hcard_country_name'     => ($arrTokens['country'] ? '<div class="country-name">' . $arrTokens['country'] . '</div>' : ''),
        );

        return $arrTokens;
    }

    /**
     * Find address for member, automatically checking the current store ID and tl_member parent table
     * @param   int
     * @param   array
     * @return  Collection|null
     */
    public static function findForMember($intMember, array $arrOptions = array())
    {
        return static::findBy(array('pid=?', 'ptable=?', 'store_id=?'), array($intMember, 'tl_member', Isotope::getCart()->store_id), $arrOptions);
    }

    /**
     * Find address by ID and member, automatically checking the current store ID and tl_member parent table
     * @param   int
     * @param   int
     * @param   array
     * @return  Address|null
     */
    public static function findOneForMember($intId, $intMember, array $arrOptions = array())
    {
        return static::findOneBy(array('id=?', 'pid=?', 'ptable=?', 'store_id=?'), array($intId, $intMember, 'tl_member', Isotope::getCart()->store_id), $arrOptions);
    }

    /**
     * Find default billing adddress for a member, automatically checking the current store ID and tl_member parent table
     * @param   int
     * @param   array
     * @return  Address|null
     */
    public static function findDefaultBillingForMember($intMember, array $arrOptions = array())
    {
        return static::findOneBy(array('pid=?', 'ptable=?', 'store_id=?', 'isDefaultBilling=?'), array($intMember, 'tl_member', Isotope::getCart()->store_id, '1'), $arrOptions);
    }

    /**
     * Find default shipping adddress for a member, automatically checking the current store ID and tl_member parent table
     * @param   int
     * @param   array
     * @return  Address|null
     */
    public static function findDefaultShippingForMember($intMember, array $arrOptions = array())
    {
        return static::findOneBy(array('pid=?', 'ptable=?', 'store_id=?', 'isDefaultShipping=?'), array($intMember, 'tl_member', Isotope::getCart()->store_id, '1'), $arrOptions);
    }

    /**
     * Create a new address for a member and automatically set default properties
     * @param   int
     * @param   array|null
     * @return  Address
     */
    public static function createForMember($intMember, $arrFill = null)
    {
        $objAddress = new Address();

        $arrData = array(
            'pid'      => $intMember,
            'ptable'   => 'tl_member',
            'tstamp'   => time(),
            'store_id' => Isotope::getCart()->store_id,
        );

        if (!empty($arrFill) && is_array($arrFill) && ($objMember = \MemberModel::findByPk($intMember)) !== null) {

            $arrData = array_intersect_key(
                array_merge(
                    $objMember->row(),
                    $arrData,
                    array(
                         'street_1'    => $objMember->street,

                         // Trying to guess subdivision by country and state
                         'subdivision' => strtoupper($objMember->country . '-' . $objMember->state)
                    )
                ),
                array_flip($arrFill)
            );
        }

        $objAddress->setRow($arrData);

        return $objAddress;
    }
}
