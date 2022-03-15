<?php

namespace craftsnippets\elementfilters\records;

use Craft;
use craft\db\ActiveRecord;

use craftsnippets\elementfilters\helpers\DbTables;

class FilterRecord extends ActiveRecord
{

    public static function tableName()
    {
        return DbTables::FILTERS;
    }
}
