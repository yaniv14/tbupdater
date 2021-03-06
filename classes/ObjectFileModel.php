<?php
/**
 * 2017 thirty bees
 *
 * thirty bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.thirtybees.com for more information.
 *
 *  @author    thirty bees <contact@thirtybees.com>
 *  @copyright 2017 thirty bees
 *  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace TbUpdaterModule;

/**
 * This class is a shim between class ObjectModel and a class using ObjectModel.
 * It's purpose is to not access database storage, but a regular file instead.
 * Accordingly, all methods accessing the DB are overridden; everything else
 * is left for the parent.
 *
 * Storing data in a file is useful for e.g. configuration data (read often,
 * changed rarely). It also allows to change data by a shell script, which is
 * crucial for shop synchronisation.
 */

// This file might not exist. For example, at install time it doesn't.
// Accordingly, we also have to check for the existence of $shopUrlConfig
// on every read access.
@include_once(_PS_ROOT_DIR_.'/config/shop.inc.php');

/**
 * Class ObjectFileModelCore
 *
 * This class is a shim between ObjectModel and inherited classes like
 * ShopUrlCore, which redirects storage to a file instead of the database.
 *
 * @TODO: file content is in variable $shopUrlConfig, which is hardcoded
 *        here. This needs abstraction as soon as another usage of this class
 *        comes up.
 *
 * @since 1.1.0
 */
abstract class ObjectFileModel extends ObjectModel
{
    /**
     * Builds the object
     *
     * @param int|null $id     If specified, loads and existing object from DB (optional).
     * @param int|null $idLang Required if object is multilingual (optional).
     * @param int|null $idShop ID shop for objects with multishop tables.
     *
     * @throws \Exception
     * @throws \Exception
     *
     * @since   1.1.0
     * @version 1.1.0 Initial version
     */
    public function __construct($id = null, $idLang = null, $idShop = null)
    {
        global $shopUrlConfig;

        $className = get_class($this);
        if (!isset(static::$loaded_classes[$className])) {
            $this->def = static::getDefinition($className);
            static::$loaded_classes[$className] = get_object_vars($this);
        } else {
            foreach (static::$loaded_classes[$className] as $key => $value) {
                $this->{$key} = $value;
            }
        }

        if (!is_writable(_PS_ROOT_DIR_.$this->def['path']) &&
             !is_writable(dirname(_PS_ROOT_DIR_.$this->def['path']))) {
            throw new \Exception('Storage file '._PS_ROOT_DIR_.$this->def['path'].' for class '.get_class($this).' not writable.');
        }

        if ($idLang !== null) {
            $this->id_lang = (Language::getLanguage($idLang) !== false) ? $idLang : Configuration::get('PS_LANG_DEFAULT');
        }

        if ($idShop && $this->isMultishop()) {
            $this->id_shop = (int) $idShop;
            $this->get_shop_from_context = false;
        }

        if ($this->isMultishop() && !$this->id_shop) {
            $this->id_shop = Context::getContext()->shop->id;
        }

        if ($id) {
            if (is_array($shopUrlConfig)) {
                // This is what Adapter_EntityMapper does after database access.
                foreach ($shopUrlConfig[$id] as $key => $value) {
                    if (property_exists($this, $key)) {
                        $this->{$key} = $value;
                    } else {
                        unset($shopUrlConfig[$id][$key]);
                    }
                }
            }
            $this->id = $id;
        }
    }

    /**
     * Gets one or all record(s).
     *
     * @param bool $id Record to get. All records if NULL.
     *
     * @return array Array with record or array with all record arrays.
     *
     * @since   1.1.0
     * @version 1.1.0 Initial version
     */
    public static function get($id = null)
    {
        global $shopUrlConfig;

        if (!is_array($shopUrlConfig)) {
            return [];
        } elseif ($id) {
            return $shopUrlConfig[$id];
        } else {
            return $shopUrlConfig;
        }
    }

    /**
     * Writes the whole objects array as-is to it's file. Should be executed
     * after any permanent change to that array.
     *
     * @return bool Number of bytes written or false equivalent on failure.
     *
     * @since   1.1.0
     * @version 1.1.0 Initial version
     */
    public function write()
    {
        global $shopUrlConfig;

        $result = file_put_contents(_PS_ROOT_DIR_.$this->def['path'],
            "<?php\n\n".
            'global $shopUrlConfig;'."\n\n".
            '$shopUrlConfig = '.var_export($shopUrlConfig, true).';'."\n");

        // Clear most citizens in cache-mess-city. Else the include_once()
        // above may well read an old version on the next page load.
        Tools::clearSmartyCache();
        Tools::clearXMLCache();
        Cache::getInstance()->flush();
        PageCache::flush();
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        return $result;
    }

    /**
     * Adds current object to the file based storage.
     *
     * @param bool $autoDate
     * @param bool $nullValues Ignored, for compatibility with ObjectModel.
     *
     * @return bool Insertion result
     * @throws \Exception
     *
     * @since   1.1.0
     * @version 1.1.0 Initial version
     */
    public function add($autoDate = true, $nullValues = false)
    {
        global $shopUrlConfig;

        if (isset($this->id) && !$this->force_id) {
            unset($this->id);
        }

        // @hook actionObject*AddBefore
        Hook::exec('actionObjectAddBefore', ['object' => $this]);
        Hook::exec('actionObject'.get_class($this).'AddBefore', ['object' => $this]);

        // Automatically fill dates
        if ($autoDate && property_exists($this, 'date_add')) {
            $this->date_add = date('Y-m-d H:i:s');
        }
        if ($autoDate && property_exists($this, 'date_upd')) {
            $this->date_upd = date('Y-m-d H:i:s');
        }

        $idShopList = Shop::getContextListShopID();
        if (Shop::isTableAssociated($this->def['table'])) {
            if (count($this->id_shop_list) > 0) {
                $idShopList = $this->id_shop_list;
            }
        }

        if (Shop::checkIdShopDefault($this->def['table'])) {
            $this->id_shop_default = (in_array(Configuration::get('PS_SHOP_DEFAULT'), $idShopList) == true) ? Configuration::get('PS_SHOP_DEFAULT') : min($idShopList);
        }
        $fields = $this->getFields();

        // Find the smallest insertion point. count($array) is unreliable,
        // because there can be gaps after previous deletions.
        $newId = 1;
        while (is_array($shopUrlConfig) &&
               array_key_exists($newId, $shopUrlConfig)) {
            $newId++;
        }

        // Array insertion.
        $shopUrlConfig[$newId] = $fields;
        $result = $this->write();

        $this->id = $newId;

        /* Associations, multilingual fields not yet implemented. */

        // @hook actionObject*AddAfter
        Hook::exec('actionObjectAddAfter', ['object' => $this]);
        Hook::exec('actionObject'.get_class($this).'AddAfter', ['object' => $this]);

        return $result;
    }

     /**
      * Updates the current object in the file based storage.
      *
      * @param bool $nullValues Ignored, for compatibility with ObjectModel.
      *
      * @return bool
      * @throws \Exception
      */
    public function update($nullValues = false)
    {
        global $shopUrlConfig;

        $result = true;

        // @hook actionObject*UpdateBefore
        Hook::exec('actionObjectUpdateBefore', ['object' => $this]);
        Hook::exec('actionObject'.get_class($this).'UpdateBefore', ['object' => $this]);

        // Automatically fill dates
        if (property_exists($this, 'date_upd')) {
            $this->date_upd = date('Y-m-d H:i:s');
            if (isset($this->update_fields) && is_array($this->update_fields) && count($this->update_fields)) {
                $this->update_fields['date_upd'] = true;
            }
        }

        // Automatically fill dates
        if (property_exists($this, 'date_add') && $this->date_add == null) {
            $this->date_add = date('Y-m-d H:i:s');
            if (isset($this->update_fields) && is_array($this->update_fields) && count($this->update_fields)) {
                $this->update_fields['date_add'] = true;
            }
        }

        $idShopList = Shop::getContextListShopID();
        if (count($this->id_shop_list) > 0) {
            $idShopList = $this->id_shop_list;
        }

        if (Shop::checkIdShopDefault($this->def['table']) && !$this->id_shop_default) {
            $this->id_shop_default = (in_array(Configuration::get('PS_SHOP_DEFAULT'), $idShopList) == true) ? Configuration::get('PS_SHOP_DEFAULT') : min($idShopList);
        }

        // Array update.
        $fields = $this->getFields();
        unset($fields[$this->def['primary']]); // Set by getFields(), but not needed.
        $shopUrlConfig[$this->id] = $fields;
        $result = static::write();

        /* Associations, multilingual fields not yet implemented. */

        // @hook actionObject*UpdateAfter
        Hook::exec('actionObjectUpdateAfter', ['object' => $this]);
        Hook::exec('actionObject'.get_class($this).'UpdateAfter', ['object' => $this]);

        return $result;
    }

    /**
     * Deletes current object from the file based storage.
     *
     * @return bool True if delete was successful
     * @throws \Exception
     *
     * @since   1.1.0
     * @version 1.1.0 Initial version
     */
    public function delete()
    {
        global $shopUrlConfig;

        // @hook actionObject*DeleteBefore
        Hook::exec('actionObjectDeleteBefore', ['object' => $this]);
        Hook::exec('actionObject'.get_class($this).'DeleteBefore', ['object' => $this]);

        /* Associations, multilingual fields not yet implemented. */

        if (is_array($shopUrlConfig)) {
            unset($shopUrlConfig[$this->id]);
        }
        $result = $this->write();

        // @hook actionObject*DeleteAfter
        Hook::exec('actionObjectDeleteAfter', ['object' => $this]);
        Hook::exec('actionObject'.get_class($this).'DeleteAfter', ['object' => $this]);

        return $result;
    }
}
