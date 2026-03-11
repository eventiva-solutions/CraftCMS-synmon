<?php

namespace eventiva\synmon\models;

use craft\base\Model;

class Settings extends Model
{
    public string $nodeBinary       = 'node';
    public int    $defaultTimeout   = 30000;
    public int    $globalTimeout    = 120;
    public int    $runRetentionDays = 30;
    public bool   $enabled          = true;
}
