<?php

namespace eventiva\synmon\records;

use craft\db\ActiveRecord;

/**
 * @property int    $id
 * @property string $uid
 * @property int    $suiteId
 * @property string $status
 * @property string $trigger
 * @property int    $durationMs
 * @property int    $failedStep
 * @property string $errorMessage
 * @property string $nodeVersion
 * @property string $playwrightVersion
 * @property string $dateCreated
 * @property string $dateUpdated
 */
class RunRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%synmon_runs}}';
    }
}
