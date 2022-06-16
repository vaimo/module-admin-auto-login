<?php
/**
 * Copyright © Vaimo Group. All rights reserved.
 * See LICENSE for license details.
 */
namespace Vaimo\AdminAutoLogin\App\Action\Plugin;

use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\State\UserLockedException;
use Magento\Security\Model\AdminSessionsManager;
use function in_array;
use function sprintf;
use function array_keys;
use function reset;

class Authentication
{
    /**
     * Default usernames to attempt login if there is no configuration
     */
    private const DEFAULT_USERNAMES = [
        'admin',
    ];

    /**
     * Controller actions that must be reachable without authentication
     */
    private const CONTROLLER_ACTIONS_OPEN = [
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
     * @var \Magento\Framework\Message\ManagerInterface
     */
    private $messageManager;

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

    /**
     * @var AdminSessionsManager
     */
    private $adminSessionsManager;

    /**
     * @param \Magento\Backend\Model\Auth $auth
     * @param \Magento\Backend\Model\UrlInterface $backendUrl
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Framework\Data\Collection\ModelFactory $modelFactory
     * @param \Magento\Framework\Controller\Result\RedirectFactory $resultRedirectFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Vaimo\AdminAutoLogin\Model\Config\Source\AdminUser $adminUserSource
     * @param AdminSessionsManager $adminSessionsManager
     */
    public function __construct(
        \Magento\Backend\Model\Auth $auth,
        \Magento\Backend\Model\UrlInterface $backendUrl,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\Data\Collection\ModelFactory $modelFactory,
        \Magento\Framework\Controller\Result\RedirectFactory $resultRedirectFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Vaimo\AdminAutoLogin\Model\Config\Source\AdminUser $adminUserSource,
        \Magento\Security\Model\AdminSessionsManager $adminSessionsManager
    ) {
        $this->auth = $auth;
        $this->backendUrl = $backendUrl;
        $this->eventManager = $eventManager;
        $this->messageManager = $messageManager;
        $this->modelFactory = $modelFactory;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->config = $scopeConfig;
        $this->adminUserSource = $adminUserSource;
        $this->adminSessionsManager = $adminSessionsManager;
    }

    /**
     * @param \Magento\Framework\App\ActionInterface $subject
     * @param \Closure $proceed
     * @param \Magento\Framework\App\RequestInterface $request
     *
     * @return \Magento\Framework\Controller\Result\Redirect|mixed
     * @throws AuthenticationException
     * @throws UserLockedException
     * @throws \Exception
     */
    public function aroundDispatch(
        \Magento\Framework\App\ActionInterface $subject,
        \Closure $proceed,
        \Magento\Framework\App\RequestInterface $request
    ) {
        if (!$this->config->isSetFlag('admin/admin_auto_login/active')) {
            return $proceed($request);
        }

        $requestedActionName = $request->getActionName();

        if (in_array($requestedActionName, self::CONTROLLER_ACTIONS_OPEN, true)) {
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

        $loginUserName = $this->getLoginUsername();

        if (empty($loginUserName)) {
            $this->messageManager->addErrorMessage("Create an admin user for Vaimo_AdminAutoLogin to work!");
            return $proceed($request);
        }

        $this->autoLogin($request, $loginUserName);

        if ($request instanceof \Magento\Framework\App\Request\Http) {
            $routePath = sprintf(
                '%s/%s/%s',
                $request->getRouteName(),
                $request->getControllerName(),
                $request->getActionName()
            );
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
            if (in_array($username, $usernameList, true)) {
                return $username;
            }
        }

        $username = reset($usernameList);
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
        $user = $this->modelFactory->create(\Magento\Backend\Model\Auth\Credential\StorageInterface::class);

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
            throw new AuthenticationException(
                __('You did not sign in correctly or your account is temporarily disabled.')
            );
        }

        // check whether user is locked
        $lockExpires = $user->getLockExpires();

        if ($lockExpires) {
            $lockExpires = new \DateTime($lockExpires);
            if ($lockExpires > new \DateTime()) {
                throw new UserLockedException(
                    __('You did not sign in correctly or your account is temporarily disabled.')
                );
            }
        }

        $this->eventManager->dispatch('admin_user_authenticate_after', [
            'username' => $username,
            'password' => null,
            'user' => $user,
            'result' => true,
        ]);

        $this->handleLogin($user);
    }

    private function handleLogin($user)
    {
        $authStorage = $this->auth->getAuthStorage();
        $user->getResource()->recordLogin($user);
        $authStorage->setUser($user);
        $authStorage->processLogin();
        $this->eventManager->dispatch('backend_auth_user_login_success', ['user' => $user]);
        $this->populateAdminUserSessionTable();
        $authStorage->refreshAcl();
    }

    private function populateAdminUserSessionTable()
    {
        $this->adminSessionsManager->processLogin();
        if ($this->adminSessionsManager->getCurrentSession()->isOtherSessionsTerminated()) {
            $this->messageManager->addWarningMessage(__('All other open sessions for this account were terminated.'));
        }
    }

    private function prolongSession()
    {
        $this->adminSessionsManager->processLogin();
    }
}
