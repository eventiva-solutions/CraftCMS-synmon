<?php

namespace eventiva\synmon\services;

use Cron\CronExpression;
use eventiva\synmon\records\SuiteRecord;
use yii\base\Component;

class SchedulerService extends Component
{
    public function getDueSuites(): array
    {
        $suites = SuiteRecord::find()
            ->where(['enabled' => true])
            ->asArray()
            ->all();

        $due = [];
        foreach ($suites as $suite) {
            if ($this->isDue($suite['cronExpression'])) {
                $due[] = $suite;
            }
        }

        return $due;
    }

    public function isDue(string $cronExpr): bool
    {
        try {
            $cron = new CronExpression($cronExpr);
            return $cron->isDue();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getNextRunTime(string $cronExpr): ?\DateTime
    {
        try {
            $cron = new CronExpression($cronExpr);
            return $cron->getNextRunDate();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function isValidCronExpression(string $expr): bool
    {
        try {
            new CronExpression($expr);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
