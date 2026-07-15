<?php

use PHPUnit\Framework\TestCase;
use Reliese\Coders\Model\Relations\RelationHelper;

class RelationHelperTest extends TestCase
{
    public function provideKeys()
    {
        // usesSnakeAttributes, primaryKey, foreignKey, expected
        return [
            // camelCase
            [false, 'id', 'lineManagerId', 'lineManager'],
            [false, 'ID', 'lineManagerID', 'lineManager'],
            // snake_case
            [true, 'id', 'line_manager_id', 'line_manager'],
            [true, 'ID', 'line_manager_id', 'line_manager'],
            // no suffix
            [false, 'id', 'lineManager', 'lineManager'],
            [true, 'id', 'line_manager', 'line_manager'],
            // columns that contain the letters of the primary key as part of their name
            [false, 'id', 'holiday', 'holiday'],
            [true, 'id', 'something_identifier_id', 'something_identifier'],
            // PascalCase columns without an underscore, even though the
            // model itself uses snake_case attributes (e.g. legacy schemas
            // with columns like "HostID", "GuestID").
            [true, 'ID', 'HostID', 'Host'],
            [true, 'ID', 'GuestID', 'Guest'],
            [true, 'id', 'authorId', 'author'],
            // words that merely happen to end in "id" must not be stripped,
            // since there is no underscore or capital letter marking a
            // suffix boundary.
            [true, 'id', 'valid', 'valid'],
            [true, 'id', 'grid', 'grid'],
            [true, 'id', 'android', 'android'],
            // The referencing column's suffix casing does not always match
            // the referenced primary key's own casing (e.g. primary key
            // "ID" referenced by a "...Id"-suffixed column) - the match
            // must still succeed as long as the suffix starts with an
            // uppercase letter.
            [true, 'ID', 'VirtualHostId', 'VirtualHost'],
            [true, 'ID', 'VirtualGuestId', 'VirtualGuest'],
        ];
    }

    /**
     * @dataProvider provideKeys
     *
     * @param bool $usesSnakeAttributes
     * @param string $primaryKey
     * @param string $foreignKey
     * @param string $expected
     */
    public function testNameUsingForeignKeyStrategy($usesSnakeAttributes, $primaryKey, $foreignKey, $expected)
    {
        $this->assertEquals(
            $expected,
            RelationHelper::stripSuffixFromForeignKey($usesSnakeAttributes, $primaryKey, $foreignKey),
            json_encode(compact('usesSnakeAttributes', 'primaryKey', 'foreignKey'))
        );
    }

    public function provideComments()
    {
        return [
            'valid JSON with a relation hint' => ['{"relation":"host"}', 'host'],
            'valid JSON with other keys plus a relation hint' => ['{"alias":"HostTeamId","relation":"host"}', 'host'],
            'valid JSON without a relation key' => ['{"alias":"HostTeamId"}', null],
            'valid JSON with an empty relation value' => ['{"relation":""}', null],
            'plain text comment, not JSON' => ['Host team of the match', null],
            'empty comment' => ['', null],
            'null comment' => [null, null],
            'JSON scalar, not an object' => ['"host"', null],
        ];
    }

    /**
     * @dataProvider provideComments
     *
     * @param string|null $comment
     * @param string|null $expected
     */
    public function testRelationNameFromComment($comment, $expected)
    {
        $this->assertSame($expected, RelationHelper::relationNameFromComment($comment));
    }

    /**
     * A column's `{"relation": "..."}` comment hint should be used verbatim
     * instead of the stripped-suffix heuristic, since it exists precisely
     * for the columns the heuristic can't name well on its own (e.g.
     * abbreviated names like `HostID`/`GuestID`).
     */
    public function testNameFromForeignKeyColumnsUsesCommentOverrideWhenPresent()
    {
        $this->assertSame(
            'host',
            RelationHelper::nameFromForeignKeyColumns(
                true,
                ['HostID'],
                ['ID'],
                ['HostID' => '{"relation":"host"}']
            )
        );
    }

    /**
     * A comment override on one column of a composite foreign key should
     * only affect that column's part of the name; the other column still
     * falls back to the stripped-suffix heuristic.
     */
    public function testNameFromForeignKeyColumnsMixesCommentOverrideWithHeuristicForCompositeKeys()
    {
        $this->assertSame(
            'Team_host',
            RelationHelper::nameFromForeignKeyColumns(
                true,
                ['TeamId', 'HostID'],
                ['id', 'ID'],
                ['HostID' => '{"relation":"host"}']
            )
        );
    }

    public function testNameFromForeignKeyColumnsFallsBackToHeuristicWithoutAComment()
    {
        $this->assertSame(
            'Host',
            RelationHelper::nameFromForeignKeyColumns(
                true,
                ['HostID'],
                ['ID'],
                ['HostID' => null]
            )
        );
    }
}
