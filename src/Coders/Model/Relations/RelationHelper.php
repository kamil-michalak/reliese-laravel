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
     * (e.g. "HostID", "authorId", "VirtualHostId"), since that is the only
     * signal we have that it is a real suffix and not just a word that
     * happens to end in "id" (e.g. "valid", "grid"). The rest of the
     * suffix is matched case-insensitively, since referencing tables don't
     * always spell the primary key's name the same way the foreign key
     * column does (e.g. primary key "ID" referenced by a "...Id" column).
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

        $suffixLength = strlen($studlyPrimaryKey);
        $candidateSuffix = substr($foreignKey, -$suffixLength);

        if (ctype_upper(substr($candidateSuffix, 0, 1)) && strcasecmp($candidateSuffix, $studlyPrimaryKey) === 0) {
            return substr($foreignKey, 0, -$suffixLength);
        }

        return $foreignKey;
    }
}
