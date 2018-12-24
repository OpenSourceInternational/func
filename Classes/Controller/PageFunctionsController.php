<?php
namespace TYPO3\CMS\Func\Controller;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Fluid\ViewHelpers\Be\InfoboxViewHelper;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Backend\Template\DocumentTemplate;
use TYPO3\CMS\Func\Service\PageFunctionsService;

/**
 * Script Class for the Web > Functions module
 * This class creates the framework to which other extensions can connect their sub-modules
 */
class PageFunctionsController
{
    /**
     * @var array
     * @internal
     */
    public $pageInfo;

    /**
     * ModuleTemplate Container
     *
     * @var ModuleTemplate
     */
    protected $moduleTemplate;

    /**
     * Document Template Object
     *
     * @var \TYPO3\CMS\Backend\Template\DocumentTemplate
     */
    public $documentTemplate;

    /**
     * The name of the module
     *
     * @var string
     */
    protected $moduleName = 'web_func';

    /**
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * @var StandaloneView
     */
    protected $standaloneView;

    /**
     * Generally used for accumulating the output content of backend modules
     *
     * @var string
     */
    public $outputContent = '';

    /**
     * The integer value of the GET/POST var, 'id'. Used for submodules to the 'Web' module (page id)
     *
     * @see init()
     * @var int
     */
    public $id;

    /**
     * A WHERE clause for selection records from the pages table based on read-permissions of the current backend user.
     *
     * @see init()
     * @var string
     */
    public $permissionsClause;

    /**
     * The module menu items array. Each key represents a key for which values can range between the items in the array of that key.
     *
     * @see init()
     * @var array
     */
    public $moduleMenu = [
        'function' => []
    ];

    /**
     * Current settings for the keys of the moduleMenu array
     *
     * @see $moduleMenu
     * @var array
     */
    public $moduleSettings = [];


    /**
     * Module TSconfig based on PAGE TSconfig / USER TSconfig
     *
     * @see menuConfig()
     * @var array
     */
    public $moduleTSConfig;

    /**
     * If type is 'ses' then the data is stored as session-lasting data. This means that it'll be wiped out the next time the user logs in.
     * Can be set from extension classes of this class before the init() function is called.
     *
     * @see menuConfig(), \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleData()
     * @var string
     */
    public $moduleMenuType = '';

    /**
     * dontValidateList can be used to list variables that should not be checked if their value is found in the moduleMenu array. Used for dynamically generated menus.
     * Can be set from extension classes of this class before the init() function is called.
     *
     * @see menuConfig(), \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleData()
     * @var string
     */
    public $moduleMenuDontValidateList = '';

    /**
     * List of default values from $moduleMenu to set in the output array (only if the values from moduleMenu are not arrays)
     * Can be set from extension classes of this class before the init() function is called.
     *
     * @see menuConfig(), \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleData()
     * @var string
     */
    public $moduleMenuSetDefaultList = '';

    /**
     * Contains module configuration parts from TBE_MODULES_EXT if found
     *
     * @see handleExternalFunctionValue()
     * @var array
     */
    public $extensionClassConfiguration;

    /**
     * May contain an instance of a 'Function menu module' which connects to this backend module.
     *
     * @see checkExtObj()
     * @var PageFunctionsController
     */
    public $extensionObject;

    /**
     * Module Config
     *
     * @see init()
     * @var array
     */
    protected $moduleConfig = [];

    /**
     * @var string
     */
    public $command = '';

    /**
     * @var \TYPO3\CMS\Func\Service\PageFunctionsService
     */
    protected $pageFunctionsService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $this->moduleTemplate = GeneralUtility::makeInstance(ModuleTemplate::class);
        $this->pageFunctionsService = GeneralUtility::makeInstance(PageFunctionsService::class);
        $this->pageFunctionsService->getLanguageService()->includeLLFile('EXT:func/Resources/Private/Language/locallang_mod_web_func.xlf');
        $this->moduleConfig = [
            'name' => $this->moduleName,
        ];
    }

    /**
     * Initializes the backend module by setting internal variables, initializing the menu.
     *
     * @see menuConfig()
     */
    public function init()
    {
        // Name might be set from outside
        if (!$this->moduleConfig['name']) {
            $this->moduleConfig = $GLOBALS['MCONF'];
        }
        $this->id = (int)GeneralUtility::_GP('id');
        $this->command = GeneralUtility::_GP('CMD');
        $this->permissionsClause = $this->pageFunctionsService->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
        $this->menuConfig();
        $this->handleExternalFunctionValue();
    }

    /**
     * Injects the request object for the current request or subrequest
     * Then checks for module functions that have hooked in, and renders menu etc.
     *
     * @param ServerRequestInterface $request the current request
     * @param ResponseInterface $response
     * @return ResponseInterface the response with the content
     */
    public function mainAction(ServerRequestInterface $request, ResponseInterface $response)
    {
        $GLOBALS['SOBE'] = $this;
        $this->init();

        // Checking for first level external objects
        $this->checkExtObj();

        // Checking second level external objects
        $this->checkSubExtObj();
        $this->main();

        $this->moduleTemplate->setContent($this->outputContent);

        $response->getBody()->write($this->moduleTemplate->renderContent());
        return $response;
    }

    /**
     * Initialize module header etc and call extObjContent function
     */
    public function main()
    {
        // Access check...
        // The page will show only if there is a valid page and if this page may be viewed by the user
        $this->pageInfo = BackendUtility::readPageAccess($this->id, $this->permissionsClause);
        if ($this->pageInfo) {
            $this->moduleTemplate->getDocHeaderComponent()->setMetaInformation($this->pageInfo);
        }
        $access = is_array($this->pageInfo);
        // We keep this here, in case somebody relies on the old doc being here
        $this->documentTemplate = GeneralUtility::makeInstance(DocumentTemplate::class);
        // Main
        if ($this->id && $access) {
            // JavaScript
            $this->moduleTemplate->addJavaScriptCode(
                'WebFuncInLineJS',
                'if (top.fsMod) top.fsMod.recentIds["web"] = ' . (int)$this->id . ';');
            // Setting up the context sensitive menu:
            $this->moduleTemplate->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/Backend/ContextMenu');

            $this->standaloneView = $this->pageFunctionsService->getFluidTemplateObject('func', 'func');
            $this->standaloneView->assign('moduleName', $this->pageFunctionsService->getModuleUrl('web_func'));
            $this->standaloneView->assign('id', $this->id);
            $this->standaloneView->assign('functionMenuModuleContent', $this->getExtObjContent());
            // Setting up the buttons and markers for docheader
            $this->getButtons();
            $this->generateMenu();
            $this->outputContent .= $this->standaloneView->render();
        } else {
            // If no access or if ID == zero
            $title = $this->pageFunctionsService->getLanguageService()->getLL('title');
            $message = $this->pageFunctionsService->getLanguageService()->getLL('clickAPage_content');
            $this->standaloneView = $this->pageFunctionsService->getFluidTemplateObject('func', 'func', 'InfoBox');
            $this->standaloneView->assignMultiple([
                'title' => $title,
                'message' => $message,
                'state' => InfoboxViewHelper::STATE_INFO
            ]);
            $this->outputContent = $this->standaloneView->render();
            // Setting up the buttons and markers for docheader
            $this->getButtons();
        }
    }

    /**
     * Generates the menu based on $this->moduleMenu
     *
     * @throws \InvalidArgumentException
     */
    protected function generateMenu()
    {
        $menu = $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu->setIdentifier('WebFuncJumpMenu');
        foreach ($this->moduleMenu['function'] as $controller => $title) {
            $item = $menu
                ->makeMenuItem()
                ->setHref(
                    $this->pageFunctionsService->getModuleUrl(
                        $this->moduleName,
                        [
                            'id' => $this->id,
                            'SET' => [
                                'function' => $controller
                            ]
                        ]
                    )
                )
                ->setTitle($title);
            if ($controller === $this->moduleSettings['function']) {
                $item->setActive(true);
            }
            $menu->addMenuItem($item);
        }
        $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
    }

    /**
     * Create the panel of buttons for submitting the form or otherwise perform operations.
     */
    protected function getButtons()
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        // CSH
        $cshButton = $buttonBar->makeHelpButton()
            ->setModuleName('_MOD_web_func')
            ->setFieldName('');
        $buttonBar->addButton($cshButton);
        if ($this->id && is_array($this->pageInfo)) {
            // View page
            $viewButton = $buttonBar->makeLinkButton()
                ->setOnClick(BackendUtility::viewOnClick($this->pageInfo['uid'], '', BackendUtility::BEgetRootLine($this->pageInfo['uid'])))
                ->setTitle($this->pageFunctionsService->getLanguageService()->sL('LLL:EXT:lang/Resources/Private/Language/locallang_core.xlf:labels.showPage'))
                ->setIcon($this->iconFactory->getIcon('actions-view-page', Icon::SIZE_SMALL))
                ->setHref('#');
            $buttonBar->addButton($viewButton);
            // Shortcut
            $shortcutButton = $buttonBar->makeShortcutButton()
                ->setModuleName($this->moduleName)
                ->setGetVariables(['id', 'edit_record', 'pointer', 'new_unique_uid', 'search_field', 'search_levels', 'showLimit'])
                ->setSetVariables(array_keys($this->moduleMenu));
            $buttonBar->addButton($shortcutButton);
        }
    }

    /**
     * Initializes the internal moduleMenu array setting and unsetting items based on various conditions. It also merges in external menu items from the global array TBE_MODULES_EXT (see mergeExternalItems())
     * Then moduleSettings array is cleaned up (see \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleData()) so it contains only valid values. It's also updated with any SET[] values submitted.
     * Also loads the moduleTSConfig internal variable.
     *
     * @see init(), $moduleMenu, $moduleSettings, \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleData(), mergeExternalItems()
     */
    private function menuConfig()
    {
        // Page / user TSconfig settings and blinding of menu-items
        $this->moduleTSConfig['properties'] = BackendUtility::getPagesTSconfig($this->id)['mod.'][$this->moduleConfig['name'] . '.'] ?? [];
        $this->moduleMenu['function'] = $this->pageFunctionsService->mergeExternalItems($this->moduleConfig['name'], 'function', $this->moduleMenu['function']);
        $blindActions = $this->moduleTSConfig['properties']['menu.']['function.'] ?? [];
        foreach ($blindActions as $key => $value) {
            if (!$value && array_key_exists($key, $this->moduleMenu['function'])) {
                unset($this->moduleMenu['function'][$key]);
            }
        }
        $this->moduleSettings = BackendUtility::getModuleData(
            $this->moduleMenu,
            GeneralUtility::_GP('SET'),
            $this->moduleConfig['name'],
            $this->moduleMenuType,
            $this->moduleMenuDontValidateList,
            $this->moduleMenuSetDefaultList
        );
    }

    /**
     * Loads $this->extensionClassConfiguration with the configuration for the CURRENT function of the menu.
     *
     * @param string $MM_key The key to moduleMenu for which to fetch configuration. 'function' is default since it is first and foremost used to get information per "extension object" (I think that is what its called)
     * @param string $MS_value The value-key to fetch from the config array. If NULL (default) moduleSettings[$MM_key] will be used. This is useful if you want to force another function than the one defined in moduleSettings[function]. Call this in init() function of your Script Class: handleExternalFunctionValue('function', $forcedSubModKey)
     * @see getExternalItemConfig(), init()
     */
    private function handleExternalFunctionValue($MM_key = 'function', $MS_value = null)
    {
        if ($MS_value === null) {
            $MS_value = $this->moduleSettings[$MM_key];
        }
        $this->extensionClassConfiguration = $this->pageFunctionsService->getExternalItemConfig($this->moduleConfig['name'], $MM_key, $MS_value);
    }

    /**
     * Creates an instance of the class found in $this->extensionClassConfiguration['name'] in $this->extensionObject if any (this should hold three keys, "name", "path" and "title" if a "Function menu module" tries to connect...)
     * This value in extensionClassConfiguration might be set by an extension (in an ext_tables/ext_localconf file) which thus "connects" to a module.
     * The array $this->extensionClassConfiguration is set in handleExternalFunctionValue() based on the value of moduleSettings[function]
     * If an instance is created it is initiated with $this passed as value and $this->extensionClassConfiguration as second argument. Further the $this->MOD_SETTING is cleaned up again after calling the init function.
     *
     * @see handleExternalFunctionValue(), \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(), $extObj
     */
    private function checkExtObj()
    {
        if (is_array($this->extensionClassConfiguration) && $this->extensionClassConfiguration['name']) {
            $this->extensionObject = GeneralUtility::makeInstance($this->extensionClassConfiguration['name']);
            $this->extensionObject->init($this, $this->extensionClassConfiguration);
            // Re-write:
            $this->moduleSettings = BackendUtility::getModuleData(
                $this->moduleMenu,
                GeneralUtility::_GP('SET'),
                $this->moduleConfig['name'],
                $this->moduleMenuType,
                $this->moduleMenuDontValidateList,
                $this->moduleMenuSetDefaultList
            );
        }
    }

    /**
     * Calls the checkExtObj function in sub module if present.
     */
    private function checkSubExtObj()
    {
        if (is_object($this->extensionObject)) {
            $this->extensionObject->checkExtObj();
        }
    }

    /**
     * Return the content of the 'main' function inside the "Function menu module" if present
     *
     * @return string
     * @throws \TYPO3\CMS\Core\Exception
     */
    private function getExtObjContent()
    {
        $savedContent = $this->outputContent;
        $this->outputContent = '';
        $this->extObjContent();
        $newContent = $this->outputContent;
        $this->outputContent = $savedContent;
        return $newContent;
    }

    /**
     * Calls the 'main' function inside the "Function menu module" if present
     *
     * @throws \TYPO3\CMS\Core\Exception
     */
    private function extObjContent()
    {
        if ($this->extensionObject === null) {
            $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                $this->pageFunctionsService->getLanguageService()->sL('LLL:EXT:backend/Resources/Private/Language/locallang.xlf:no_modules_registered'),
                $this->pageFunctionsService->getLanguageService()->getLL('title'),
                FlashMessage::ERROR
            );
            /** @var \TYPO3\CMS\Core\Messaging\FlashMessageService $flashMessageService */
            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            /** @var \TYPO3\CMS\Core\Messaging\FlashMessageQueue $defaultFlashMessageQueue */
            $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $defaultFlashMessageQueue->enqueue($flashMessage);
        } else {
            $this->extensionObject->pObj = $this;
            if (is_callable([$this->extensionObject, 'main'])) {
                $this->outputContent .= $this->extensionObject->main();
            }
        }
    }
}
