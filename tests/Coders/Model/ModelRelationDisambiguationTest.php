<?php

use Illuminate\Support\Fluent;
use Reliese\Coders\Model\Factory;
use Reliese\Coders\Model\Model;
use Reliese\Meta\Blueprint;
use Reliese\Meta\Schema;
use Reliese\Meta\SchemaManager;

class ModelRelationDisambiguationTest extends TestCase
{
    /**
     * Two foreign keys on the same table pointing at the same related table
     * (e.g. `manager_id` and `mentor_id`, both referencing `employees.id`)
     * used to collide on the same relation name and silently overwrite each
     * other. The first one found should keep its default name, and the
     * second one should fall back to a name based on its own foreign key.
     */
    public function testCollidingBelongsToRelationsAreBothGenerated()
    {
        $managerRelation = new Fluent([
            'columns' => ['manager_id'],
            'references' => ['id'],
            'on' => ['test', 'employees'],
        ]);

        $mentorRelation = new Fluent([
            'columns' => ['mentor_id'],
            'references' => ['id'],
            'on' => ['test', 'employees'],
        ]);

        $blueprint = Mockery::mock(Blueprint::class);
        $blueprint->shouldReceive('columns')->andReturn([]);
        $blueprint->shouldReceive('schema')->andReturn('test');
        $blueprint->shouldReceive('qualifiedTable')->andReturn('test.employees');
        $blueprint->shouldReceive('connection')->andReturn('test');
        $blueprint->shouldReceive('primaryKey')->andReturn(new Fluent(['columns' => ['id']]));
        $blueprint->shouldReceive('relations')->andReturn([$managerRelation, $mentorRelation]);
        $blueprint->shouldReceive('table')->andReturn('employees');
        $blueprint->shouldReceive('is')->andReturn(true);
        $blueprint->shouldReceive('column')->andReturn(new Fluent(['nullable' => true]));

        $model = new Model(
            $blueprint,
            new Factory(
                Mockery::mock(\Illuminate\Database\DatabaseManager::class),
                Mockery::mock(\Illuminate\Filesystem\Filesystem::class),
                Mockery::mock(\Reliese\Support\Classify::class),
                new \Reliese\Coders\Model\Config()
            )
        );

        $relations = $model->getRelations();

        $this->assertCount(2, $relations, 'Both relations should be generated instead of one overwriting the other.');
        $this->assertArrayHasKey('employee', $relations, 'The first relation should keep its default (related) name.');
        $this->assertArrayHasKey('employee_mentor', $relations, 'The colliding relation should be disambiguated using the related name plus its own foreign key.');

        $this->assertSame('manager_id', $this->readForeignKey($relations['employee']));
        $this->assertSame('mentor_id', $this->readForeignKey($relations['employee_mentor']));
    }

    /**
     * Real-world case: `MeczTbl` has two composite-key BelongsTo relations
     * to `ZespolTbl_LigaTbl`, both matching on the same shared `_LigaId`
     * column, differing only by the second column (`GuestID`/`HostID`).
     * Disambiguating using only the first composite column produced a
     * degenerate suffix (stripping "_LigaId" against itself leaves just an
     * underscore), yielding a broken double-underscore name like
     * "zespol_tbl_liga_tbl__". All composite columns must be considered so
     * the actually-distinguishing column produces a clean name.
     */
    public function testCollidingCompositeKeyBelongsToRelationsAreDisambiguated()
    {
        $guestRelation = new Fluent([
            'columns' => ['_LigaId', 'GuestID'],
            'references' => ['_LigaId', '_ZespolId'],
            'on' => ['test', 'zespol_tbl_liga_tbl'],
        ]);

        $hostRelation = new Fluent([
            'columns' => ['_LigaId', 'HostID'],
            'references' => ['_LigaId', '_ZespolId'],
            'on' => ['test', 'zespol_tbl_liga_tbl'],
        ]);

        $blueprint = Mockery::mock(Blueprint::class);
        $blueprint->shouldReceive('columns')->andReturn([]);
        $blueprint->shouldReceive('schema')->andReturn('test');
        $blueprint->shouldReceive('qualifiedTable')->andReturn('test.zespol_tbl_liga_tbl');
        $blueprint->shouldReceive('connection')->andReturn('test');
        $blueprint->shouldReceive('primaryKey')->andReturn(new Fluent(['columns' => ['_LigaId', '_ZespolId']]));
        $blueprint->shouldReceive('relations')->andReturn([$guestRelation, $hostRelation]);
        $blueprint->shouldReceive('table')->andReturn('zespol_tbl_liga_tbl');
        $blueprint->shouldReceive('is')->andReturn(true);
        $blueprint->shouldReceive('column')->andReturn(new Fluent(['nullable' => true]));

        $model = new Model(
            $blueprint,
            new Factory(
                Mockery::mock(\Illuminate\Database\DatabaseManager::class),
                Mockery::mock(\Illuminate\Filesystem\Filesystem::class),
                Mockery::mock(\Reliese\Support\Classify::class),
                new \Reliese\Coders\Model\Config()
            )
        );

        $relations = $model->getRelations();

        $this->assertCount(2, $relations, 'Both relations should be generated instead of one overwriting the other.');
        $this->assertArrayHasKey('zespol_tbl_liga_tbl', $relations, 'The first relation should keep its default (related) name.');
        $this->assertArrayHasKey('zespol_tbl_liga_tbl_host', $relations, 'The colliding relation should be disambiguated using the distinguishing composite column, not a degenerate underscore.');

        $this->assertSame('GuestID', $this->readForeignKey($relations['zespol_tbl_liga_tbl'], 1));
        $this->assertSame('HostID', $this->readForeignKey($relations['zespol_tbl_liga_tbl_host'], 1));
    }

    /**
     * Real-world case: a legacy schema with PascalCase columns without
     * underscores (e.g. `GuestID` and `HostID`, both referencing
     * `zespol_tbl.ID`), while the model itself uses snake_case attributes.
     * Both relations must be generated with distinct, readable names
     * instead of colliding on `zespol_tbl` or falling back to a numeric
     * suffix like `zespol_tbl2`.
     */
    public function testCollidingBelongsToRelationsWithPascalCaseColumnsAreDisambiguated()
    {
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

        $blueprint = Mockery::mock(Blueprint::class);
        $blueprint->shouldReceive('columns')->andReturn([]);
        $blueprint->shouldReceive('schema')->andReturn('test');
        $blueprint->shouldReceive('qualifiedTable')->andReturn('test.zespol_tbl');
        $blueprint->shouldReceive('connection')->andReturn('test');
        $blueprint->shouldReceive('primaryKey')->andReturn(new Fluent(['columns' => ['ID']]));
        $blueprint->shouldReceive('relations')->andReturn([$guestRelation, $hostRelation]);
        $blueprint->shouldReceive('table')->andReturn('zespol_tbl');
        $blueprint->shouldReceive('is')->andReturn(true);
        $blueprint->shouldReceive('column')->andReturn(new Fluent(['nullable' => true]));

        $model = new Model(
            $blueprint,
            new Factory(
                Mockery::mock(\Illuminate\Database\DatabaseManager::class),
                Mockery::mock(\Illuminate\Filesystem\Filesystem::class),
                Mockery::mock(\Reliese\Support\Classify::class),
                new \Reliese\Coders\Model\Config()
            )
        );

        $relations = $model->getRelations();

        $this->assertCount(2, $relations, 'Both relations should be generated instead of one overwriting the other.');
        $this->assertArrayHasKey('zespol_tbl', $relations, 'The first relation should keep its default (related) name.');
        $this->assertArrayHasKey('zespol_tbl_host', $relations, 'The colliding relation should be disambiguated using the related name plus its own foreign key, not a numeric suffix.');

        $this->assertSame('GuestID', $this->readForeignKey($relations['zespol_tbl']));
        $this->assertSame('HostID', $this->readForeignKey($relations['zespol_tbl_host']));
    }

    /**
     * Real-world case: `VirtualGuestId`/`VirtualHostId` on
     * `MatchTbl_TeamVirtualTbl`, both referencing `team_virtual_tbl.ID`.
     * The suffix casing ("Id") does not match the referenced primary key's
     * own casing ("ID"), which previously prevented the suffix from being
     * stripped at all, producing a needlessly verbose
     * "team_virtual_tbl_virtual_host_id" instead of
     * "team_virtual_tbl_virtual_host".
     */
    public function testCollidingBelongsToRelationsWithMismatchedSuffixCasingAreDisambiguated()
    {
        $guestRelation = new Fluent([
            'columns' => ['VirtualGuestId'],
            'references' => ['ID'],
            'on' => ['test', 'team_virtual_tbl'],
        ]);

        $hostRelation = new Fluent([
            'columns' => ['VirtualHostId'],
            'references' => ['ID'],
            'on' => ['test', 'team_virtual_tbl'],
        ]);

        $blueprint = Mockery::mock(Blueprint::class);
        $blueprint->shouldReceive('columns')->andReturn([]);
        $blueprint->shouldReceive('schema')->andReturn('test');
        $blueprint->shouldReceive('qualifiedTable')->andReturn('test.team_virtual_tbl');
        $blueprint->shouldReceive('connection')->andReturn('test');
        $blueprint->shouldReceive('primaryKey')->andReturn(new Fluent(['columns' => ['ID']]));
        $blueprint->shouldReceive('relations')->andReturn([$guestRelation, $hostRelation]);
        $blueprint->shouldReceive('table')->andReturn('team_virtual_tbl');
        $blueprint->shouldReceive('is')->andReturn(true);
        $blueprint->shouldReceive('column')->andReturn(new Fluent(['nullable' => true]));

        $model = new Model(
            $blueprint,
            new Factory(
                Mockery::mock(\Illuminate\Database\DatabaseManager::class),
                Mockery::mock(\Illuminate\Filesystem\Filesystem::class),
                Mockery::mock(\Reliese\Support\Classify::class),
                new \Reliese\Coders\Model\Config()
            )
        );

        $relations = $model->getRelations();

        $this->assertCount(2, $relations, 'Both relations should be generated instead of one overwriting the other.');
        $this->assertArrayHasKey('team_virtual_tbl', $relations, 'The first relation should keep its default (related) name.');
        $this->assertArrayHasKey('team_virtual_tbl_virtual_host', $relations, 'The colliding relation should be disambiguated with the suffix cleanly stripped, despite the casing mismatch.');

        $this->assertSame('VirtualGuestId', $this->readForeignKey($relations['team_virtual_tbl']));
        $this->assertSame('VirtualHostId', $this->readForeignKey($relations['team_virtual_tbl_virtual_host']));
    }

    /**
     * Model::getRelations() disambiguates colliding relation names, but
     * Factory::body() used to discard that and call $constraint->name()
     * again when generating each method declaration - which always
     * recomputes the original (colliding) name, regardless of the
     * disambiguated key the relation was actually stored under. That meant
     * the generated PHP still declared the same method name twice, even
     * though Model::getRelations() itself was already correct.
     */
    public function testGeneratedMethodBodiesUseDisambiguatedNames()
    {
        $managerRelation = new Fluent([
            'columns' => ['manager_id'],
            'references' => ['id'],
            'on' => ['test', 'employees'],
        ]);

        $mentorRelation = new Fluent([
            'columns' => ['mentor_id'],
            'references' => ['id'],
            'on' => ['test', 'employees'],
        ]);

        $blueprint = Mockery::mock(Blueprint::class);
        $blueprint->shouldReceive('columns')->andReturn([]);
        $blueprint->shouldReceive('schema')->andReturn('test');
        $blueprint->shouldReceive('qualifiedTable')->andReturn('test.employees');
        $blueprint->shouldReceive('connection')->andReturn('test');
        $blueprint->shouldReceive('primaryKey')->andReturn(new Fluent(['columns' => ['id']]));
        $blueprint->shouldReceive('relations')->andReturn([$managerRelation, $mentorRelation]);
        $blueprint->shouldReceive('table')->andReturn('employees');
        $blueprint->shouldReceive('is')->andReturn(true);
        $blueprint->shouldReceive('column')->andReturn(new Fluent(['nullable' => true]));
        $blueprint->shouldReceive('hasColumn')->andReturn(false);

        $factory = new Factory(
            Mockery::mock(\Illuminate\Database\DatabaseManager::class),
            Mockery::mock(\Illuminate\Filesystem\Filesystem::class),
            new \Reliese\Support\Classify(),
            new \Reliese\Coders\Model\Config()
        );

        $model = new Model($blueprint, $factory);

        $bodyMethod = new \ReflectionMethod(Factory::class, 'body');
        $bodyMethod->setAccessible(true);
        $body = $bodyMethod->invoke($factory, $model);

        $this->assertStringContainsString('function employee()', $body);
        $this->assertStringContainsString('function employee_mentor()', $body);
        $this->assertSame(1, substr_count($body, 'function employee()'), 'The default name should only be declared once.');
    }

    /**
     * The same collision also happens on the reverse (HasMany) side: e.g.
     * `sportmonks_fixture_events.sub_type_id` and
     * `sportmonks_fixture_events.type_id` both reference `sportmonks_type`,
     * so SportmonksType generated two `sportmonks_fixture_events()` methods.
     * The first should keep its default name, and the second should be
     * disambiguated using the existing "Where<Column>" convention already
     * used by HasMany's foreign_key strategy.
     */
    public function testCollidingHasManyRelationsAreDisambiguated()
    {
        $parentBlueprint = Mockery::mock(Blueprint::class);
        $parentBlueprint->shouldReceive('columns')->andReturn([]);
        $parentBlueprint->shouldReceive('schema')->andReturn('test');
        $parentBlueprint->shouldReceive('qualifiedTable')->andReturn('test.sportmonks_type');
        $parentBlueprint->shouldReceive('connection')->andReturn('test');
        $parentBlueprint->shouldReceive('primaryKey')->andReturn(new Fluent(['columns' => ['id']]));
        $parentBlueprint->shouldReceive('relations')->andReturn([]);
        $parentBlueprint->shouldReceive('table')->andReturn('sportmonks_type');
        $parentBlueprint->shouldReceive('is')->andReturnUsing(function ($schema, $table) {
            return $schema === 'test' && $table === 'sportmonks_type';
        });
        $parentBlueprint->shouldReceive('column')->andReturn(new Fluent(['nullable' => true]));

        $childBlueprint = Mockery::mock(Blueprint::class);
        $childBlueprint->shouldReceive('columns')->andReturn([]);
        $childBlueprint->shouldReceive('schema')->andReturn('test');
        $childBlueprint->shouldReceive('qualifiedTable')->andReturn('test.sportmonks_fixture_events');
        $childBlueprint->shouldReceive('connection')->andReturn('test');
        $childBlueprint->shouldReceive('primaryKey')->andReturn(new Fluent(['columns' => ['id']]));
        $childBlueprint->shouldReceive('table')->andReturn('sportmonks_fixture_events');
        $childBlueprint->shouldReceive('isUniqueKey')->andReturn(false);

        $subTypeReference = new Fluent([
            'columns' => ['sub_type_id'],
            'references' => ['id'],
            'on' => ['test', 'sportmonks_type'],
        ]);

        $typeReference = new Fluent([
            'columns' => ['type_id'],
            'references' => ['id'],
            'on' => ['test', 'sportmonks_type'],
        ]);

        $schema = Mockery::mock(Schema::class);
        $schema->shouldReceive('referencing')->andReturn([
            ['blueprint' => $childBlueprint, 'reference' => $subTypeReference],
            ['blueprint' => $childBlueprint, 'reference' => $typeReference],
        ]);
        $schema->shouldReceive('table')->with('sportmonks_fixture_events')->andReturn($childBlueprint);

        $schemaManager = Mockery::mock(SchemaManager::class);
        $schemaManager->shouldReceive('getIterator')->andReturn(new ArrayIterator([$schema]));
        $schemaManager->shouldReceive('make')->with('test')->andReturn($schema);

        $factory = new Factory(
            Mockery::mock(\Illuminate\Database\DatabaseManager::class),
            Mockery::mock(\Illuminate\Filesystem\Filesystem::class),
            Mockery::mock(\Reliese\Support\Classify::class),
            new \Reliese\Coders\Model\Config()
        );

        $schemasProperty = new \ReflectionProperty(Factory::class, 'schemas');
        $schemasProperty->setAccessible(true);
        $schemasProperty->setValue($factory, $schemaManager);

        $model = new Model($parentBlueprint, $factory);

        $relations = $model->getRelations();

        $this->assertCount(2, $relations, 'Both HasMany relations should be generated instead of one overwriting the other.');
        $this->assertArrayHasKey('sportmonks_fixture_events', $relations, 'The first relation should keep its default (related) name.');
        $this->assertArrayHasKey('sportmonks_fixture_events_where_type', $relations, 'The colliding relation should be disambiguated using its own foreign key.');
    }

    /**
     * Real-world case: `ZespolTbl_LigaTbl` has two composite-key HasMany
     * relations back to `MeczTbl`, mirroring the composite BelongsTo case
     * above from the other side. HasOneOrMany previously only ever looked
     * at the first composite column (the shared "_LigaId"), which is not
     * just a naming problem: it also meant the generated body() never
     * emitted a `->where()` clause for the second column, so both
     * relations queried on "_LigaId" alone and would return the exact same
     * (wrong) rows regardless of guest/host.
     */
    public function testCollidingCompositeKeyHasManyRelationsAreDisambiguatedWithCorrectWhereClauses()
    {
        $parentBlueprint = Mockery::mock(Blueprint::class);
        $parentBlueprint->shouldReceive('columns')->andReturn([]);
        $parentBlueprint->shouldReceive('schema')->andReturn('test');
        $parentBlueprint->shouldReceive('qualifiedTable')->andReturn('test.zespol_tbl_liga_tbl');
        $parentBlueprint->shouldReceive('connection')->andReturn('test');
        $parentBlueprint->shouldReceive('primaryKey')->andReturn(new Fluent(['columns' => ['_LigaId', '_ZespolId']]));
        $parentBlueprint->shouldReceive('relations')->andReturn([]);
        $parentBlueprint->shouldReceive('table')->andReturn('zespol_tbl_liga_tbl');
        $parentBlueprint->shouldReceive('is')->andReturnUsing(function ($schema, $table) {
            return $schema === 'test' && $table === 'zespol_tbl_liga_tbl';
        });
        $parentBlueprint->shouldReceive('column')->andReturn(new Fluent(['nullable' => true]));
        $parentBlueprint->shouldReceive('hasColumn')->andReturn(false);

        $childBlueprint = Mockery::mock(Blueprint::class);
        $childBlueprint->shouldReceive('columns')->andReturn([]);
        $childBlueprint->shouldReceive('schema')->andReturn('test');
        $childBlueprint->shouldReceive('qualifiedTable')->andReturn('test.mecz_tbl');
        $childBlueprint->shouldReceive('connection')->andReturn('test');
        $childBlueprint->shouldReceive('primaryKey')->andReturn(new Fluent(['columns' => ['_MatchId']]));
        $childBlueprint->shouldReceive('table')->andReturn('mecz_tbl');
        $childBlueprint->shouldReceive('isUniqueKey')->andReturn(false);

        $guestReference = new Fluent([
            'columns' => ['_LigaId', 'GuestID'],
            'references' => ['_LigaId', '_ZespolId'],
            'on' => ['test', 'zespol_tbl_liga_tbl'],
        ]);

        $hostReference = new Fluent([
            'columns' => ['_LigaId', 'HostID'],
            'references' => ['_LigaId', '_ZespolId'],
            'on' => ['test', 'zespol_tbl_liga_tbl'],
        ]);

        $schema = Mockery::mock(Schema::class);
        $schema->shouldReceive('referencing')->andReturn([
            ['blueprint' => $childBlueprint, 'reference' => $guestReference],
            ['blueprint' => $childBlueprint, 'reference' => $hostReference],
        ]);
        $schema->shouldReceive('table')->with('mecz_tbl')->andReturn($childBlueprint);

        $schemaManager = Mockery::mock(SchemaManager::class);
        $schemaManager->shouldReceive('getIterator')->andReturn(new ArrayIterator([$schema]));
        $schemaManager->shouldReceive('make')->with('test')->andReturn($schema);

        $factory = new Factory(
            Mockery::mock(\Illuminate\Database\DatabaseManager::class),
            Mockery::mock(\Illuminate\Filesystem\Filesystem::class),
            new \Reliese\Support\Classify(),
            new \Reliese\Coders\Model\Config()
        );

        $schemasProperty = new \ReflectionProperty(Factory::class, 'schemas');
        $schemasProperty->setAccessible(true);
        $schemasProperty->setValue($factory, $schemaManager);

        $model = new Model($parentBlueprint, $factory);

        $relations = $model->getRelations();

        $this->assertCount(2, $relations, 'Both HasMany relations should be generated instead of one overwriting the other.');
        $this->assertArrayHasKey('mecz_tbls', $relations, 'The first relation should keep its default (related) name.');
        $this->assertArrayHasKey('mecz_tbls_where_host', $relations, 'The colliding relation should be disambiguated using the distinguishing composite column.');

        $bodyMethod = new \ReflectionMethod(Factory::class, 'body');
        $bodyMethod->setAccessible(true);
        $body = $bodyMethod->invoke($factory, $model);

        $this->assertStringContainsString(
            "->where('zespol_tbl_liga_tbl._ZespolId', '=', 'mecz_tbl.GuestID')",
            $body,
            'The composite where clause for the guest side must actually filter by GuestID, not just _LigaId.'
        );
        $this->assertStringContainsString(
            "->where('zespol_tbl_liga_tbl._ZespolId', '=', 'mecz_tbl.HostID')",
            $body,
            'The composite where clause for the host side must actually filter by HostID, not just _LigaId.'
        );
    }

    /**
     * @param \Reliese\Coders\Model\Relation $relation
     * @param int $index
     *
     * @return string
     */
    private function readForeignKey($relation, $index = 0)
    {
        $property = new \ReflectionProperty($relation, 'command');
        $property->setAccessible(true);

        return $property->getValue($relation)->columns[$index];
    }
}
