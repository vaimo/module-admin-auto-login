<?php
/**
 * Copyright © 2009-2017 Vaimo Group. All rights reserved.
 * See LICENSE for license details.
 */

namespace Vaimo\AdminAutoLogin\App\Action\Plugin;

use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\State\UserLockedException;

class Authentication
{

    /**
     * Default usernames to attempt login if there is no configuration
     */
    const DEFAULT_USERNAMES = [
        'jambi',
        'admin',
    ];

    /**
     * Controller actions that must be reachable without authentication
     */
    const CONTROLLER_ACTIONS_OPEN = [
        'forgotpassword',
        'resetpassword',
        'resetpasswordpost',
        'logout',
        'refresh', // captcha refresh
    ];

    /**
     * @var \Magento\Backend\Model\Auth
     */
    private $auth;

    /**
     * @var \Magento\Backend\Model\UrlInterface
     */
    private $backendUrl;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    private $eventManager;

    /**
     * @var \Magento\Framework\Data\Collection\ModelFactory
     */
    private $modelFactory;

    /**
     * @var \Magento\Framework\Controller\Result\RedirectFactory
     */
    private $resultRedirectFactory;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $config;

    /**
     * @var \Vaimo\AdminAutoLogin\Model\Config\Source\AdminUser
     */
    private $adminUserSource;

    public function __construct(
        \Magento\Backend\Model\Auth $auth,
        \Magento\Backend\Model\UrlInterface $backendUrl,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Framework\Data\Collection\ModelFactory $modelFactory,
        \Magento\Framework\Controller\Result\RedirectFactory $resultRedirectFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Vaimo\AdminAutoLogin\Model\Config\Source\AdminUser $adminUserSource
    ) {
        $this->auth = $auth;
        $this->backendUrl = $backendUrl;
        $this->eventManager = $eventManager;
        $this->modelFactory = $modelFactory;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->config = $scopeConfig;
        $this->adminUserSource = $adminUserSource;
    }

    public function aroundDispatch(
        \Magento\Framework\App\ActionInterface $subject,
        \Closure $proceed,
        \Magento\Framework\App\RequestInterface $request
    ) {
        if (!$this->config->isSetFlag('admin/admin_auto_login/active')) {
            return $proceed($request);
        }

        $requestedActionName = $request->getActionName();

        if (in_array($requestedActionName, self::CONTROLLER_ACTIONS_OPEN)) {
            return $proceed($request);
        }

        if ($this->auth->getUser()) {
            $this->auth->getUser()->reload();
        }

        if ($this->auth->isLoggedIn()) {
            $this->auth->getAuthStorage()->refreshAcl();
            $this->prolongSession();
            return $proceed($request);
        }

        $this->autoLogin($request, $this->getLoginUsername());

        if ($request instanceof \Magento\Framework\App\Request\Http) {
            $routePath = sprintf('%s/%s/%s', $request->getRouteName(), $request->getControllerName(), $request->getActionName());
        } else {
            $routePath = 'adminhtml/dashboard';
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setUrl($this->backendUrl->getUrl($routePath, $request->getParams()));
    }

    /**
     * @return string
     * @throws \Exception
     */
    private function getLoginUsername()
    {
        $username = $this->config->getValue('admin/admin_auto_login/user');

        if (!empty($username)) {
            return $username;
        }

        $usernameList = array_keys($this->adminUserSource->toArray(false));

        foreach (self::DEFAULT_USERNAMES as $username) {
            if (array_search($username, $usernameList, true) !== false) {
                return $username;
            }
        }

        $username = reset($usernameList);

        if (empty($username)) {
            throw new \Exception('There are no admin users to attempt login to.');
        }

        return $username;
    }

    /**
     * @param \Magento\Framework\App\RequestInterface $request
     * @param string $username
     * @throws AuthenticationException
     * @throws UserLockedException
     * @throws \Exception
     */
    private function autoLogin(\Magento\Framework\App\RequestInterface $request, $username)
    {
        $authStorage = $this->auth->getAuthStorage();
        $user = $this->modelFactory->create('\Magento\Backend\Model\Auth\Credential\StorageInterface');

        $this->eventManager->dispatch('admin_user_authenticate_before', [
            'username' => $username,
            'user' => $user,
        ]);

        $user->loadByUsername($username);

        if (empty($user->getId())) {
            throw new \Exception('Invalid user');
        }

        // check whether user is disabled
        if (!$user->getIsActive()) {
            throw new AuthenticationException(__('You did not sign in correctly or your account is temporarily disabled.'));
        }

        // check whether user is locked
        $lockExpires = $user->getLockExpires();

        if ($lockExpires) {
            $lockExpires = new \DateTime($lockExpires);
            if ($lockExpires > new \DateTime()) {
                throw new UserLockedException(__('You did not sign in correctly or your account is temporarily disabled.'));
            }
        }

        $this->eventManager->dispatch('admin_user_authenticate_after', [
            'username' => $username,
            'password' => null,
            'user' => $user,
            'result' => true,
        ]);

        // Handle login
        $user->getResource()->recordLogin($user);
        $authStorage->setUser($user);
        $authStorage->processLogin();
        $this->eventManager->dispatch('backend_auth_user_login_success', ['user' => $user]);
        $this->populateAdminUserSessionTable($this->auth);
        $authStorage->refreshAcl();
    }

    /**
     * Populates admin_user_session table for M2.1+
     * Intentional usage of Object Manager, since the class is not available on M2.0 and will throw an exception.
     *
     * @param \Magento\Backend\Model\Auth $auth
     */
    private function populateAdminUserSessionTable(\Magento\Backend\Model\Auth $auth)
    {
        $plugin = false;

        try {
            /** @var \Magento\Security\Model\Plugin\Auth $plugin */
            $plugin = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\Security\Model\Plugin\Auth');
        } catch (\Exception $e) {
            //ignore exception
        }

        // This is intentionally outside of the above try-catch because we only want to catch the failure to instantiate the plugin
        if ($plugin) {
            $plugin->afterLogin($auth);
        }
    }

    /**
     * Prolong session for M2.1+
     * Intentional usage of Object Manager, since the class is not available on M2.0 and will throw an exception.
     */
    private function prolongSession()
    {
        $model = false;

        try {
            /** @var \Magento\Security\Model\AdminSessionsManager $model */
            $model = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\Security\Model\AdminSessionsManager');
        } catch (\Exception $e) {
            //ignore exception
        }

        // This is intentionally outside of the above try-catch because we only want to catch the failure to instantiate the plugin
        if ($model) {
            $model->processLogin();
        }
    }

}
