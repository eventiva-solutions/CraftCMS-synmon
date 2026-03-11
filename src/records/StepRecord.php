<?php

namespace eventiva\synmon\records;

use craft\db\ActiveRecord;

/**
 * @property int    $id
 * @property string $uid
 * @property int    $suiteId
 * @property int    $sortOrder
 * @property string $type
 * @property string $selector
 * @property string $value
 * @property string $description
 * @property int    $timeout
 * @property string $dateCreated
 * @property string $dateUpdated
 */
class StepRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%synmon_steps}}';
    }
}
