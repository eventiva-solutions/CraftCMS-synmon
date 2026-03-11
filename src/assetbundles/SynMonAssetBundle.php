<?php

namespace eventiva\synmon\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class SynMonAssetBundle extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/../web';

        $this->depends = [CpAsset::class];

        $this->js = [
            'js/synmon-cp.js',
        ];

        $this->css = [
            'css/synmon-cp.css',
        ];

        parent::init();
    }
}
