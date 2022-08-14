<?php

namespace craftsnippets\elementfilters\models;

use Craft;
use craft\base\Model;
use craft\helpers\UrlHelper;
use craft\helpers\Template;
use craft\helpers\ArrayHelper;

use craftsnippets\elementfilters\ElementFilters;

class ElementFilter extends Model
{

    public $id;
    public $uid;

    public $elementType;
    public $sourceKey;
    public $order;
    public $jsonSettings;

    // json properties
    public $fieldId;
    public $elementAttribute;
    public $filterType;

    const JSON_PROPERTIES = [
        'fieldId',
        'elementAttribute',
        'filterType'
    ];

    const FILTER_TYPE_FIELD = 'field';
    const FILTER_TYPE_ATTRIBUTE = 'attribute';

    const WIDGET_SELECT_TEMPLATE = 'quick-filters/_widget-select';
    const WIDGET_DATE_TEMPLATE = 'quick-filters/_widget-datepicker';
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

    const ATTRIBUTE_TYPE_DATE = 'date';
    const ATTRIBUTE_TYPE_NUMBER = 'number';
    const ATTRIBUTE_TYPE_TEXT = 'text';
    const ATTRIBUTE_TYPE_SELECT = 'select';

    const SELECT_TYPE_RELATION = 'relation';
    const SELECT_TYPE_OPTIONS = 'options';
    const SELECT_TYPE_SWITCH = 'switch';

    public function init(): void
    {
        $this->populateJsonSettings();
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


    private function getRelationFieldOptions($craftField)
    {
        // entry
        if(get_class($craftField) == 'craft\fields\Entries'){
            if(is_array($craftField->sources)){
                $handles = [];
                foreach ($craftField->sources as $source) {
                    if($source != 'singles'){
                        $uid = str_replace('section:', '', $source);
                        $section = Craft::$app->getSections()->getSectionByUid($uid);
                        if($section != null){
                            $handles[] = $section['handle'];       
                        }
                                         
                    }
                }
                $elements = [];
                if(!empty($handles)){
                    $elements = \craft\elements\Entry::find()->section($handles)->anyStatus()->all();
                }

                // singles
                foreach ($craftField->sources as $source) {
                    if($source == 'singles'){
                        $singlesSections = Craft::$app->getSections()->getSectionsByType('single');
                        $singleHandles = array_column($singlesSections, 'handle');
                        $singles = \craft\elements\Entry::find()->section($singleHandles)->anyStatus()->all();
                        $elements = array_merge($elements, $singles);
                    }
                }                

            }else if($craftField->sources == '*'){
                $elements = \craft\elements\Entry::find()->anyStatus()->all();
            }
            $options = array_map(function($single){
                return [
                    'value' => $single->id,
                    'label' => $single->title,
                    // non structures have level null
                    'level' => $single->level ?? 1,
                ];
            }, $elements);                    
        }

        // category
        if(get_class($craftField) == 'craft\fields\Categories'){
            $uid = str_replace('group:', '', $craftField->source);
            $group = Craft::$app->getCategories()->getGroupByUid($uid);
            if($group != null){
                $handle = Craft::$app->getCategories()->getGroupByUid($uid)['handle'];
                $elements = \craft\elements\Category::find()->anyStatus()->group($handle)->all();
                $options = array_map(function($single){
                    return [
                        'value' => $single->id,
                        'label' => $single->title,
                        'level' => $single->level,
                    ];
                }, $elements);
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
                ];
            }, $elements);     
        }


        return $options ?? [];
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
        return $type;
    }


    public function render()
    {

        $field = null;
        if($this->filterType == self::FILTER_TYPE_FIELD && $this->fieldId != null){
            $field = Craft::$app->fields->getFieldById($this->fieldId);
        }

        // select
        if(
            $this->filterType == self::FILTER_TYPE_FIELD &&
            $field != null &&
            (
                in_array(get_class($field), self::FIELDS_RELATIONS) || 
                in_array(get_class($field), self::FIELDS_OPTIONS) || 
                in_array(get_class($field), self::FIELDS_SWITCH)
            )
        ){
            $context = [
                'type' => $this->getSelectType($field),
                'options' => $this->getOptions($field),
                'placeholder' => $this->getName(),
                'handle' => $this->getFilterHandle(),
                // switch does nto allow multiple options selection
                'multiple' => !in_array(get_class($field), self::FIELDS_SWITCH),
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
            $template = self::WIDGET_DATE_TEMPLATE;
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



    public function getAvaibleFields()
    {
        $craftFields = Craft::$app->getFields()->allFields;
        $craftFields = array_filter($craftFields, function($single){
            if(
                in_array(get_class($single), self::FIELDS_RELATIONS) || 
                in_array(get_class($single), self::FIELDS_OPTIONS) || 
                in_array(get_class($single), self::FIELDS_SWITCH) ||
                in_array(get_class($single), self::FIELDS_DATE) ||
                in_array(get_class($single), self::FILEDS_NUMBER) ||
                in_array(get_class($single), self::FIELDS_TEXT)
            ){
                return true;
            }
        });
        return $craftFields;
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
        if($this->filterType == self::FILTER_TYPE_FIELD && $this->fieldId != null){
            $field = Craft::$app->fields->getFieldById($this->fieldId);
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
        if($this->filterType == self::FILTER_TYPE_FIELD && $this->fieldId != null){
            $field = Craft::$app->fields->getFieldById($this->fieldId);
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
                return $single->filterType == self::FILTER_TYPE_FIELD && $single->fieldId == $this->fieldId && $single->id != $this->id;
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

        $rules[] = [['fieldId'], 'validateField', 'skipOnEmpty' => false];
        $rules[] = [['elementAttribute'], 'validateAttribute', 'skipOnEmpty' => false];

        return $rules;
    }

    public function validateField($attributeName, $params)
    {
        if($this->filterType == self::FILTER_TYPE_FIELD && $this->fieldId == null){
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
            $entryTypes = Craft::$app->getSections()->getAllEntryTypes();
        }else{
            $uid = str_replace('section:', '', $this->sourceKey);
            $section = Craft::$app->getSections()->getSectionByUid($uid);
            if(is_null($section)){
                return [];
            }
            $entryTypes = $section->getEntryTypes();            
        }

        $values = array_map(function($single){
            $label = $single->name;
            // if we display entry types of multiple sections, show chich section do they belong
            if($this->sourceKey == 'all' || str_contains($this->sourceKey, 'custom')){
                $label = $label . ' (' . $single->section->name . ')';
            }
            return [
                'value' => $single->id,
                'label' => $label,
                'level' => 1,
                'sectionId' => $single->sectionId, // for sorting
            ];            
        }, $entryTypes);

        // sort by section
        usort($values, function($a, $b){
            if ($a['sectionId']  == $b['sectionId'] ) {
                return 0;
            }
            return ($a['sectionId'] < $b['sectionId']) ? -1 : 1;    
        });        

        return $values;
    }

}
