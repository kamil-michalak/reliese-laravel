<?php

use Illuminate\Support\Fluent;
use Reliese\Meta\Blueprint;

/**
 * Created by Cristian.
 * Date: 16/10/16 01:32 PM.
 */
class BlueprintTest extends TestCase
{
    public function test_it_can_be_instantiated()
    {
        $blueprint = new Blueprint('connection', 'schema', 'table');

        $this->assertEquals('connection', $blueprint->connection());
        $this->assertEquals('schema', $blueprint->schema());
        $this->assertEquals('table', $blueprint->table());
    }

    /**
     * Real-world case: `mecz_tbl` has `HostID` as the physically first
     * column, followed by `GuestID`, both referencing `zespol_tbl.ID`. MySQL
     * lists FK constraints in `SHOW CREATE TABLE` in declaration order,
     * which happened to declare the GuestID constraint first - so
     * `references()` used to hand back [GuestID, HostID], and whichever
     * relation came first won the un-suffixed default name regardless of
     * where its column actually sits in the table. `references()` is
     * consumed directly by the reverse (HasMany) side of the generator, so
     * this ordering is what decides which of two colliding `hasMany()`
     * methods keeps its default name.
     */
    public function testReferencesAreOrderedByOwnColumnPositionNotConstraintDeclarationOrder()
    {
        $meczTbl = new Blueprint('test', 'test', 'mecz_tbl');
        $meczTbl->withColumn(new Fluent(['name' => 'HostID']));
        $meczTbl->withColumn(new Fluent(['name' => 'GuestID']));

        $guestRelation = new Fluent([
            'columns' => ['GuestID'],
            'references' => ['ID'],
            'on' => ['test', 'zespol_tbl'],
        ]);

        $hostRelation = new Fluent([
            'columns' => ['HostID'],
            'references' => ['ID'],
            'on' => ['test', 'zespol_tbl'],
        ]);

        // The FK constraint for GuestID is declared first, even though
        // HostID is the physically first column (added above).
        $meczTbl->withRelation($guestRelation);
        $meczTbl->withRelation($hostRelation);

        $zespolTbl = new Blueprint('test', 'test', 'zespol_tbl');
        $zespolTbl->withColumn(new Fluent(['name' => 'ID']));

        $references = $meczTbl->references($zespolTbl);

        $this->assertSame(
            [$hostRelation, $guestRelation],
            $references,
            'References should be ordered by their own column position in mecz_tbl (HostID before GuestID), not by constraint declaration order.'
        );
    }
}
