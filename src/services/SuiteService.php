<?php

namespace eventiva\synmon\services;

use Craft;
use craft\helpers\StringHelper;
use eventiva\synmon\records\StepRecord;
use eventiva\synmon\records\SuiteRecord;
use yii\base\Component;

class SuiteService extends Component
{
    public function getSuites(): array
    {
        return SuiteRecord::find()->orderBy('name ASC')->asArray()->all();
    }

    public function getSuiteById(int $id): ?array
    {
        $record = SuiteRecord::findOne($id);
        return $record ? $record->toArray() : null;
    }

    public function createSuite(array $data): int|false
    {
        $record = new SuiteRecord();
        $record->uid              = StringHelper::UUID();
        $record->name             = $data['name'] ?? 'Neue Suite';
        $record->description      = $data['description'] ?? null;
        $record->cronExpression   = $data['cronExpression'] ?? '*/5 * * * *';
        $record->enabled          = (bool)($data['enabled'] ?? true);
        $record->notifyEmail      = $data['notifyEmail'] ?? null;
        $record->notifyWebhookUrl = $data['notifyWebhookUrl'] ?? null;
        $record->notifyOnSuccess  = (bool)($data['notifyOnSuccess'] ?? false);

        if ($record->save()) {
            return $record->id;
        }

        Craft::error('SuiteService::createSuite failed: ' . json_encode($record->errors), __METHOD__);
        return false;
    }

    public function updateSuite(int $id, array $data): bool
    {
        $record = SuiteRecord::findOne($id);
        if (!$record) {
            return false;
        }

        $record->name             = $data['name'] ?? $record->name;
        $record->description      = $data['description'] ?? $record->description;
        $record->cronExpression   = $data['cronExpression'] ?? $record->cronExpression;
        $record->enabled          = (bool)($data['enabled'] ?? $record->enabled);
        $record->notifyEmail      = $data['notifyEmail'] ?? $record->notifyEmail;
        $record->notifyWebhookUrl = $data['notifyWebhookUrl'] ?? $record->notifyWebhookUrl;
        $record->notifyOnSuccess  = (bool)($data['notifyOnSuccess'] ?? $record->notifyOnSuccess);

        if ($record->save()) {
            return true;
        }

        Craft::error('SuiteService::updateSuite failed: ' . json_encode($record->errors), __METHOD__);
        return false;
    }

    public function deleteSuite(int $id): bool
    {
        $record = SuiteRecord::findOne($id);
        if (!$record) {
            return false;
        }
        return (bool)$record->delete();
    }

    public function getStepsBySuiteId(int $suiteId): array
    {
        return StepRecord::find()
            ->where(['suiteId' => $suiteId])
            ->orderBy('sortOrder ASC')
            ->asArray()
            ->all();
    }

    public function saveSteps(int $suiteId, array $steps): void
    {
        StepRecord::deleteAll(['suiteId' => $suiteId]);

        foreach ($steps as $index => $stepData) {
            $record              = new StepRecord();
            $record->uid         = StringHelper::UUID();
            $record->suiteId     = $suiteId;
            $record->sortOrder   = (int)($stepData['sortOrder'] ?? $index);
            $record->type        = $stepData['type'] ?? 'navigate';
            $record->selector    = $stepData['selector'] ?? null;
            $record->value       = $stepData['value'] ?? null;
            $record->description = $stepData['description'] ?? null;
            $record->timeout     = (int)($stepData['timeout'] ?? 30000);
            $record->save();
        }
    }

    public function updateLastRunStatus(int $suiteId, string $status): void
    {
        Craft::$app->getDb()->createCommand()->update(
            '{{%synmon_suites}}',
            [
                'lastRunAt'     => (new \DateTime())->format('Y-m-d H:i:s'),
                'lastRunStatus' => $status,
                'dateUpdated'   => (new \DateTime())->format('Y-m-d H:i:s'),
            ],
            ['id' => $suiteId]
        )->execute();
    }

    public function getStepTypes(): array
    {
        return [
            'navigate'       => ['label' => 'Navigate',          'hasSelector' => false, 'hasValue' => true,  'valuePlaceholder' => 'https://example.com'],
            'click'          => ['label' => 'Click',             'hasSelector' => true,  'hasValue' => false, 'selectorPlaceholder' => '#button'],
            'fill'           => ['label' => 'Fill Input',        'hasSelector' => true,  'hasValue' => true,  'selectorPlaceholder' => '#input', 'valuePlaceholder' => 'Text eingeben'],
            'select'         => ['label' => 'Select Option',     'hasSelector' => true,  'hasValue' => true,  'selectorPlaceholder' => 'select#field', 'valuePlaceholder' => 'option-value'],
            'pressKey'       => ['label' => 'Press Key',         'hasSelector' => false, 'hasValue' => true,  'valuePlaceholder' => 'Enter'],
            'assertVisible'  => ['label' => 'Assert Visible',    'hasSelector' => true,  'hasValue' => false, 'selectorPlaceholder' => '.element'],
            'assertText'     => ['label' => 'Assert Text',       'hasSelector' => true,  'hasValue' => true,  'selectorPlaceholder' => 'h1', 'valuePlaceholder' => 'Erwarteter Text'],
            'assertUrl'      => ['label' => 'Assert URL',        'hasSelector' => false, 'hasValue' => true,  'valuePlaceholder' => '/erwartete-seite'],
            'assertTitle'    => ['label' => 'Assert Title',      'hasSelector' => false, 'hasValue' => true,  'valuePlaceholder' => 'Seitentitel'],
            'waitForSelector'=> ['label' => 'Wait for Selector', 'hasSelector' => true,  'hasValue' => false, 'selectorPlaceholder' => '.lazy-loaded'],
        ];
    }
}
