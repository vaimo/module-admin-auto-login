<?php
/**
 * Copyright © Vaimo Group. All rights reserved.
 * See LICENSE for license details.
 */
namespace Vaimo\AdminAutoLogin\Model\Config\Source;

class AdminUsernameList
{
    /**
     * @var \Magento\User\Model\ResourceModel\User\Collection
     */
    private $userCollection;

    /**
     * @var array
     */
    private $users;

    /**
     * AdminUsernameList constructor.
     *
     * @param \Magento\User\Model\ResourceModel\User\Collection $userCollection
     */
    public function __construct(
        \Magento\User\Model\ResourceModel\User\Collection $userCollection
    ) {
        $this->userCollection = $userCollection;
    }

    /**
     * Get options in "key-value" format
     *
     * @param bool $includeEmptyChoice
     * @return array
     */
    public function get($includeEmptyChoice = true)
    {
        if ($this->users === null) {
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

}
