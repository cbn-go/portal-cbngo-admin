<?php

namespace Tests\Unit\Models;

use App\Models\Church;
use App\Models\Minister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MinisterModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_minister_has_fillable_attributes_and_casts(): void
    {
        $minister = Minister::create([
            'registration_number' => '1234',
            'function' => 'Pastor',
            'name' => 'Pastor João da Silva',
            'status' => 'ATIVO(A)',
            'sectional' => 'ORMIBAN GO',
            'church_name' => 'Igreja Batista Central',
            'address' => 'Rua 1, Qd 2, Lt 3',
            'zip_code' => '74000-000',
            'phone' => '(62) 3200-1111',
            'email' => 'joao@example.com',
            'cpf' => '12345678901',
            'birth_date' => '1980-05-15',
            'nationality' => 'Brasileira',
            'place_of_birth' => 'Goiânia GO',
            'education_level' => 'Superior Completo',
            'marital_status' => 'Casado(a)',
            'gender' => 'Masculino',
            'profession' => 'Pastor',
            'identity_document' => '123456',
            'identity_issuer' => 'SSP/GO',
            'passport' => null,
            'identity_issued_at' => '1998-02-10',
            'other_phones' => [['tipo' => 'CELULAR', 'ddd' => '62', 'numero' => '99999-0000']],
            'occupational_roles' => [['funcao_igreja' => 'Presidente', 'data_inicial' => '2015-01-01', 'data_final' => null]],
            'registered_at' => '2005-01-10 10:00:00',
            'source_updated_at' => '2023-08-15 14:30:00',
        ]);

        $this->assertSame('1234', $minister->registration_number);
        $this->assertSame('12345678901', $minister->cpf);
        $this->assertInstanceOf(Carbon::class, $minister->birth_date);
        $this->assertSame('1980-05-15', $minister->birth_date->format('Y-m-d'));
        $this->assertInstanceOf(Carbon::class, $minister->identity_issued_at);
        $this->assertInstanceOf(Carbon::class, $minister->registered_at);
        $this->assertInstanceOf(Carbon::class, $minister->source_updated_at);
        $this->assertIsArray($minister->other_phones);
        $this->assertIsArray($minister->occupational_roles);
    }

    public function test_minister_relationships_with_church_and_user(): void
    {
        $church = Church::factory()->create(['name' => 'Igreja Batista Central']);
        $user = User::factory()->create();

        $minister = Minister::create([
            'registration_number' => '5678',
            'function' => 'Pastor',
            'name' => 'Pastor Pedro',
            'status' => 'ATIVO(A)',
            'sectional' => 'ORMIBAN GO',
            'church_name' => 'Igreja Batista Central',
            'cpf' => '98765432100',
            'church_id' => $church->id,
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(Church::class, $minister->church);
        $this->assertSame($church->id, $minister->church->id);

        $this->assertInstanceOf(User::class, $minister->user);
        $this->assertSame($user->id, $minister->user->id);
    }

    public function test_minister_family_members_and_history_events_relationships(): void
    {
        $minister = Minister::create([
            'registration_number' => '7777',
            'function' => 'Pastor',
            'name' => 'Pastor Marcos',
            'status' => 'ATIVO(A)',
            'sectional' => 'ORMIBAN GO',
            'church_name' => 'Igreja Betel',
            'cpf' => '11122233344',
        ]);

        $family = $minister->familyMembers()->create([
            'relationship' => 'Cônjuge',
            'name' => 'Maria da Silva',
            'birth_date' => '1982-03-20',
        ]);

        $historyDiploma = $minister->historyEvents()->create([
            'occurred_on' => '2000-12-10',
            'code' => '1',
            'description' => 'DIPLOMADO',
        ]);

        $historyOrdenacao = $minister->historyEvents()->create([
            'occurred_on' => '2005-06-20',
            'code' => '2',
            'description' => 'ORDENAÇÃO',
        ]);

        $this->assertCount(1, $minister->familyMembers);
        $this->assertSame('Maria da Silva', $minister->familyMembers->first()->name);
        $this->assertInstanceOf(Carbon::class, $minister->familyMembers->first()->birth_date);

        $this->assertCount(2, $minister->historyEvents);
        $this->assertSame($historyDiploma->id, $minister->diplomaEvent()?->id);
        $this->assertSame($historyOrdenacao->id, $minister->ordinationEvent()?->id);
    }

    public function test_minister_years_of_ministry_and_ordination_calculations(): void
    {
        Carbon::setTestNow('2026-11-04');

        $minister = Minister::create([
            'registration_number' => '8888',
            'function' => 'Pastor',
            'name' => 'Pastor Lucas',
            'status' => 'ATIVO(A)',
            'sectional' => 'ORMIBAN GO',
            'church_name' => 'Igreja Filadélfia',
            'cpf' => '55566677788',
        ]);

        $this->assertNull($minister->yearsOfMinistry());
        $this->assertNull($minister->yearsSinceOrdination());

        $minister->historyEvents()->create([
            'occurred_on' => '2006-11-04',
            'code' => '1',
            'description' => 'DIPLOMADO',
        ]);

        $this->assertNull($minister->yearsOfMinistry());

        $minister->historyEvents()->create([
            'occurred_on' => '2011-11-04',
            'code' => '2',
            'description' => 'ORDENAÇÃO recente',
        ]);
        $minister->historyEvents()->create([
            'occurred_on' => '2001-11-04',
            'code' => '2',
            'description' => 'ORDENAÇÃO',
        ]);

        $this->assertSame(25, $minister->yearsOfMinistry());
        $this->assertSame(15, $minister->yearsSinceOrdination());

        Carbon::setTestNow();
    }
}
