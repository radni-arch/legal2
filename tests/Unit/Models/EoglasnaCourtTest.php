<?php

namespace Tests\Unit\Models;

use App\Models\EoglasnaCourt;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class EoglasnaCourtTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_has_correct_table_name()
    {
        $court = new EoglasnaCourt;
        $this->assertEquals('eoglasna_courts', $court->getTable());
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $court = EoglasnaCourt::create([
            'code' => 'ZSZG',
            'name' => 'Županijski sud u Zagrebu',
            'court_type' => 'zupanijski',
        ]);

        $this->assertEquals('ZSZG', $court->code);
        $this->assertEquals('Županijski sud u Zagrebu', $court->name);
        $this->assertEquals('zupanijski', $court->court_type);
    }

    /** @test */
    public function it_stores_commercial_court_data()
    {
        $court = EoglasnaCourt::create([
            'code' => 'TSZG',
            'name' => 'Trgovački sud u Zagrebu',
            'court_type' => 'trgovacki',
        ]);

        $this->assertEquals('TSZG', $court->code);
        $this->assertEquals('Trgovački sud u Zagrebu', $court->name);
        $this->assertEquals('trgovacki', $court->court_type);
    }

    /** @test */
    public function it_stores_municipal_court_data()
    {
        $court = EoglasnaCourt::create([
            'code' => 'OSZG',
            'name' => 'Općinski sud u Zagrebu',
            'court_type' => 'opcinski',
        ]);

        $this->assertEquals('OSZG', $court->code);
        $this->assertEquals('Općinski sud u Zagrebu', $court->name);
        $this->assertEquals('opcinski', $court->court_type);
    }

    /** @test */
    public function it_stores_supreme_court_data()
    {
        $court = EoglasnaCourt::create([
            'code' => 'VSRH',
            'name' => 'Vrhovni sud Republike Hrvatske',
            'court_type' => 'vrhovni',
        ]);

        $this->assertEquals('VSRH', $court->code);
        $this->assertEquals('Vrhovni sud Republike Hrvatske', $court->name);
        $this->assertEquals('vrhovni', $court->court_type);
    }

    /** @test */
    public function it_can_find_court_by_code()
    {
        EoglasnaCourt::create([
            'code' => 'ZSOS',
            'name' => 'Županijski sud u Osijeku',
            'court_type' => 'zupanijski',
        ]);

        EoglasnaCourt::create([
            'code' => 'ZSST',
            'name' => 'Županijski sud u Splitu',
            'court_type' => 'zupanijski',
        ]);

        $court = EoglasnaCourt::where('code', 'ZSOS')->first();

        $this->assertNotNull($court);
        $this->assertEquals('Županijski sud u Osijeku', $court->name);
    }

    /** @test */
    public function it_can_filter_courts_by_type()
    {
        EoglasnaCourt::create(['code' => 'TSZG', 'name' => 'Trgovački sud Zagreb', 'court_type' => 'trgovacki']);
        EoglasnaCourt::create(['code' => 'TSST', 'name' => 'Trgovački sud Split', 'court_type' => 'trgovacki']);
        EoglasnaCourt::create(['code' => 'ZSZG', 'name' => 'Županijski sud Zagreb', 'court_type' => 'zupanijski']);

        $trgovacki = EoglasnaCourt::where('court_type', 'trgovacki')->get();

        $this->assertCount(2, $trgovacki);
        $this->assertEquals('trgovacki', $trgovacki->first()->court_type);
    }

    /** @test */
    public function it_handles_null_optional_fields()
    {
        $court = EoglasnaCourt::create([
            'code' => 'TEST',
            'name' => 'Test Court',
            'court_type' => null,
        ]);

        $this->assertNull($court->court_type);
    }

    /** @test */
    public function it_can_update_court_information()
    {
        $court = EoglasnaCourt::create([
            'code' => 'TEST',
            'name' => 'Old Name',
            'court_type' => 'opcinski',
        ]);

        $court->update([
            'name' => 'New Name',
            'court_type' => 'zupanijski',
        ]);

        $this->assertEquals('New Name', $court->fresh()->name);
        $this->assertEquals('zupanijski', $court->fresh()->court_type);
    }

    /** @test */
    public function it_stores_various_croatian_court_types()
    {
        $courts = [
            ['code' => 'VS', 'name' => 'Vrhovni sud', 'court_type' => 'vrhovni'],
            ['code' => 'VTS', 'name' => 'Visoki trgovački sud', 'court_type' => 'visoki_trgovacki'],
            ['code' => 'VPS', 'name' => 'Visoki prekršajni sud', 'court_type' => 'visoki_prekrsajni'],
            ['code' => 'USRH', 'name' => 'Ustavni sud RH', 'court_type' => 'ustavni'],
        ];

        foreach ($courts as $courtData) {
            EoglasnaCourt::create($courtData);
        }

        $this->assertEquals(4, EoglasnaCourt::count());
        $this->assertNotNull(EoglasnaCourt::where('court_type', 'ustavni')->first());
    }

    /** @test */
    public function court_code_can_be_used_as_unique_identifier()
    {
        EoglasnaCourt::create([
            'code' => 'UNIQUE123',
            'name' => 'Test Court',
            'court_type' => 'test',
        ]);

        $court = EoglasnaCourt::where('code', 'UNIQUE123')->sole();

        $this->assertEquals('Test Court', $court->name);
    }
}
