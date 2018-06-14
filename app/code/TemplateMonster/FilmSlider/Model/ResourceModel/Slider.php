<?php

/**
 *
 * Copyright © 2015 TemplateMonster. All rights reserved.
 * See COPYING.txt for license details.
 *
 */
namespace TemplateMonster\FilmSlider\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use TemplateMonster\FilmSlider\Api\Data\SliderInterface;

class Slider extends AbstractDb
{
    /**
     * Store model
     *
     * @var null|\Magento\Store\Model\Store
     */
    protected $_store = null;

    /**
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * Construct
     *
     * @param \Magento\Framework\Model\ResourceModel\Db\Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Stdlib\DateTime $dateTime
     * @param string $connectionName
     */
    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
        $this->_storeManager = $storeManager;
    }

    protected function _construct()
    {
        $this->_init('film_slider', 'slider_id');
    }

    protected function _beforeDelete(AbstractModel $object)
    {
        $condition = ['slider_id = ?' => (int)$object->getId()];
        $this->getConnection()->delete($this->getTable('film_slider_store'), $condition);
    }

    protected function _beforeSave(\Magento\Framework\Model\AbstractModel $object)
    {
        $this->_encodeValueToSave($object);
        return parent::_beforeSave($object); // TODO: Change the autogenerated stub
    }

    protected function _afterSave(AbstractModel $object)
    {
        $oldStores = $this->getStoreIds($object->getId());
        $newStores = (array)$object->getStores();
        if (empty($newStores)) {
            $newStores = (array)$object->getStoreId();
        }
        $table = $this->getTable('film_slider_store');
        $insert = array_diff($newStores, $oldStores);
        $delete = array_diff($oldStores, $newStores);

        if ($delete) {
            $where = ['slider_id = ?' => (int)$object->getId(), 'store_id IN (?)' => $delete];

            $this->getConnection()->delete($table, $where);
        }

        if ($insert) {
            $data = [];

            foreach ($insert as $storeId) {
                $data[] = ['slider_id' => (int)$object->getId(), 'store_id' => (int)$storeId];
            }

            $this->getConnection()->insertMultiple($table, $data);
        }

        return parent::_afterSave($object);
    }

    /**
     * Perform operations after object load
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return $this
     */
    protected function _afterLoad(\Magento\Framework\Model\AbstractModel $object)
    {
        if ($object->getId()) {
            $stores = $this->getStoreIds($object->getId());

            $object->setData('store_id', $stores);
        }

        $this->_retriveSliderParams($object);
        $this->_insertValueToObject($object);

        return parent::_afterLoad($object);
    }


    protected function _getLoadSelect($field, $value, $object)
    {
        $select = parent::_getLoadSelect($field, $value, $object);

        if ($object->getStoreId()) {
            $storeIds = [\Magento\Store\Model\Store::DEFAULT_STORE_ID, (int)$object->getStoreId()];
            $select->join(
                ['film_slider_store' => $this->getTable('film_slider_store')],
                $this->getMainTable() . '.slider_id = film_slider_store.slider_id',
                []
            )->where(
                'is_active = ?',
                1
            )->where(
                'film_slider_store.store_id IN (?)',
                $storeIds
            )->order(
                'film_slider_store.store_id DESC'
            )->limit(
                1
            );
        }

        return $select;
    }

    /**
     * Get store ids to which specified item is assigned
     *
     * @param int $sliderId
     * @return array
     */
    public function getStoreIds($sliderId)
    {
        $connection = $this->getConnection();

        $select = $connection->select()->from(
            $this->getTable('film_slider_store'),
            'store_id'
        )->where(
            'slider_id = ?',
            (int)$sliderId
        );

        return $connection->fetchCol($select);
    }

    public function getActiveSlidesCount($slider)
    {
        $connection = $this->getConnection();

        $select = $connection->select()->from(
            $this->getTable('film_slider_item'),
            array('slider_items'=>('COUNT(*)'))
        )->where(
            'parent_id = ?',
            (int)$slider->getId()
        )->where(
            'status = ?',
            1
        );

        return $connection->fetchRow($select);
    }


    /**
     * Set store model
     *
     * @param \Magento\Store\Model\Store $store
     * @return $this
     */
    public function setStore($store)
    {
        $this->_store = $store;
        return $this;
    }

    /**
     * Retrieve store model
     *
     * @return \Magento\Store\Model\Store
     */
    public function getStore()
    {
        return $this->_storeManager->getStore($this->_store);
    }

    public function _retriveSliderParams(\Magento\Framework\Model\AbstractModel $object)
    {
        if ($object->getId() && $object->getParams()) {
            $object->setData(SliderInterface::PARAMS_ARRAY,
                \Zend_Json::decode($object->getParams()));
        }
    }

    public function _insertValueToObject(\Magento\Framework\Model\AbstractModel $object)
    {
        if ($object->getParamsArray() && is_array($object->getParamsArray())) {
            $params = $object->getParamsArray();
            $object->addData($params);
        }
    }

    public function _encodeValueToSave(\Magento\Framework\Model\AbstractModel $object)
    {
        if ($object->getParams() && is_array($object->getParams())) {
            $result = json_encode($object->getParams());
            $object->setParams($result);
        }
    }
}
