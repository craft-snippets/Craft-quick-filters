<?php

namespace craftsnippets\elementfilters\assetbundles;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;


class ElementFiltersAsset extends AssetBundle
{

    public function init()
    {
        $this->sourcePath = "@craftsnippets/elementfilters/assetbundles";

        $this->depends = [
            CpAsset::class,
        ];
        $this->js = [
            'elementFilters.js',
            'lib/slimselect.min.js',
            'lib/moment.min.js',
        ];
        $this->css = [
            'elementFilters.css',
            'lib/slimselect.min.css',
        ];
        parent::init();
    }
}
