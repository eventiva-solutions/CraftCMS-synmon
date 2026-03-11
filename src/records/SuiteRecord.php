<?php

namespace eventiva\synmon\records;

use craft\db\ActiveRecord;

/**
 * @property int    $id
 * @property string $uid
 * @property string $name
 * @property string $description
 * @property string $cronExpression
 * @property bool   $enabled
 * @property string $notifyEmail
 * @property string $notifyWebhookUrl
 * @property bool   $notifyOnSuccess
 * @property string $lastRunAt
 * @property string $lastRunStatus
 * @property string $dateCreated
 * @property string $dateUpdated
 */
class SuiteRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%synmon_suites}}';
    }
}
