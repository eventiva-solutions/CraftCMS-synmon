<?php

namespace eventiva\synmon\controllers;

use Craft;
use craft\web\Controller;
use eventiva\synmon\SynMon;

class RunsController extends Controller
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
        $request = Craft::$app->getRequest();
        $page    = max(1, (int)$request->getQueryParam('page', 1));
        $suiteId = $request->getQueryParam('suiteId') ? (int)$request->getQueryParam('suiteId') : null;
        $status  = $request->getQueryParam('status');
        $suites  = SynMon::getInstance()->getSuiteService()->getSuites();

        $data = SynMon::getInstance()->getResultService()->getRuns($page, 20, $suiteId, $status);

        return $this->renderTemplate('synmon/cp/runs/index', [
            'title'   => 'Run History',
            'runs'    => $data['runs'],
            'total'   => $data['total'],
            'page'    => $data['page'],
            'perPage' => $data['perPage'],
            'totalPages' => $data['totalPages'],
            'suites'  => $suites,
            'suiteId' => $suiteId,
            'status'  => $status,
        ]);
    }

    public function actionDetail(int $id): \yii\web\Response
    {
        $run = SynMon::getInstance()->getResultService()->getRunById($id);
        if (!$run) {
            throw new \yii\web\NotFoundHttpException("Run #{$id} not found.");
        }

        return $this->renderTemplate('synmon/cp/runs/_detail', [
            'title' => 'Run #' . $id . ' – ' . ($run['suiteName'] ?? ''),
            'run'   => $run,
        ]);
    }

    public function actionDelete(): \yii\web\Response
    {
        $this->requirePostRequest();
        $id = (int)Craft::$app->getRequest()->getRequiredBodyParam('runId');

        Craft::$app->getDb()->createCommand()->delete('{{%synmon_runs}}', ['id' => $id])->execute();
        Craft::$app->getSession()->setNotice('Run gelöscht.');
        return $this->redirect('synmon/runs');
    }

    public function actionPurge(): \yii\web\Response
    {
        $this->requirePostRequest();
        $days    = (int)Craft::$app->getRequest()->getBodyParam('days', 30);
        $deleted = SynMon::getInstance()->getResultService()->deleteOldRuns($days);

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson(['success' => true, 'deleted' => $deleted]);
        }

        Craft::$app->getSession()->setNotice("{$deleted} Runs gelöscht.");
        return $this->redirect('synmon/runs');
    }
}
