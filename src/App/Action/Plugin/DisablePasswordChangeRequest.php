<?php
/**
 * Copyright © Vaimo Group. All rights reserved.
 * See LICENSE for license details.
 */
namespace Vaimo\AdminAutoLogin\App\Action\Plugin;

/**
 * @plugin \Magento\User\Model\Backend\Config\ObserverConfig
 */
class DisablePasswordChangeRequest
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $config;

    /**
     * DisablePasswordChangeRequest constructor.
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->config = $scopeConfig;
    }

    /**
     * @param \Magento\User\Model\Backend\Config\ObserverConfig $subject
     * @param \Closure $proceed
     * @return int
     */
    public function aroundGetAdminPasswordLifetime(\Magento\User\Model\Backend\Config\ObserverConfig $subject, $proceed)
    {
        // If admin_auto_login_module is inactive, get password lifetime normally.
        if (!$this->config->isSetFlag('admin/admin_auto_login/active')) {
            return $proceed();
        }

        // Otherwise password lifetime is infinitive.
        // Could return INF, but to avoid unexpected side effects, let's return 50 years.
        return 86400 * 365 * 50;
    }

    /**
     * @param \Magento\User\Model\Backend\Config\ObserverConfig $subject
     * @param \Closure $proceed
     * @return bool
     */
    public function aroundIsPasswordChangeForced(\Magento\User\Model\Backend\Config\ObserverConfig $subject, $proceed)
    {
        if (!$this->config->isSetFlag('admin/admin_auto_login/active')) {
            return $proceed();
        }

        return false;
    }
}
