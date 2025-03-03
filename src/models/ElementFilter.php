<?php

namespace craftsnippets\elementfilters\models;

use Craft;
use craft\base\FieldInterface;
use craft\base\Model;
use craft\helpers\UrlHelper;
use craft\helpers\Template;
use craft\helpers\ArrayHelper;

use craftsnippets\elementfilters\ElementFilters;

class ElementFilter extends Model
{
    const DROPDOWN_MODE_DEFAULT = 'default';
    const DROPDOWN_MODE_AJAX = 'ajax';

    public $id;
    public $uid;

    public $elementType;
    public $sourceKey;
    public $order;
    public $jsonSettings;

    // json properties
    public $fieldUidInLayout;
    public $fieldId;

    public $elementAttribute;
    public $filterType;
    public $orderOptionsBy;
    public $datePickerType;
    public $dropdownMode = self::DROPDOWN_MODE_DEFAULT;

    const JSON_PROPERTIES = [
        'fieldUidInLayout',
        'elementAttribute',
        'filterType',
        'orderOptionsBy',
        'datePickerType',
        'dropdownMode',
        'fieldId',
    ];

    const SORT_DEFAULT = 'default';
    const SORT_ALPHABETICALLY = 'alphabetically';
    const DATEPICKER_DAY = 'day';
    const DATEPICKER_RANGE = 'range';

    const FILTER_TYPE_FIELD = 'field';
    const FILTER_TYPE_ATTRIBUTE = 'attribute';

    const WIDGET_SELECT_TEMPLATE = 'quick-filters/_widget-select';
    const WIDGET_DATE_TEMPLATE = 'quick-filters/_widget-datepicker';
    const WIDGET_DATE_TEMPLATE_DAY = 'quick-filters/_widget-datepicker-day';
    const WIDGET_RANGE_TEMPLATE = 'quick-filters/_widget-range';
    const WIDGET_TEXT_TEMPLATE = 'quick-filters/_widget-text';

    const FIELDS_RELATIONS = [
            'craft\fields\Categories',
            'craft\fields\Entries',
            'craft\fields\Assets',
            'craft\fields\Users',
            'craft\fields\Tags',
            'craft\commerce\fields\Products',
            'craft\commerce\fields\Variants',
    ];
    const FIELDS_OPTIONS = [
            'craft\fields\Checkboxes',
            'craft\fields\Dropdown',
            'craft\fields\RadioButtons',
            'craft\fields\MultiSelect',
    ];
    const FIELDS_SWITCH = [
            'craft\fields\Lightswitch',
    ];
    const FIELDS_DATE = [
            'craft\fields\Date',
    ];
    const FILEDS_NUMBER = [
            'craft\fields\Number',
    ];
    const FIELDS_TEXT = [
        'craft\fields\PlainText',
        'craft\redactor\Field',
        'besteadfast\preparsefield\fields\PreparseFieldType',
    ];

    const FIELDS_COLOR = [
        'percipiolondon\colourswatches\fields\ColourSwatches',
    ];

    const ATTRIBUTE_TYPE_DATE = 'date';
    const ATTRIBUTE_TYPE_NUMBER = 'number';
    const ATTRIBUTE_TYPE_TEXT = 'text';
    const ATTRIBUTE_TYPE_SELECT = 'select';

    const SELECT_TYPE_RELATION = 'relation';
    const SELECT_TYPE_OPTIONS = 'options';
    const SELECT_TYPE_SWITCH = 'switch';
    const SELECT_TYPE_COLOR = 'color';

    public function init(): void
    {
        $this->populateJsonSettings();

        // for update form craft 4 to 5
        if(is_null($this->fieldUidInLayout) && !is_null($this->fieldId)){
            $newInLayout = $this->getFieldUidFromOldId();
            if(!is_null($newInLayout)){
                $this->fieldUidInLayout = $newInLayout->layoutElement->uid;
            }
        }
    }

    public function getFieldUidFromOldId()
    {
        $layoutFields = $this->getAllLayoutFields();
        $oldField = Craft::$app->fields->getFieldById($this->fieldId);
        if(is_null($oldField)){
            return null;
        }
        $layoutFields = $this->getAllLayoutFields();
        $matching = array_filter($layoutFields, function($single) use ($oldField){
            return $single->handle == $oldField->handle;
        });
        if(empty($matching)){
            return null;
        }
        return reset($matching);
    }

    public function populateJsonSettings()
    {
        $jsonAttributes = json_decode($this->jsonSettings, true);
        if(!$jsonAttributes){
            return;
        }

        foreach ($jsonAttributes as $attributeKey => $attributeValue) {
            if(property_exists($this, $attributeKey) && in_array($attributeKey, self::JSON_PROPERTIES)){
                $this->{$attributeKey} = $attributeValue;
            }
        }
    }

    public function prepareJsonSettings()
    {
        $jsonSettings = [];
        foreach ($this::JSON_PROPERTIES as $property) {
            $jsonSettings[$property] = $this->{$property};
        }
        $jsonSettings = json_encode($jsonSettings);
        return $jsonSettings;
    }

    public function getSortOptions()
    {
        return [
            [
                'value' => self::SORT_DEFAULT,
                'label' => Craft::t('quick-filters', 'Default order'),
            ],
            [
                'value' => self::SORT_ALPHABETICALLY,
                'label' => Craft::t('quick-filters', 'Alphabetically'),
            ],
        ];
    }

    public function getDatepickerOptions()
    {
        return [
            [
                'value' => self::DATEPICKER_RANGE,
                'label' => Craft::t('quick-filters', 'Date range (two date pickers)'),
            ],
            [
                'value' => self::DATEPICKER_DAY,
                'label' => Craft::t('quick-filters', 'Day selection (one date picker)'),
            ],
        ];
    }
    
    public function getDropdownModeOptions()
    {
        return [
            [
                'value' => self::DROPDOWN_MODE_DEFAULT,
                'label' => Craft::t('quick-filters', 'Display all options'),
            ],
            [
                'value' => self::DROPDOWN_MODE_AJAX,
                'label' => Craft::t('quick-filters', 'Ajax mode'),
            ],
        ];
    }

    public function getFieldIdsUsingDropdownMode()
    {
        $craftFields = $this->getAllLayoutFields();
        $craftFields = array_filter($craftFields, function($single){
//            if(
//                in_array(get_class($single), self::FIELDS_RELATIONS)
//            ){
//                return true;
//            }
            // only entry fields
            if(get_class($single) == 'craft\fields\Entries'){
                return true;
            }
        });
//        $ids = array_column($craftFields, 'id');
//        return $ids;
        $uids = [];
        foreach ($craftFields as $field){
            if(isset($field->layoutElement->uid)){
                $uids[] = $field->layoutElement->uid;
            }
        }
        return $uids;
    }

    public function getOptionsQueryObject()
    {
        $craftField = null;
        if($this->filterType == self::FILTER_TYPE_FIELD){
            $craftField = $this->getFieldObject();
            if(is_null($craftField)){
                return null;
            }
        }else{
            return null;
        }

        // entry
        if(get_class($craftField) == 'craft\fields\Entries'){
            if(is_array($craftField->sources)){
                $handles = [];
                foreach ($craftField->sources as $source) {
                    if($source != 'singles'){
                        $uid = str_replace('section:', '', $source);
                        $section = Craft::$app->getEntries()->getSectionByUid($uid);
                        if($section != null){
                            $handles[] = $section['handle'];
                        }
                    }
                }

                // singles
                foreach ($craftField->sources as $source) {
                    if($source == 'singles'){
                        $singlesSections = Craft::$app->getEntries()->getSectionsByType('single');
                        $singleHandles = array_column($singlesSections, 'handle');
                        $handles = array_merge($singleHandles, $handles);
                    }
                }

                $query = \craft\elements\Entry::find()->
                section($handles)->
                status(null);

            }else if($craftField->sources == '*'){
                $query = \craft\elements\Entry::find()->status(null);
            }

            return $query;
        }
        return null;
    }

    public function getAjaxModeRelationOptions()
    {
        $query = $this->getOptionsQueryObject();
        $request = Craft::$app->getRequest();
        $searchBy = $request->getQueryParam('q');

        $selectedIds = $request->getBodyParam('criteria')[$this->getFilterHandle()] ?? null;

        if(is_null($selectedIds)){
            return [];
        }
        $optionsEntries = $query->limit(10)->id($selectedIds)->all();
        $options = array_map(function($single){
            return [
                'label' => $single->title,
                'value' => $single->id,
                'level' => 1,
            ];
        }, $optionsEntries);
        return $options;
    }

    private function getRelationFieldOptions($craftField)
    {
        $mapArrayRecursively = function ($array, $callback) use (&$mapArrayRecursively) {
            $mappedArray = [];
            foreach ($array as $key => $value) {
                // Apply the callback to the current element
                $mappedArray[$key] = $callback($value);

                // If the element has children and is an array, recursively map them
                if ($value->hasDescendants) {
                    $mappedArray[$key]['children'] = $mapArrayRecursively($value->getChildren(), $callback);
                }

            }
            if($this->orderOptionsBy == self::SORT_ALPHABETICALLY){
                ArrayHelper::multisort($mappedArray, 'label');
            }
            return $mappedArray;
        };

        // entry
        if(get_class($craftField) == 'craft\fields\Entries'){

            if(is_array($craftField->sources)){
                $handlesChannel = [];
                $handlesStructure = [];
                foreach ($craftField->sources as $source) {
                    if($source != 'singles'){
                        $uid = str_replace('section:', '', $source);
                        $section = Craft::$app->getEntries()->getSectionByUid($uid);
                        if($section != null){
                            if($section->type == 'channel'){
                                $handlesChannel[] = $section['handle'];
                            }
                            if($section->type == 'structure'){
                                $handlesStructure[] = $section['handle'];
                            }
                        }
                    }
                }
                $elements = [];
                // level([1, null])-> doesnt work when we query channel and structure for some reason
                $idsToQuery = [];
                if(!empty($handlesStructure)){
                    $ids = \craft\elements\Entry::find()->
                    section($handlesStructure)->
                    status(null)->
                    level(1)->
                    ids();
                    $idsToQuery = array_merge($ids, $idsToQuery);
                }
                if(!empty($handlesChannel)){
                    $ids = \craft\elements\Entry::find()->
                    section($handlesChannel)->
                    status(null)->
                    ids();
                    $idsToQuery = array_merge($ids, $idsToQuery);
                }
                $elements = \craft\elements\Entry::find()->
                id($idsToQuery)->
                status(null)->
                all();

                // singles
                foreach ($craftField->sources as $source) {
                    if($source == 'singles'){
                        $singlesSections = Craft::$app->getEntries()->getSectionsByType('single');
                        $singleHandles = array_column($singlesSections, 'handle');
                        $singles = \craft\elements\Entry::find()->section($singleHandles)->anyStatus()->all();
                        $elements = array_merge($elements, $singles);
                    }
                }

            }else if($craftField->sources == '*'){
                $elements = \craft\elements\Entry::find()->anyStatus()->level([1, null])->all();
            }

            $options = $mapArrayRecursively($elements, function ($single) {
                return [
                    'value' => $single->id,
                    'label' => $single->title,
                    // non structures have level null
                    'level' => $single->level ?? 1,
                    'enabled' => $single->enabled,
                    'children' => [],
                ];
            });

        }

        // category
        if(get_class($craftField) == 'craft\fields\Categories'){
            $uid = str_replace('group:', '', $craftField->source);
            $group = Craft::$app->getCategories()->getGroupByUid($uid);
            if($group != null){
                $handle = Craft::$app->getCategories()->getGroupByUid($uid)['handle'];
                $elements = \craft\elements\Category::find()->anyStatus()->group($handle)->level(1)->all();

                $options = $mapArrayRecursively($elements, function ($single) {
                    return [
                        'value' => $single->id,
                        'label' => $single->title,
                        'level' => $single->level,
                        'enabled' => $single->enabled,
                        'children' => [],
                    ];
                });

            }else{
                $options = [];
            }

        }

        // asset
        if(get_class($craftField) == 'craft\fields\Assets'){
            if(is_array($craftField->sources)){
                $handles = [];
                foreach ($craftField->sources as $source) {
                    $uid = str_replace('volume:', '', $source);
                    $volume = Craft::$app->getVolumes()->getVolumeByUid($uid);
                    if($volume != null){
                        $handles[] = $volume['handle'];
                    }
                }
                $elements = [];
                if(!empty($handles)){
                    $elements = \craft\elements\Asset::find()->anyStatus()->volume($handles)->all();
                }
                
            }else if($craftField->sources == '*'){
                $elements = \craft\elements\Asset::find()->anyStatus()->all();
            }
            $options = array_map(function($single){
                return [
                    'value' => $single->id,
                    'label' => $single->title,
                    'level' => 1,
                ];
            }, $elements);                                  
        }

        // user
        if(get_class($craftField) == 'craft\fields\Users'){
            if(is_array($craftField->sources)){
                $handles = [];
                foreach ($craftField->sources as $source) {
                    // source can be admin which is not uid of group
                    if($source != 'admins'){
                        $uid = str_replace('group:', '', $source);
                        $group = Craft::$app->getUserGroups()->getGroupByUid($uid);
                        if($group != null){
                            $handles[] = $group['handle'];
                        }                        
                    }
                }
                $elements = [];
                if(!empty($handles)){
                    $elements = \craft\elements\User::find()->anyStatus()->group($handles)->all();
                }
                
                // admins
                foreach ($craftField->sources as $source) {
                    if($source == 'admins'){
                        $admins = \craft\elements\User::find()->anyStatus()->admin()->all();
                        $elements = array_merge($elements, $admins);
                    }
                }

            }else if($craftField->sources == '*'){
                $elements = \craft\elements\User::find()->anyStatus()->all();
            }

            // remove duplicate users which belonged to user groups and were admins
            $unique = array_intersect_key($elements, array_unique(array_column($elements, 'id')));

            $options = array_map(function($single){
                return [
                    'value' => $single->id,
                    'label' => $single->friendlyName,
                    'level' => 1,
                ];
            }, $unique);                                  
        }

        // tag
        if(get_class($craftField) == 'craft\fields\Tags'){
            $uid = str_replace('taggroup:', '', $craftField->source);
            $group = Craft::$app->getTags()->getTagGroupByUid($uid);
            if($group != null){
                $handle = $group['handle'];
                $elements = \craft\elements\Tag::find()->anyStatus()->group($handle)->all();
                $options = array_map(function($single){
                    return [
                        'value' => $single->id,
                        'label' => $single->title,
                        'level' => 1,
                    ];
                }, $elements);
            }else{
                $options = [];
            }                   
        }               

        // commerce product
        if(get_class($craftField) == 'craft\commerce\fields\Products' && Craft::$app->plugins->isPluginEnabled('commerce')){
            if(is_array($craftField->sources)){
                $handles = [];
                foreach ($craftField->sources as $source) {
                    $uid = str_replace('productType:', '', $source);
                    $productType = \craft\commerce\Plugin::getInstance()->getProductTypes()->getProductTypeByUid($uid);
                    if($productType != null){
                        $handles[] = $productType['handle'];
                    }
                }
                $elements = [];
                if(!empty($handles)){
                    $elements = \craft\commerce\elements\Product::find()->anyStatus()->type($handles)->all();
                }              

            }else if($craftField->sources == '*'){
                $elements = \craft\commerce\elements\Product::find()->anyStatus()->all();
            }
            $options = array_map(function($single){
                return [
                    'value' => $single->id,
                    'label' => $single->title,
                    'level' => 1,
                    'enabled' => $single->enabled,
                ];
            }, $elements);     
        }

        // commerce variant
        if(get_class($craftField) == 'craft\commerce\fields\Variants' && Craft::$app->plugins->isPluginEnabled('commerce')){
            if(is_array($craftField->sources)){
                $typeIds = [];
                foreach ($craftField->sources as $source) {
                    $uid = str_replace('productType:', '', $source);
                    $type = \craft\commerce\Plugin::getInstance()->getProductTypes()->getProductTypeByUid($uid);
                    if($type != null){
                        $typeIds[] = $type['id'];
                    }
                }
                $elements = [];
                if($typeIds != null){
                    $elements = \craft\commerce\elements\Variant::find()->anyStatus()->typeId($typeIds)->all();
                }                

            }else if($craftField->sources == '*'){
                $elements = \craft\commerce\elements\Variant::find()->anyStatus()->all();
            }
            $options = array_map(function($single){
                return [
                    'value' => $single->id,
                    'label' => $single->title,
                    'level' => 1,
                    'enabled' => $single->enabled,
                ];
            }, $elements);     
        }


        return $options ?? [];
    }

    private function getSwatchesOptionCss(array $option)
    {
        // multiple hex values are separated by comma
        $string = '';
        $coloursHexStrings = explode(',', $option['color']);
        if(!empty($coloursHexStrings)){
                $percentageNumber = 100 / count($coloursHexStrings);
                $string .= 'linear-gradient(to bottom right,';
                $loopIndex = 0;
                foreach ($coloursHexStrings as $singleHex) {
                    $percent = $percentageNumber * $loopIndex;
                    $percentSecond = $percent + $percentageNumber;
                    $string .= (' ' . $singleHex . ' ' . $percent . '% ' . $percentSecond . '%');
                    if($loopIndex + 1 != count($coloursHexStrings)){
                        $string .= ',';
                    }
                    $loopIndex ++;
                }
                $string .= ')';
        }
        return $string;
    }

    private function getOptions($field)
    {
        $options = [];

        // relations field
        if(in_array(get_class($field), self::FIELDS_RELATIONS)){
            $options = $this->getRelationFieldOptions($field);
        }

        // options field
        if(in_array(get_class($field), self::FIELDS_OPTIONS)){
            $options = array_map(function($single){
                return [
                    'value' => $single['value'],
                    'label' => $single['label'],
                    'level' => 1,
                ];
            }, array_filter($field->options, function($single){
                return !array_key_exists('optgroup', $single);
            }));
            if($this->orderOptionsBy == self::SORT_ALPHABETICALLY){
                ArrayHelper::multisort($options, 'label');
            }
        }

        // color
        if(in_array(get_class($field), self::FIELDS_COLOR)){
            $options = array_map(function($single){

                // color swatches stores value in content table as json
                $value  = [
                    'label' => $single['label'],
                    'color' => $single['color'],
                    'class' => $single['class'],
                ];
                $value = json_encode($value);

                return [
                    'value' => $value,
                    'label' => $single['label'],
                    'level' => 1,
                    'color' => $this->getSwatchesOptionCss($single),
                ];
            }, $field->options);
            if($this->orderOptionsBy == self::SORT_ALPHABETICALLY){
                ArrayHelper::multisort($options, 'label');
            }
        }


        // add "has any value" and "is empty" options
        if(in_array(get_class($field), self::FIELDS_RELATIONS) || in_array(get_class($field), self::FIELDS_OPTIONS) || in_array(get_class($field), self::FIELDS_COLOR)){
            $options = array_merge([
                [
                    'value' => ':notempty:',
                    'label' => '[' . Craft::t('quick-filters','Has any value') . ']',
                    'level' => 1,
                ],
                [
                    'value' => ':empty:',
                    'label' => '[' . Craft::t('quick-filters','Is empty') . ']',
                    'level' => 1,
                ],            
            ], $options);
        }

        // lightswitch
        if(in_array(get_class($field), self::FIELDS_SWITCH)){
            $onLabel = $field->onLabel ? $field->onLabel : Craft::t('quick-filters','Enabled');
            $offLabel = $field->offLabel ? $field->offLabel : Craft::t('quick-filters','Disabled');
            $options = [
                [
                    'label' => Craft::t('quick-filters','Select value'),
                    'value' => '',
                    'level' => 1,
                ],                
                [
                    'label' => $onLabel,
                    'value' => 1,
                    'level' => 1,
                ],
                [
                    'label' => $offLabel,
                    'value' => 0,
                    'level' => 1,
                ],
            ];
        }
        return $options;
    }

    private function getSelectType($field)
    {
        $type = null;
        if(in_array(get_class($field), self::FIELDS_RELATIONS)){
            $type = self::SELECT_TYPE_RELATION;
        }

        if(in_array(get_class($field), self::FIELDS_OPTIONS)){
            $type = self::SELECT_TYPE_OPTIONS;
        }

        if(in_array(get_class($field), self::FIELDS_SWITCH)){
            $type = self::SELECT_TYPE_SWITCH;
        }
        if(in_array(get_class($field), self::FIELDS_COLOR)){
            $type = self::SELECT_TYPE_COLOR;
        }        
        return $type;
    }

    public function getFieldsIdsUsingSelect()
    {
        $craftFields = $this->getAvaibleFields();
        $craftFields = array_filter($craftFields, function($single){
            if(
                in_array(get_class($single), self::FIELDS_RELATIONS) ||
                in_array(get_class($single), self::FIELDS_OPTIONS) ||
                in_array(get_class($single), self::FIELDS_COLOR)
            ){
                return true;
            }
        });
//        $ids = array_column($craftFields, 'id');
//        return $ids;
        $uids = [];
        foreach ($craftFields as $field){
            if(isset($field->layoutElement->uid)){
                $uids[] = $field->layoutElement->uid;
            }
        }
        return $uids;
    }

    public function getFieldIdsUsingDatepicker()
    {
        $craftFields = $this->getAvaibleFields();
        $craftFields = array_filter($craftFields, function($single){
            if(
                in_array(get_class($single), self::FIELDS_DATE)
            ){
                return true;
            }
        });
//        $ids = array_column($craftFields, 'id');
//        return $ids;
        $uids = [];
        foreach ($craftFields as $field){
            if(isset($field->layoutElement->uid)){
                $uids[] = $field->layoutElement->uid;
            }
        }
        return $uids;
    }

    public function render()
    {

        $field = null;
        if($this->filterType == self::FILTER_TYPE_FIELD){
            $field = $this->getFieldObject();
            if(is_null($field)){
                return null;
            }
        }

        // select
        if(
            $this->filterType == self::FILTER_TYPE_FIELD &&
            $field != null &&
            (
                in_array(get_class($field), self::FIELDS_RELATIONS) || 
                in_array(get_class($field), self::FIELDS_OPTIONS) || 
                in_array(get_class($field), self::FIELDS_SWITCH) ||
                in_array(get_class($field), self::FIELDS_COLOR)
            )
        ){
            $options = $this->dropdownMode == self::DROPDOWN_MODE_AJAX ? $this->getAjaxModeRelationOptions() : $this->getOptions($field);
            $context = [
                'type' => $this->getSelectType($field),
                'options' => $options,
                'placeholder' => $this->getName(),
                'handle' => $this->getFilterHandle(),
                // switch does nto allow multiple options selection
                'multiple' => !in_array(get_class($field), self::FIELDS_SWITCH),
                'mode' => $this->dropdownMode,
                'filterId' => $this->id,
            ];
            $template = self::WIDGET_SELECT_TEMPLATE;
        }

        // select entry type
        if(
            $this->filterType == self::FILTER_TYPE_ATTRIBUTE &&
            $this->elementAttribute == 'typeId'
        ){
            $context = [
                'type' => self::SELECT_TYPE_OPTIONS,
                'options' => $this->getEntryTypeOptions(),
                'placeholder' => $this->getName(),
                'handle' => $this->getFilterHandle(),
                'multiple' => true,
                'mode' => $this->dropdownMode,
                'filterId' => $this->id,
            ];
            $template = self::WIDGET_SELECT_TEMPLATE;
        }

        // datepicker
        if(

            (
                $this->filterType == self::FILTER_TYPE_FIELD &&
                $field != null &&
                in_array(get_class($field), self::FIELDS_DATE)
            ) ||
            (
                $this->filterType == self::FILTER_TYPE_ATTRIBUTE &&
                ($this->getAttributeData()['type'] ?? null) == self::ATTRIBUTE_TYPE_DATE
            )
        ){

            if(Craft::$app->getUser()->getIdentity()->getPreferredLocale() != null){
                $localeHandle = Craft::$app->getUser()->getIdentity()->getPreferredLocale();
            }else{
                $localeHandle =  Craft::$app->getSites()->currentSite->language;
            }
            $locale = Craft::$app->getI18n()->getLocaleById($localeHandle);

            // uppercase because rangedatepicker did not accepted default format
            $dateFormat = strtoupper($locale->getDateFormat('short'));

            $context = [
                'handle' => $this->getFilterHandle(),
                'dateFormat' => $dateFormat,
                'label' => $this->getName(),
            ];
            if($this->datePickerType == self::DATEPICKER_DAY){
                $template = self::WIDGET_DATE_TEMPLATE_DAY;
            }else{
                $template = self::WIDGET_DATE_TEMPLATE;
            }
        }

        // range 
        if(
            (
                $this->filterType == self::FILTER_TYPE_FIELD &&
                $field != null &&
                in_array(get_class($field), self::FILEDS_NUMBER)
            ) ||
            (
                $this->filterType == self::FILTER_TYPE_ATTRIBUTE &&
                ($this->getAttributeData()['type'] ?? null) == self::ATTRIBUTE_TYPE_NUMBER
            )
        ){
            $context = [
                'handle' => $this->getFilterHandle(),
                'label' => $this->getName(),
            ];            
            $template = self::WIDGET_RANGE_TEMPLATE;
        }

        // text
        if(
            (
                $this->filterType == self::FILTER_TYPE_FIELD &&
                $field != null &&
                in_array(get_class($field), self::FIELDS_TEXT)
            ) ||
            (
                $this->filterType == self::FILTER_TYPE_ATTRIBUTE &&
                ($this->getAttributeData()['type'] ?? null) == self::ATTRIBUTE_TYPE_TEXT
            )
        ){
            $context = [
                'handle' => $this->getFilterHandle(),
                'label' => $this->getName(),
            ];            
            $template = self::WIDGET_TEXT_TEMPLATE;
        }

        // if field does not exists
        if(!isset($template) || !isset($context)){
            return null;
        }

        // render
        $html = Craft::$app->getView()->renderTemplate(
            $template, 
            $context,
            Craft::$app->view::TEMPLATE_MODE_CP
        );
        $html = Template::raw($html);
        return $html;
    }

    public function getAllLayoutFields()
    {
        $elementTypeString = null;
        if($this->elementType == 'entries'){
            $elementTypeString = 'craft\elements\Entry';
        }
        if($this->elementType == 'categories'){
            $elementTypeString = 'craft\elements\Category';
        }
        if($this->elementType == 'users'){
            $elementTypeString = 'craft\elements\User';
        }
        if($this->elementType == 'assets'){
            $elementTypeString = 'craft\elements\Asset';
        }
        if($this->elementType == 'orders'){
            $elementTypeString = 'craft\commerce\elements\Order';
        }
        if($this->elementType == 'products'){
            $elementTypeString = 'craft\commerce\elements\Product';
        }

        $craftFields = [];
        foreach (Craft::$app->getFields()->getLayoutsByType($elementTypeString) as $fieldLayout) {
            foreach ($fieldLayout->getCustomFields() as $field) {
                if ($field instanceof FieldInterface) {
                    $craftFields[] = $field;
                }
            }
        }
        return $craftFields;
    }

    public function getAvaibleFields()
    {
//        $craftFields = Craft::$app->getFields()->allFields;
        $craftFields = $this->getAllLayoutFields();
        $craftFields = array_filter($craftFields, function($single){
            if(
                in_array(get_class($single), self::FIELDS_RELATIONS) || 
                in_array(get_class($single), self::FIELDS_OPTIONS) || 
                in_array(get_class($single), self::FIELDS_SWITCH) ||
                in_array(get_class($single), self::FIELDS_DATE) ||
                in_array(get_class($single), self::FILEDS_NUMBER) ||
                in_array(get_class($single), self::FIELDS_TEXT) ||
                in_array(get_class($single), self::FIELDS_COLOR)
            ){
                return true;
            }
        });
        return $craftFields;
    }

    public function getAvaibleFieldsOptions()
    {
        $fields = $this->getAvaibleFields();
        return array_map(function($single){

            $label = $single->name;

            // for entries, add prefix with field layout name
            if(
                $this->elementType == 'entries' ||
                $this->elementType == 'assets' ||
                $this->elementType == 'categories' ||
                $this->elementType == 'products'
            ){
                if($this->elementType == 'entries'){
                    $types = Craft::$app->entries->getAllEntryTypes();
                }
                if($this->elementType == 'assets'){
                    $types = Craft::$app->volumes->getAllVolumes();
                }
                if($this->elementType == 'categories'){
                    $types = Craft::$app->categories->getAllGroups();
                }
                if($this->elementType == 'products'){
                    $types = \craft\commerce\Plugin::getInstance()->getProductTypes()->getAllProductTypes();
                }

                $layout = $single->layoutElement->layout;
                $type = array_filter($types, function($singleType) use ($layout){
                    return $singleType->fieldLayout->id == $layout->id;
                });
                $type = reset($type);
                $label = $type->name . ' - ' . $label;

            }

            return [
                'label' => $label,
                'value' => $single->layoutElement->uid,
            ];
        }, $fields);
    }

    public function getFieldObject()
    {
        $field = null;
        if(is_null($this->fieldUidInLayout)){
            return $field;
        }
        foreach ($this->getAllLayoutFields() as $singleField){
            if(($singleField->layoutElement->uid ?? null) == $this->fieldUidInLayout){
                $field = $singleField;
                break;
            }
        }
        return $field;
    }

    public function getFilterTypeOptions()
    {
        $types = [
            [
                'label' => Craft::t('quick-filters','Field'),                
                'value' => self::FILTER_TYPE_FIELD,
            ],
            [
                'label' => Craft::t('quick-filters','Element attribute'),                
                'value' => self::FILTER_TYPE_ATTRIBUTE,
            ],
        ];

        return $types;
    }

    private function getAttributeData()
    {
        $attribute = ArrayHelper::firstWhere($this->getAvailableAttributes(), 'attribute', $this->elementAttribute);
        return $attribute;
    }

    public function getName()
    {
        $name = null;
        if($this->filterType == self::FILTER_TYPE_FIELD){
            $field = $this->getFieldObject();
            if($field != null){
                $name = $field->name;
            }
        }else if($this->filterType == self::FILTER_TYPE_ATTRIBUTE){
            $attribute = $this->getAttributeData();
            if($attribute != null){
                $name = $attribute['label'];
            }            
        }

        if($name == null){
            $name = '[' . Craft::t('quick-filters','Field class missing or element attribute does not exist') . ']';
        }

        return $name;
    }


    public function getFilterTypeName()
    {
        $type = ArrayHelper::firstWhere($this->getFilterTypeOptions(), 'value', $this->filterType);
        $typeName = $type['label'] ?? null;
        return $typeName;
    }

    public function getFilterHandle()
    {
        $handle = null;
        if($this->filterType == self::FILTER_TYPE_FIELD){
            $field = $this->getFieldObject();
            if(is_null($field)){
                return null;
            }
            $handle = $field->handle;
        }else if($this->filterType == self::FILTER_TYPE_ATTRIBUTE){
            $handle = $this->elementAttribute;
        }
        return $handle;
    }


    public function getAvailableAttributes()
    {

        $attributes = [];

        $elementType = $this->elementType;

        switch ($elementType) {
            

            case 'entries';

                $attributes = [
                    [
                        'label' => Craft::t('app', 'Title'),
                        'attribute' => 'title',
                        'type' => self::ATTRIBUTE_TYPE_TEXT,
                    ],
                    [
                        'label' => Craft::t('app', 'Post Date'),
                        'attribute' => 'postDate',
                        'type' => self::ATTRIBUTE_TYPE_DATE,
                    ],
                    [
                        'label' => Craft::t('app', 'Expiry Date'),
                        'attribute' => 'expiryDate',
                        'type' => self::ATTRIBUTE_TYPE_DATE,
                    ],
                    [
                        'label' => Craft::t('app', 'Date Created'),
                        'attribute' => 'dateCreated',
                        'type' => self::ATTRIBUTE_TYPE_DATE,
                    ],
                    [
                        'label' => Craft::t('app', 'Date Updated'),
                        'attribute' => 'dateUpdated',
                        'type' => self::ATTRIBUTE_TYPE_DATE,
                    ],
                    [
                        'label' => Craft::t('app', 'Entry Type'),
                        'attribute' => 'typeId',
                        'type' => self::ATTRIBUTE_TYPE_SELECT,
                    ],                    
                ];

                break;

            case 'categories';

                $attributes = [
                    [
                        'label' => Craft::t('app', 'Title'),
                        'attribute' => 'title',
                        'type' => self::ATTRIBUTE_TYPE_TEXT,
                    ],                    
                    [
                        'label' => Craft::t('app', 'Date Created'),
                        'attribute' => 'dateCreated',
                        'type' => self::ATTRIBUTE_TYPE_DATE,
                    ],
                    [
                        'label' => Craft::t('app', 'Date Updated'),
                        'attribute' => 'dateUpdated',
                        'type' => self::ATTRIBUTE_TYPE_DATE,
                    ],
                ];

                break;

            case 'assets';

                $attributes = [
                    [
                        'label' => Craft::t('app', 'Title'),
                        'attribute' => 'title',
                        'type' => self::ATTRIBUTE_TYPE_TEXT,
                    ],                      
                    [
                        'label' => Craft::t('app', 'Date Created'),
                        'attribute' => 'dateCreated',
                        'type' => self::ATTRIBUTE_TYPE_DATE,
                    ],
                    [
                        'label' => Craft::t('app', 'Date Updated'),
                        'attribute' => 'dateUpdated',
                        'type' => self::ATTRIBUTE_TYPE_DATE,
                    ],
                ];

                break;

            case 'users';

                $attributes = [
                    [
                        'label' => Craft::t('app', 'Date Created'),
                        'attribute' => 'dateCreated',
                        'type' => self::ATTRIBUTE_TYPE_DATE,
                    ],
                    [
                        'label' => Craft::t('app', 'Date Updated'),
                        'attribute' => 'dateUpdated',
                        'type' => self::ATTRIBUTE_TYPE_DATE,
                    ],
                    [
                        'label' => Craft::t('app', 'Last Login'),
                        'attribute' => 'lastLoginDate',
                        'type' => self::ATTRIBUTE_TYPE_DATE,
                    ],                    
                ];

                break;

            case 'products';

                $attributes = [
                    [
                        'label' => Craft::t('commerce', 'Title'),
                        'attribute' => 'title',
                        'type' => self::ATTRIBUTE_TYPE_TEXT,
                    ],                         
                    [
                        'label' => Craft::t('commerce', 'Post Date'),
                        'attribute' => 'postDate',
                        'type' => self::ATTRIBUTE_TYPE_DATE,
                    ],

                    [
                        'label' => Craft::t('commerce', 'Expiry Date'),
                        'attribute' => 'expiryDate',
                        'type' => self::ATTRIBUTE_TYPE_DATE,
                    ],
                    [
                        'label' => Craft::t('commerce', 'Date Created'),
                        'attribute' => 'dateCreated',
                        'type' => self::ATTRIBUTE_TYPE_DATE,
                    ],
                    [
                        'label' => Craft::t('commerce', 'Date Updated'),
                        'attribute' => 'dateUpdated',
                        'type' => self::ATTRIBUTE_TYPE_DATE,
                    ],
                    // [
                    //     'label' => Craft::t('commerce', 'Stock'),
                    //     'attribute' => 'stock',
                    //     'type' => self::ATTRIBUTE_TYPE_NUMBER,
                    // ],   
                    [
                        'label' => Craft::t('commerce', 'Price'),
                        'attribute' => 'defaultPrice',
                        'type' => self::ATTRIBUTE_TYPE_NUMBER,
                    ],
                    [
                        'label' => Craft::t('commerce', 'Weight'),
                        'attribute' => 'defaultWeight',
                        'type' => self::ATTRIBUTE_TYPE_NUMBER,
                    ],    
                    [
                        'label' => Craft::t('commerce', 'Length'),
                        'attribute' => 'defaultLength',
                        'type' => self::ATTRIBUTE_TYPE_NUMBER,
                    ],    
                    [
                        'label' => Craft::t('commerce', 'Width'),
                        'attribute' => 'defaultWidth',
                        'type' => self::ATTRIBUTE_TYPE_NUMBER,
                    ],    
                    [
                        'label' => Craft::t('commerce', 'Height'),
                        'attribute' => 'defaultHeight',
                        'type' => self::ATTRIBUTE_TYPE_NUMBER,
                    ],                        

                ];

                break;

            case 'orders';

                $attributes = [
                    [
                        'label' => Craft::t('commerce', 'Date Ordered'),
                        'attribute' => 'dateOrdered',
                        'type' => self::ATTRIBUTE_TYPE_DATE,
                    ],  
                    [
                        'label' => Craft::t('commerce', 'Date Paid'),
                        'attribute' => 'datePaid',
                        'type' => self::ATTRIBUTE_TYPE_DATE,
                    ],  
                    [
                        'label' => Craft::t('commerce', 'Date Created'),
                        'attribute' => 'dateCreated',
                        'type' => self::ATTRIBUTE_TYPE_DATE,
                    ],  
                    [
                        'label' => Craft::t('commerce', 'Date Updated'),
                        'attribute' => 'dateUpdated',
                        'type' => self::ATTRIBUTE_TYPE_DATE,
                    ],  
                    // [
                    //     'label' => Craft::t('commerce', 'All Totals'),
                    //     'attribute' => 'totals',
                    //     'type' => self::ATTRIBUTE_TYPE_NUMBER,
                    // ],   
                    // [
                    //     'label' => Craft::t('commerce', 'Total'),
                    //     'attribute' => 'total',
                    //     'type' => self::ATTRIBUTE_TYPE_NUMBER,
                    // ],   
                    // [
                    //     'label' => Craft::t('commerce', 'Total Price'),
                    //     'attribute' => 'totalPrice',
                    //     'type' => self::ATTRIBUTE_TYPE_NUMBER,
                    // ],   
                    // [
                    //     'label' => Craft::t('commerce', 'Total Paid'),
                    //     'attribute' => 'totalPaid',
                    //     'type' => self::ATTRIBUTE_TYPE_NUMBER,
                    // ],   
                    // [
                    //     'label' => Craft::t('commerce', 'Total Discount'),
                    //     'attribute' => 'totalDiscount',
                    //     'type' => self::ATTRIBUTE_TYPE_NUMBER,
                    // ],   
                    // [
                    //     'label' => Craft::t('commerce', 'Total Shipping'),
                    //     'attribute' => 'totalShippingCost',
                    //     'type' => self::ATTRIBUTE_TYPE_NUMBER,
                    // ],   
                    // [
                    //     'label' => Craft::t('commerce', 'Total Tax'),
                    //     'attribute' => 'totalTax',
                    //     'type' => self::ATTRIBUTE_TYPE_NUMBER,
                    // ],                                                                                                                    
                    // [
                    //     'label' => Craft::t('commerce', 'Total Included Tax'),
                    //     'attribute' => 'totalIncludedTax',
                    //     'type' => self::ATTRIBUTE_TYPE_NUMBER,
                    // ],                      
                ];

                break;

        }

        return $attributes;
    }

    // need to distinguish between boolean false (lightswitch) and empty text string
    public function hasValue()
    {
        $criteria = Craft::$app->request->getBodyParam('criteria');
        if($criteria == null || !isset($criteria[$this->getFilterHandle()])){
            return false;
        }
        $value = $criteria[$this->getFilterHandle()];
        if(is_string($value) && empty($value)){
            return false;
        }
        return true;
    }

    public function hasDuplicate()
    {
        $siblings = ElementFilters::getInstance()->filters->getFilters($this->elementType, $this->sourceKey);

        if($this->filterType == self::FILTER_TYPE_FIELD){
            $same = array_filter($siblings, function($single){
                return $single->filterType == self::FILTER_TYPE_FIELD && $single->fieldUidInLayout == $this->fieldUidInLayout && $single->id != $this->id;
            });
            if(!empty($same)){
                return true;
            }
        }

        if($this->filterType == self::FILTER_TYPE_ATTRIBUTE){
            $same = array_filter($siblings, function($single){
                return $single->filterType == self::FILTER_TYPE_ATTRIBUTE && $single->elementAttribute == $this->elementAttribute && $single->id != $this->id;
            });
            if(!empty($same)){
                return true;
            }
        }

        return false;

    }

    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('quick-filters/' . $this->elementType . '/' . $this->sourceKey . '/' . $this->id);
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['id', 'order'], 'number', 'integerOnly' => true];
        $rules[] = [['filterType', 'elementType', 'sourceKey', 'order'], 'required'];
        $rules[] = [['elementType', 'sourceKey'], 'string', 'max' => 255];

        $rules[] = [['fieldUidInLayout'], 'validateField', 'skipOnEmpty' => false];
        $rules[] = [['elementAttribute'], 'validateAttribute', 'skipOnEmpty' => false];

        return $rules;
    }

    public function validateField($attributeName, $params)
    {
        if($this->filterType == self::FILTER_TYPE_FIELD && $this->fieldUidInLayout == null){
            $this->addError($attributeName, Craft::t('quick-filters','You must select Craft field'));
            return false;
        }
        return true;
    }

    public function validateAttribute($attributeName, $params)
    {
        if($this->filterType == self::FILTER_TYPE_ATTRIBUTE && $this->elementAttribute == null){
            $this->addError($attributeName, Craft::t('quick-filters','You must select element attribute'));
            return false;
        }
        return true;
    }

    private function getEntryTypeOptions()
    {
        // only entries
        if($this->elementType != 'entries'){
            return [];
        }

        if($this->sourceKey == 'all' || str_contains($this->sourceKey, 'custom')){
            $entryTypes = Craft::$app->getEntries()->getAllEntryTypes();
        }else{
            $uid = str_replace('section:', '', $this->sourceKey);
            $section = Craft::$app->getEntries()->getSectionByUid($uid);
            if(is_null($section)){
                return [];
            }
            $entryTypes = $section->getEntryTypes();            
        }

        $values = array_map(function($single){
            $label = $single->name;
            return [
                'value' => $single->id,
                'label' => $label,
                'level' => 1,
            ];
        }, $entryTypes);

        // sort by label
        usort($values, function($a, $b){
            return strcasecmp($a['label'], $b['label']);
        });

        return $values;
    }

}
