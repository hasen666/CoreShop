<?php
/**
 * CoreShop
 *
 * LICENSE
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015 Dominik Pfaffenbauer (http://dominik.pfaffenbauer.at)
 * @license    http://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
 */

use CoreShop\Plugin;
use CoreShop\Config;
use CoreShop\Tool;
use CoreShop\Helper\Country;

use CoreShop\Model;

use Pimcore\Controller\Action\Admin;

class CoreShop_Admin_SettingsController extends Admin
{
    public function init() {

        parent::init();

        // check permissions
        $notRestrictedActions = array('get-settings');
        if (!in_array($this->getParam("action"), $notRestrictedActions)) {
            $this->checkPermission("coreshop_permission_settings");
        }
    }

    public function getSettingsAction()
    {
        $values = Config::getConfig();

        $valueArray = $values->toArray();
        $pluginConfig = \Pimcore\ExtensionManager::getPluginConfig("CoreShop");

        $this->_helper->json(array("coreshop" => $valueArray, "plugin" => $pluginConfig['plugin']));
    }

    public function getAction()
    {
        $values = Config::getConfig();

        $valueArray = $values->toArray();

        $response = array(
            "values" => $valueArray,
        );

        $this->_helper->json($response);
        $this->_helper->json(false);
    }

    public function setAction()
    {
        $values = \Zend_Json::decode($this->getParam("data"));

        // convert all special characters to their entities so the xml writer can put it into the file
        $values = array_htmlspecialchars($values);

        // email settings
        $oldConfig = Config::getConfig();
        $settings = $oldConfig->toArray();

        $settings['base']['base-currency'] = $values["base.base-currency"];
        $settings['product']['default-image'] = $values["product.default-image"];
        $settings['product']['days-as-new'] = $values["product.days-as-new"];
        $settings['category']['default-image'] = $values["category.default-image"];
        $settings['template']['name'] = $values['template.name'];
        $settings['invoice']['create'] = $values['invoice.create'];
        $settings['invoice']['prefix'] = $values['invoice.prefix'];
        $settings['invoice']['suffix'] = $values['invoice.suffix'];

        $config = new \Zend_Config($settings, true);
        $writer = new \Zend_Config_Writer_Xml(array(
            "config" => $config,
            "filename" => CORESHOP_CONFIGURATION
        ));
        $writer->write();

        $this->_helper->json(array("success" => true));
    }

    public function createcountriesAction()
    {
        $language = $this->_getParam("language");

        $locale = new Zend_Locale($language);
        $regions = Zend_Locale::getTranslationList('RegionToTerritory');

        $countryGroup = array();

        foreach ($regions as $region => $countriesString) {
            $countries = explode(' ', $countriesString);

            foreach($countries as $country) {
                $countryGroup[$country] = $locale->getTranslation($region, 'territory', $locale);
            }

        }

        $countries = Country::getData();

        foreach($countries as $iso=>$name)
        {
            $currencyCode = Country::getCurrencyCodeForCountry($iso);
            $currencyDetail = Country::getCurrencyDetail($currencyCode);

            if(!$currencyCode || !$currencyDetail) {

                continue;
            }

            $currencyName = $currencyDetail['name'];
            $currencySymbol = $currencyDetail['symbol'];
            $currencyIsoNumber = $currencyDetail['isocode'];

            //Check if currency Object already exists
            $currencyObject = Model\Currency::getByName($currencyName);

            if(!$currencyObject instanceof Model\Currency)
            {
                $currencyObject = new Model\Currency();
                $currencyObject->setSymbol($currencySymbol);
                $currencyObject->setNumericIsoCode($currencyIsoNumber);
                $currencyObject->setIsoCode($currencyCode);
                $currencyObject->setExchangeRate(1);

            }

            $currencyObject->setName($currencyName);
            $currencyObject->save();

            //Check if country Object already exists
            $countryObject = Model\Country::getByIsoCode($iso);

            if(!$countryObject instanceof Model\Country)
            {
                $countryObject = new Model\Country();
            }

            $countryObject->setName($name);
            $countryObject->setIsoCode($iso);
            $countryObject->setActive(false);
            $countryObject->setCurrency($currencyObject);
            $countryObject->save();
        }

        $this->_helper->json(array("success" => true));
    }
}
