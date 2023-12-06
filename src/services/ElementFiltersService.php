<?php
namespace craftsnippets\elementfilters\services;

use Craft;
use craft\base\Component;

use craftsnippets\elementfilters\records\FilterRecord;
use craftsnippets\elementfilters\models\ElementFilter as FilterModel;

use craft\helpers\ArrayHelper;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;

use craftsnippets\elementfilters\helpers\DbTables;


class ElementFiltersService extends Component{

private $_allFilters;

private function getAllFilters()
{
    if ($this->_allFilters !== null) {
        return $this->_allFilters;
    }

    $record = FilterRecord::find()->all();
    $filters = [];
    foreach ($record as $singleRecord) {
        $filters[] = $this->_createFilterFromRecord($singleRecord);
    }

    $this->_allFilters = $filters;

    return $this->_allFilters;    
}



public function getFilters($elementType, $sourceKey)
{
    $filters = $this->getAllFilters();

    $filters = ArrayHelper::whereIn($filters, 'elementType', [$elementType]);
    $filters = ArrayHelper::whereIn($filters, 'sourceKey', [$sourceKey]);

    // order
    usort($filters, function($a, $b){
        return $a->order - $b->order;
    });

    return $filters;
}

private function getCustomSourceLabel($class, $sourceKey)
{
    $sources = Craft::$app->getElementSources()->getSources($class);
    $custom = array_filter($sources, function($single) use ($sourceKey){
        return isset($single['key']) && $single['key'] == $sourceKey;
    });
    if(!empty($custom)){
        $first = reset($custom);
        $groupName = $first['label'];
        return $groupName;
    }
    return null;
}

public function getElementGroupData($elementType, $sourceKey)
{
    
    $elementName = null;
    $groupName = null;
    $bredcrumbUrl = null;

    switch ($elementType) {

        case 'entries':

            // element name
            $elementName = Craft::t('app', 'Entries');

            // group name
            if($sourceKey == 'all'){
                $groupName = Craft::t('app', 'All entries');
                $bredcrumbUrl = UrlHelper::cpUrl('entries');
            }else if($sourceKey == 'singles'){
                $groupName = Craft::t('app', 'Singles');
                $bredcrumbUrl = $bredcrumbUrl = UrlHelper::cpUrl('entries/singles');
            }else{
                $uid = explode(':', $sourceKey);
                $uid = end($uid);
                $section = Craft::$app->sections->getSectionByUid($uid);
                if(!is_null($section)){
                    $groupName = $section->name;
                    $bredcrumbUrl = UrlHelper::cpUrl('entries/' . $section->handle);                    
                }else{
                    $groupName = $this->getCustomSourceLabel(\craft\elements\Entry::class, $sourceKey);
                    $bredcrumbUrl = UrlHelper::cpUrl('entries'); 
                }
            }
            break;

        case 'categories':

            $elementName = Craft::t('app', 'Categories');

            // explode must not be inside end function or error about reference will appear
            $uid = explode(':', $sourceKey);
            $uid = end($uid);
            $categoryGroup = Craft::$app->categories->getGroupByUid($uid);
            if(!is_null($categoryGroup)){
                $groupName = $categoryGroup->name;
                $bredcrumbUrl = UrlHelper::cpUrl('categories/' . $categoryGroup->handle);
            }else{
                $groupName = $this->getCustomSourceLabel(\craft\elements\Category::class, $sourceKey);
                $bredcrumbUrl = UrlHelper::cpUrl('categories');        
            }

            break;

        case 'users':

            $elementName = Craft::t('app', 'Users');

            if($sourceKey == 'all'){
                $groupName = Craft::t('app', 'All users');
                $bredcrumbUrl = UrlHelper::cpUrl('users/all');
            }else if($sourceKey == 'admins'){
                $groupName = Craft::t('app', 'Admins');
                $bredcrumbUrl = $bredcrumbUrl = UrlHelper::cpUrl('users/admins');
            }else{
                $uid = explode(':', $sourceKey);
                $uid = end($uid);
                $userGroup = Craft::$app->userGroups->getGroupByUid($uid);
                if(!is_null($userGroup)){
                    $groupName = $userGroup->name;
                    $bredcrumbUrl = UrlHelper::cpUrl('users/' . $userGroup->handle);
                }else{
                    $groupName = $this->getCustomSourceLabel(\craft\elements\User::class, $sourceKey);
                    $bredcrumbUrl = UrlHelper::cpUrl('users');                     
                }

            }
            break;

        case 'products':

            // commerce products
            if(Craft::$app->plugins->isPluginEnabled('commerce')){

                $elementName = Craft::t('commerce', 'Products');

                if($sourceKey == 'all'){
                    $groupName = Craft::t('commerce', 'All products');
                    $bredcrumbUrl = UrlHelper::cpUrl('commerce/products');
                }else{
                    $uid = explode(':', $sourceKey);
                    $uid = end($uid);
                    $productType = \craft\commerce\Plugin::getInstance()->getProductTypes()->getProductTypeByUid($uid);
                    if(!is_null($productType)){
                        $groupName = $productType->name;
                        $bredcrumbUrl = UrlHelper::cpUrl('commerce/products/' . $productType->handle);
                    }else{
                        $groupName = $this->getCustomSourceLabel(\craft\commerce\elements\Product::class, $sourceKey);
                        $bredcrumbUrl = UrlHelper::cpUrl('commerce/products/');
                    }
                }
            }

            break;

        case 'orders';
            if(Craft::$app->plugins->isPluginEnabled('commerce')){
                // orders can only be assigned to all group
                $elementName = Craft::t('commerce', 'Orders');
                $groupName = Craft::t('quick-filters', 'All');
                $bredcrumbUrl = UrlHelper::cpUrl('commerce/orders');
            }

            break;

        // assets
        case 'assets';

            $elementName = Craft::t('commerce', 'Assets');
            
            $uid = explode(':', $sourceKey);
            $uid = end($uid);
            $volumeFolder = Craft::$app->assets->getFolderByUid($uid);

            if(!is_null($volumeFolder)){
                // by default volume name returned user_1 or something like that for temporary uploads
                if($volumeFolder->volumeId != null){
                    $groupName = $volumeFolder->name;
                }else{
                    $groupName = Craft::t('app', 'Temporary Uploads');
                }
                $volumeHandle = $volumeFolder->volume->handle != null ? $volumeFolder->volume->handle : 'temp';
                $bredcrumbUrl = UrlHelper::cpUrl('assets/' . $volumeHandle);
            }else{
                $groupName = $this->getCustomSourceLabel(\craft\elements\Asset::class, $sourceKey);
                $bredcrumbUrl = UrlHelper::cpUrl('assets/');
            }


            break;

    }



    // filters
    $filters = $this->getFilters($elementType, $sourceKey);


    // $data = compact($elementName, $groupName, $filters);
    $data = [
        'elementName' => $elementName,
        'groupName' => $groupName,
        'bredcrumbUrl' => $bredcrumbUrl,
    ];
    return $data;
}



public function getFilterById($filterId)
{
    return ArrayHelper::firstWhere($this->getAllFilters(), 'id', $filterId);
}

public function saveFilter(FilterModel $filterModel)
{
    $isNew = !$filterModel->id;

    if ($isNew) {
        $filterModel->uid = StringHelper::UUID();
    } 

    // order
    if($isNew){
        $siblings = $this->getFilters($filterModel->elementType, $filterModel->sourceKey);
        if(empty($siblings)){
            $filterModel->order = 1;
        }else{
            $highestOrder = max(array_column($siblings, 'order'));
            $filterModel->order = $highestOrder + 1;            
        }
    }

    if (!$filterModel->validate()) {
        return false;
    }

    $filterRecord = FilterRecord::find()->andWhere(['uid' => $filterModel->uid])->one() ?? new FilterRecord();

    // set properties
    $filterRecord->elementType = $filterModel->elementType;
    $filterRecord->sourceKey = $filterModel->sourceKey;
    $filterRecord->order = $filterModel->order;
    $filterRecord->jsonSettings = $filterModel->prepareJsonSettings();

    // save
    $result = $filterRecord->save(false);
    return $result;
}

public function deleteFilterById($filterId)
{
    $filterObject = $this->getFilterById($filterId);

    if (!$filterObject) {
        return false;
    }

    return Craft::$app->getDb()->createCommand()->delete(DbTables::FILTERS, ['id' => $filterObject->id])->execute();    
}

public function reorderFilters($ids)
{
    foreach ($ids as $index => $id) {
        $filterObject = $this->getFilterById($id);
        $filterObject->order = $index + 1;
        $this->saveFilter($filterObject);
    }    
}


private function _createFilterFromRecord(FilterRecord $record = null)
{
    if (!$record) {
        return null;
    }

    $filter = new FilterModel($record->toArray([
        'id',
        'uid',
        'elementType',
        'sourceKey',
        'order',
        'jsonSettings',
    ]));

    return $filter;
}

public function removeFiltersOnEvents()
{
    // on field delete
    \yii\base\Event::on(
        \craft\base\Field::class,
        \craft\base\Field::EVENT_AFTER_DELETE,
        function (\yii\base\Event $event) {
            $fieldId = $event->sender->id;
            $filters = ArrayHelper::whereIn($this->getAllFilters(), 'fieldId', [$fieldId]);
            foreach ($filters as $filter) {
                $this->deleteFilterById($filter->id);
            }
        }
    );

    // on section delete
    \yii\base\Event::on(
        \craft\services\Sections::class,
        \craft\services\Sections::EVENT_BEFORE_DELETE_SECTION,
        function (\craft\events\SectionEvent $event) {
            $uid = 'section:' . $event->section->uid;
            $filters = ArrayHelper::whereIn($this->getAllFilters(), 'sourceKey', [$uid]);
            foreach ($filters as $filter) {
                $this->deleteFilterById($filter->id);
            }            
        }
    );

    // on category group delete
    \yii\base\Event::on(
        \craft\services\Categories::class,
        \craft\services\Categories::EVENT_BEFORE_DELETE_GROUP,
        function (\craft\events\CategoryGroupEvent $event) {
            $uid = 'group:' . $event->categoryGroup->uid;
            $filters = ArrayHelper::whereIn($this->getAllFilters(), 'sourceKey', [$uid]);
            foreach ($filters as $filter) {
                $this->deleteFilterById($filter->id);
            }                
        }
    );

    // on user group delete
    \yii\base\Event::on(
        \craft\services\UserGroups::class,
        \craft\services\UserGroups::EVENT_BEFORE_DELETE_USER_GROUP,
        function (\craft\events\UserGroupEvent $event) {
            $uid = 'group:' . $event->userGroup->uid;
            $filters = ArrayHelper::whereIn($this->getAllFilters(), 'sourceKey', [$uid]);
            foreach ($filters as $filter) {
                $this->deleteFilterById($filter->id);
            }                  
        }
    );

    // on asset volume delete
    \yii\base\Event::on(
        \craft\services\Volumes::class,
        \craft\services\Volumes::EVENT_BEFORE_DELETE_VOLUME,
        function (\craft\events\VolumeEvent $event) {
            
            // get first folder of volume
            $rootFolder = Craft::$app->getAssets()->findFolder([
                'volumeId' => $event->volume->id,
                'parentId' => ':empty:',
            ]);
            $uid = 'folder:' . $rootFolder->uid;
            $filters = ArrayHelper::whereIn($this->getAllFilters(), 'sourceKey', [$uid]);
            foreach ($filters as $filter) {
                $this->deleteFilterById($filter->id);
            }
        }
    );    



    // on commerce product type delete

    // TODO


    // commerce uninstall plugin
    \yii\base\Event::on(
        \craft\services\Plugins::class,
        \craft\services\Plugins::EVENT_BEFORE_UNINSTALL_PLUGIN,
        function (\craft\events\PluginEvent $event) {
            if($event->plugin->handle != 'commerce'){
                return;
            }
            $filters = ArrayHelper::whereIn($this->getAllFilters(), 'elementType', ['orders', 'products']);
            foreach ($filters as $filter) {
                $this->deleteFilterById($filter->id);
            }
        }
    );

}

public function injectFilterHtml()
{

    \yii\base\Event::on(
        \craft\web\View::class,
        \craft\web\View::EVENT_AFTER_RENDER_TEMPLATE,
        function (\craft\events\TemplateEvent $event) {

            if($event->template != '_elements/tableview/container'){
                return;
            }

            $elementType = Craft::$app->getRequest()->getBodyParam('elementType');
            $sourceKey = Craft::$app->getRequest()->getBodyParam('source');

            // cannot use * in url
            if($sourceKey == '*'){
                $sourceKey = 'all';
            }

            // each element
            if($elementType == 'craft\elements\Entry'){
                $elementType = 'entries';
            }
            if($elementType == 'craft\elements\Category'){
                $elementType = 'categories';
            }            
            if($elementType == 'craft\elements\User'){
                $elementType = 'users';
            }      
            // assets use volume handle not uid as sourcekey
            if($elementType == 'craft\elements\Asset'){
                $elementType = 'assets';
                // multiple uids are in string with nested folders
                $sourceKey = explode('/', $sourceKey)[0];                
            }    
            if($elementType == 'craft\commerce\elements\Order'){
                $elementType = 'orders';
                // orders do not have separate sourcekey
                $sourceKey = 'all';
            }    
            if($elementType == 'craft\commerce\elements\Product'){
                $elementType = 'products';
            }    

            // inject filters
            $filters = $this->getFilters($elementType, $sourceKey);
            $context = [
                'filters' => $filters,
            ];
            $filtersHtml = \Craft::$app->view->renderTemplate(
                'quick-filters/_filter-widget-group', 
                $context,
                Craft::$app->view::TEMPLATE_MODE_CP
            );
            $event->output = $filtersHtml . $event->output;
        }
    );   

}

public function injectAssets()
{
    if (!Craft::$app->getRequest()->getIsCpRequest()) {
        return false;
    }
    $currentUser = Craft::$app->getUser()->getIdentity();
    if(is_null($currentUser) || !$currentUser->can('accessCp')){
        return false;
    }

    $hasPermission = $currentUser->can('accessPlugin-quick-filters');
    Craft::$app->view->registerJs(
        'var userCanManageFilters = ' . json_encode($hasPermission) . ';',
        Craft::$app->view::POS_BEGIN
    );

    Craft::$app->view->registerAssetBundle(\craftsnippets\elementfilters\assetbundles\ElementFiltersAsset::class);
}


}