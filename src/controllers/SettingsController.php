<?php

namespace eventiva\synmon\controllers;

use Craft;
use craft\web\Controller;
use eventiva\synmon\SynMon;

class SettingsController extends Controller
{
    protected array|int|bool $allowAnonymous = false;

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $this->requireAdmin();
        return true;
    }

    public function actionIndex(): \yii\web\Response
    {
        $settings     = SynMon::getInstance()->getResultService()->getSettings();
        $nodeAvailable = SynMon::getInstance()->getRunnerService()->checkNodeAvailable();
        $pwAvailable   = SynMon::getInstance()->getRunnerService()->checkPlaywrightAvailable();

        return $this->renderTemplate('synmon/cp/settings', [
            'title'          => Craft::t('synmon', 'SynMon Settings'),
            'settings'       => $settings,
            'nodeAvailable'  => $nodeAvailable,
            'pwAvailable'    => $pwAvailable,
        ]);
    }

    public function actionSave(): \yii\web\Response
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();

        $settings = [
            'nodeBinary'       => $request->getBodyParam('nodeBinary', 'node'),
            'defaultTimeout'   => (int)$request->getBodyParam('defaultTimeout', 30000),
            'globalTimeout'    => (int)$request->getBodyParam('globalTimeout', 120),
            'runRetentionDays' => (int)$request->getBodyParam('runRetentionDays', 30),
            'enabled'          => (bool)$request->getBodyParam('enabled', true),
        ];

        SynMon::getInstance()->getResultService()->saveSettings($settings);
        Craft::$app->getSession()->setNotice(Craft::t('synmon', 'Settings saved.'));
        return $this->redirect('synmon/settings');
    }
}
