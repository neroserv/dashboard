<?php

use App\Models\Brand;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $defaultBrandId = Brand::query()->where('is_default', true)->value('id')
            ?? Brand::query()->value('id');

        Schema::table('reseller_domains', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        if ($defaultBrandId !== null) {
            DB::table('reseller_domains')->whereNull('brand_id')->update(['brand_id' => $defaultBrandId]);
        }

        Schema::table('reseller_domains', function (Blueprint $table) {
            $table->dropUnique(['domain']);
        });

        Schema::table('reseller_domains', function (Blueprint $table) {
            $table->unique(['brand_id', 'domain']);
        });

        $this->enforceNotNullResellerDomainsBrandId();

        Schema::table('tld_pricelist', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        if ($defaultBrandId !== null) {
            DB::table('tld_pricelist')->whereNull('brand_id')->update(['brand_id' => $defaultBrandId]);
        }

        Schema::table('tld_pricelist', function (Blueprint $table) {
            $table->dropUnique(['tld']);
        });

        Schema::table('tld_pricelist', function (Blueprint $table) {
            $table->unique(['brand_id', 'tld']);
        });

        $this->enforceNotNullTldPricelistBrandId();
    }

    protected function enforceNotNullResellerDomainsBrandId(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE reseller_domains MODIFY brand_id BIGINT UNSIGNED NOT NULL');
        }
    }

    protected function enforceNotNullTldPricelistBrandId(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE tld_pricelist MODIFY brand_id BIGINT UNSIGNED NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tld_pricelist', function (Blueprint $table) {
            $table->dropUnique(['brand_id', 'tld']);
        });

        Schema::table('tld_pricelist', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });

        Schema::table('tld_pricelist', function (Blueprint $table) {
            $table->unique('tld');
        });

        Schema::table('reseller_domains', function (Blueprint $table) {
            $table->dropUnique(['brand_id', 'domain']);
        });

        Schema::table('reseller_domains', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });

        Schema::table('reseller_domains', function (Blueprint $table) {
            $table->unique('domain');
        });
    }
};
