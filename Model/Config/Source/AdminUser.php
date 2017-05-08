<?php
/**
 * Copyright © 2009-2017 Vaimo Group. All rights reserved.
 * See LICENSE for license details.
 */

namespace Vaimo\AdminAutoLogin\Model\Config\Source;

class AdminUser implements \Magento\Framework\Option\ArrayInterface
{

    /**
     * @var \Magento\User\Model\ResourceModel\User\Collection
     */
    private $userCollection;

    /**
     * @var array
     */
    private $users;

    public function __construct(\Magento\User\Model\ResourceModel\User\Collection $userCollection)
    {
        $this->userCollection = $userCollection;
    }

    /**
     * Get options in "key-value" format
     *
     * @param bool $includeEmptyChoice
     * @return array
     */
    public function toArray($includeEmptyChoice = true)
    {
        if (is_null($this->users)) {
            $this->users = [];

            if ($includeEmptyChoice) {
                $this->users[''] = __('-- Default behavior --');
            }

            $this->userCollection->addFieldToFilter('is_active', true);
            $this->userCollection->addOrder('username', \Magento\Framework\Data\Collection::SORT_ORDER_ASC);

            foreach ($this->userCollection as $user) {
                $this->users[$user->getUsername()] = $user->getName() . ' (' . $user->getUsername() . ')';
            }
        }

        return $this->users;
    }

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $users = [];

        foreach ($this->toArray() as $value => $label) {
            $users[] = ['value' => $value, 'label' => $label];
        }

        return $users;
    }

}
