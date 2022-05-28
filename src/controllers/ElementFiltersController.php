<?php

namespace craftsnippets\elementfilters\controllers;

use Craft;
use craft\web\Controller;
use craftsnippets\elementfilters\ElementFilters;

use craftsnippets\elementfilters\models\ElementFilter as FilterModel;
use yii\web\NotFoundHttpException;
use yii\web\ServerErrorHttpException;
use craft\helpers\UrlHelper;

class ElementFiltersController extends Controller
{

protected array|int|bool $allowAnonymous = false;

public function actionFilterList($elementType, $sourceKey)
{

    $this->requirePermission('accessCp');
    $this->requirePermission('accessPlugin-quick-filters');

    $groupData = ElementFilters::getInstance()->filters->getElementGroupData($elementType, $sourceKey);

    $elementAndGroupName = $groupData['elementName'] . ' - ' . $groupData['groupName'];
    $newUrl = UrlHelper::cpUrl('quick-filters/' . $elementType . '/' . $sourceKey . '/new');

    $filters = ElementFilters::getInstance()->filters->getFilters($elementType, $sourceKey);
    $filters = array_map(function($single){
        return [
            'title' => $single->name,
            'id' => $single->id,
            'url' => $single->cpEditUrl,
            'filterType' => $single->getFilterTypeName(),
        ];
    }, $filters);

    $context = [
        'bredcrumbUrl' => $groupData['bredcrumbUrl'],
        'elementAndGroupName' => $elementAndGroupName,
        'newUrl' => $newUrl,
        'filters' => $filters,
    ];

    $html = $this->renderTemplate(
        'quick-filters/_filter-list', 
        $context,
        Craft::$app->view::TEMPLATE_MODE_CP
    );
    return $html;

}

public function actionFilterEdit($elementType, $sourceKey, int $filterId = null, FilterModel $filterObject = null)
{

    $this->requirePermission('accessCp');
    $this->requirePermission('accessPlugin-quick-filters');

    if($filterId != null){
        $filterObject = ElementFilters::getInstance()->filters->getFilterById($filterId);
        if(!$filterObject){
            throw new NotFoundHttpException(Craft::t('quick-filters','Filter not found'));
        }
    }else{
        if($filterObject === null){
            $filterObject = new FilterModel;
            $filterObject->elementType = $elementType;
        }            
    }

    $groupData = ElementFilters::getInstance()->filters->getElementGroupData($elementType, $sourceKey);
    $elementAndGroupName = $groupData['elementName'] . ' - ' . $groupData['groupName'];
    
    // field
    $craftFields = array_map(function($single){
        return [
            'value' => $single->id,
            'label' => $single->name,
        ];
    }, $filterObject->avaibleFields);
    $empty = [[
            'value' => null,
            'label' => Craft::t('quick-filters', 'Select field'),
    ]];
    $craftFields = array_merge($empty, $craftFields);

    // filter types
    $filterTypes = $filterObject->getFilterTypeOptions();

    // element attributes
    $elementAttributes = $filterObject->getAvailableAttributes();
    $elementAttributes = array_map(function($single){
        return [
            'label' => $single['label'],
            'value' => $single['attribute'],
        ];
    }, $elementAttributes);
    array_unshift($elementAttributes , [
        'label' => Craft::t('quick-filters', 'Select attribute'),
        'value' => null,        
    ]);

    $context = [
        'bredcrumbUrl' => $groupData['bredcrumbUrl'],
        'elementAndGroupName' => $elementAndGroupName,
        'elementType' => $elementType,              
        'sourceKey' => $sourceKey,          
        'filterObject' => $filterObject,
        'craftFields' => $craftFields,
        'filterTypes' => $filterTypes,
        'elementAttributes' => $elementAttributes,

    ];

    $html = $this->renderTemplate(
        'quick-filters/_filter-edit',
        $context
    );
    return $html;
}

public function actionFilterSave()
{

    $this->requirePermission('accessCp');
    $this->requirePermission('accessPlugin-quick-filters');

    $this->requirePostRequest();
    $request = Craft::$app->getRequest();
    $session = Craft::$app->getSession();    

    $filterId = $request->getBodyParam('filterId');

        if($filterId){
            $filterObject = ElementFilters::getInstance()->filters->getFilterById($filterId);
            if(!$filterObject){
                throw new NotFoundHttpException(Craft::t('quick-filters','Filter not found'));
            }
        }else{
            $filterObject = new FilterModel;
        }

        // set params from POST data
        $filterObject->fieldId = $request->getBodyParam('fieldId');
        $filterObject->elementAttribute = $request->getBodyParam('elementAttribute');
        $filterObject->filterType = $request->getBodyParam('filterType');
        $filterObject->elementType = $request->getBodyParam('elementType');
        $filterObject->sourceKey = $request->getBodyParam('sourceKey');
        // $filterObject->order = 1;

        if($filterObject->hasDuplicate()){
            $this->setFailFlash(Craft::t('quick-filters', 'Such filter for this element list already exists'));
            Craft::$app->getUrlManager()->setRouteParams([
                'filterObject' => $filterObject,
            ]);            
            return null;
        }
        
        // perform save
        $success = ElementFilters::getInstance()->filters->saveFilter($filterObject);

        if (!$success) {
            $this->setFailFlash(Craft::t('quick-filters', 'Could not save filter'));
            Craft::$app->getUrlManager()->setRouteParams([
                'filterObject' => $filterObject,
            ]);
            return null;
        }

        $session->setNotice(Craft::t('quick-filters', 'Filter saved succesfully'));
        return $this->redirectToPostedUrl($filterObject);

}

public function actionFilterDelete()
{

    $this->requirePermission('accessCp');
    $this->requirePermission('accessPlugin-quick-filters');

    $this->requirePostRequest();

    $filterId = $this->request->getBodyParam('id') ?? $this->request->getBodyParam('filterId');
    $filterObject = ElementFilters::getInstance()->filters->getFilterById($filterId);

    $success = ElementFilters::getInstance()->filters->deleteFilterById($filterId);

    if ($this->request->getAcceptsJson()) {
        return $this->asJson([
            'success' => $success,
        ]);
    }

    if(!$success){
        throw new ServerErrorHttpException(Craft::t('craft-filters', 'Unable to delete filter'));
    }

    Craft::$app->getSession()->setNotice(Craft::t('craft-filters', 'Filter deleted'));
    return $this->redirectToPostedUrl();
    
}

public function actionFilterReorder()
{

    $this->requirePermission('accessCp');
    $this->requirePermission('accessPlugin-quick-filters');

    $ids = $this->request->getBodyParam('ids');
    $ids = json_decode($ids);

    ElementFilters::getInstance()->filters->reorderFilters($ids);

    return $this->asJson([
        'success' => true,
    ]);
}


}
