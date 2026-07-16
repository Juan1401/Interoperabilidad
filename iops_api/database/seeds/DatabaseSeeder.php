<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(UserAdminSeeder::class);
        $this->call(OrganizationUserSeeder::class);
        /**
         * Inicio de siembra de datos para HL7 RDA Paciente
         */
        $this->call(Hl7ColombianTechModalitySeeder::class);
        $this->call(Hl7GrupoServiciosSeeder::class);
        $this->call(Icd10coSeeder::class);
        $this->call(Hl7ConditionClinicalSeeder::class);
        $this->call(Hl7ConditionVerificationSeeder::class);
        $this->call(Hl7ConditionCategorySeeder::class);
        $this->call(Hl7AllergyIntoleranceSeeder::class);
        $this->call(MipresInnSeeder::class);
        $this->call(Hl7MedicationStatementStatusSeeder::class);
        $this->call(Hl7FamilyHistoryStatusSeeder::class);
        $this->call(Hl7ParentescoAntecedenteSeeder::class);
        $this->call(Iso31661Seeder::class);
        $this->call(ColombianEthnicGroupSeeder::class);
        $this->call(ColombianDisabilityClassificationSeeder::class);
        $this->call(ColombianGenderIdentitySeeder::class);
        $this->call(Hl7CatalogV2_0203Seeder::class);
        $this->call(ColombianPersonIdentifierSeeder::class);
        $this->call(DivipolaSeeder::class);
        $this->call(ColombianResidenceZoneSeeder::class);
        $this->call(ColombianGenderGroupSeeder::class);
        $this->call(ColombianOrganizationIdentifierSeeder::class);
        $this->call(IhceCatalogsSeeder::class);
        $this->call(Ciuo88AcSeeder::class);
        $this->call(Hl7ClinicalCatalogsSeeder::class);
        $this->call(Hl7PharmacologicalCatalogsSeeder::class);
        $this->call(Hl7AdministrativeCatalogsSeeder::class);
        $this->call(Hl7DemographicCatalogsSeeder::class);
        /**
         * Fin de siembra de datos para HL7 RDA Paciente
         */
    }
}
