<?php
/**
 * Element filters plugin for Craft CMS 3.x
 *
 * Element filters
 *
 * @link      http://craftsnippets.com/
 * @copyright Copyright (c) 2021 Piotr Pogorzelski
 */

namespace craftsnippets\elementfilters;


use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;

use yii\base\Event;

use craft\web\UrlManager;
use craft\events\RegisterUrlRulesEvent;

/**
 * Class ElementFilters
 *
 * @author    Piotr Pogorzelski
 * @package   ElementFilters
 * @since     1.0.0
 *
 */
class ElementFilters extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var ElementFilters
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool
     */
    public bool $hasCpSettings = false;

    /**
     * @var bool
     */

    // needed for permission to appear on list
    public bool $hasCpSection = true;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // components
        $this->setComponents([
            'filters' => \craftsnippets\elementfilters\services\ElementFiltersService::class,
        ]);

        $this->filters->removeFiltersOnEvents();

        Craft::$app->onInit(function() {
            $this->filters->injectFilterHtml();
            // need to inject js everywhere instead of only when element imdex template is rendered - in that case it would return js with ajax request and it would not be able to grab Craft.elementIndex properly, when opening elemnt index with field modal (like entry field)
            $this->filters->injectAssets();
        });

        // routes
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules = array_merge($event->rules, [
                'quick-filters/<elementType>/<sourceKey>' => 'quick-filters/element-filters/filter-list',
                'quick-filters/<elementType>/<sourceKey>/new' => 'quick-filters/element-filters/filter-edit',
                'quick-filters/<elementType>/<sourceKey>/<filterId:\d+>' => 'quick-filters/element-filters/filter-edit',
            ]);
        });

    }

    public function getCpNavItem(): ?array
    {
        return null;
    }


}
