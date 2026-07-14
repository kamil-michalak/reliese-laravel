<?php

namespace Reliese\Coders\Model\Relations;

use Illuminate\Support\Str;

/**
 * General utility functions for dealing with relationships
 */
class RelationHelper
{
    /**
     * Turns a column name like 'manager_id' into 'manager'; 'lineManagerId'
     * into 'lineManager'; or 'HostID' into 'Host'.
     *
     * An underscore-delimited suffix (e.g. "_id") is always a safe word
     * boundary and is stripped regardless of casing. Without an underscore,
     * the suffix is only stripped when it starts with an uppercase letter
     * (e.g. "HostID", "authorId"), since that is the only signal we have
     * that it is a real suffix and not just a word that happens to end in
     * "id" (e.g. "valid", "grid").
     *
     * @param bool $usesSnakeAttributes
     * @param string $primaryKey
     * @param string $foreignKey
     * @return string
     */
    public static function stripSuffixFromForeignKey($usesSnakeAttributes, $primaryKey, $foreignKey)
    {
        $studlyPrimaryKey = Str::studly($primaryKey);

        if ($usesSnakeAttributes) {
            $lowerPrimaryKey = strtolower($primaryKey);
            $stripped = preg_replace(
                '/_(' . preg_quote($primaryKey, '/') . '|' . preg_quote($lowerPrimaryKey, '/') . ')$/i',
                '',
                $foreignKey
            );

            if ($stripped !== $foreignKey) {
                return $stripped;
            }
        }

        return preg_replace('/(' . preg_quote($studlyPrimaryKey, '/') . ')$/', '', $foreignKey);
    }
}
