<?php

namespace eventiva\synmon\services;

use Craft;
use yii\base\Component;

class ResultService extends Component
{
    private const SETTINGS_KEY = 'synmon_settings';

    public function getSettings(): array
    {
        $cached = Craft::$app->getCache()->get(self::SETTINGS_KEY);
        if ($cached !== false) {
            return $cached;
        }
        return $this->getDefaultSettings();
    }

    public function saveSettings(array $settings): void
    {
        $current  = $this->getSettings();
        $merged   = array_merge($current, $settings);
        Craft::$app->getCache()->set(self::SETTINGS_KEY, $merged, 0);
    }

    public function getDefaultSettings(): array
    {
        return [
            'nodeBinary'       => 'node',
            'defaultTimeout'   => 30000,
            'globalTimeout'    => 120,
            'runRetentionDays' => 30,
            'enabled'          => true,
        ];
    }

    public function getRuns(int $page = 1, int $perPage = 20, ?int $suiteId = null, ?string $status = null): array
    {
        $query = Craft::$app->getDb()->createCommand('
            SELECT r.*, s.name as suiteName
            FROM {{%synmon_runs}} r
            LEFT JOIN {{%synmon_suites}} s ON r.suiteId = s.id
            WHERE 1=1
            ' . ($suiteId ? ' AND r.suiteId = ' . (int)$suiteId : '') . '
            ' . ($status ? " AND r.status = '" . addslashes($status) . "'" : '') . '
            ORDER BY r.dateCreated DESC
            LIMIT ' . (int)$perPage . ' OFFSET ' . (int)(($page - 1) * $perPage)
        );

        $runs = $query->queryAll();

        $countQuery = Craft::$app->getDb()->createCommand('
            SELECT COUNT(*) FROM {{%synmon_runs}} r WHERE 1=1
            ' . ($suiteId ? ' AND r.suiteId = ' . (int)$suiteId : '') . '
            ' . ($status ? " AND r.status = '" . addslashes($status) . "'" : '')
        );
        $total = (int)$countQuery->queryScalar();

        return [
            'runs'       => $runs,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    public function getRunById(int $id): ?array
    {
        $run = Craft::$app->getDb()->createCommand('
            SELECT r.*, s.name as suiteName
            FROM {{%synmon_runs}} r
            LEFT JOIN {{%synmon_suites}} s ON r.suiteId = s.id
            WHERE r.id = :id
        ', [':id' => $id])->queryOne();

        if (!$run) {
            return null;
        }

        $run['stepLogs'] = Craft::$app->getDb()->createCommand('
            SELECT * FROM {{%synmon_step_logs}}
            WHERE runId = :runId
            ORDER BY sortOrder ASC
        ', [':runId' => $id])->queryAll();

        return $run;
    }

    public function getDashboardStats(): array
    {
        $db = Craft::$app->getDb();

        $totalSuites  = (int)$db->createCommand('SELECT COUNT(*) FROM {{%synmon_suites}}')->queryScalar();
        $enabledSuites= (int)$db->createCommand('SELECT COUNT(*) FROM {{%synmon_suites}} WHERE enabled = 1')->queryScalar();
        $totalRuns    = (int)$db->createCommand('SELECT COUNT(*) FROM {{%synmon_runs}}')->queryScalar();
        $passRuns     = (int)$db->createCommand("SELECT COUNT(*) FROM {{%synmon_runs}} WHERE status = 'pass'")->queryScalar();
        $failRuns     = (int)$db->createCommand("SELECT COUNT(*) FROM {{%synmon_runs}} WHERE status IN ('fail','error')")->queryScalar();

        $recentRuns = $db->createCommand('
            SELECT r.*, s.name as suiteName
            FROM {{%synmon_runs}} r
            LEFT JOIN {{%synmon_suites}} s ON r.suiteId = s.id
            ORDER BY r.dateCreated DESC
            LIMIT 10
        ')->queryAll();

        $suiteStatuses = $db->createCommand('
            SELECT id, name, lastRunStatus, lastRunAt, enabled, cronExpression
            FROM {{%synmon_suites}}
            ORDER BY name ASC
        ')->queryAll();

        return [
            'totalSuites'   => $totalSuites,
            'enabledSuites' => $enabledSuites,
            'totalRuns'     => $totalRuns,
            'passRuns'      => $passRuns,
            'failRuns'      => $failRuns,
            'passRate'      => $totalRuns > 0 ? round(($passRuns / $totalRuns) * 100) : 0,
            'recentRuns'    => $recentRuns,
            'suiteStatuses' => $suiteStatuses,
        ];
    }

    public function deleteOldRuns(int $keepDays): int
    {
        $cutoff = (new \DateTime())->modify("-{$keepDays} days")->format('Y-m-d H:i:s');
        return Craft::$app->getDb()->createCommand()->delete(
            '{{%synmon_runs}}',
            'dateCreated < :cutoff',
            [':cutoff' => $cutoff]
        )->execute();
    }
}
